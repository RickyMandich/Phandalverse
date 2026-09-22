@extends('layouts.app')

@section('include')
    <script src="//unpkg.com/alpinejs" defer></script>
@endsection

@section('content')
    <div class="container-fluid py-4" x-data="playerView({ 
                                                shareCode: '{{ $session->share_code }}',
                                                sessionId: {{ $session->id }},
                                                sessionUrl: '{{ route('dm.api.public.sessions.load', ['share_code' => $session->share_code]) }}'
                                            })">
        <div class="row g-4">
            <!-- SIDEBAR: Tools -->
            <div class="col-md-3">
                @include('dm.partials.dice_roller')
            </div>

            <div class="col-md-9">
                <!-- HEADER PUBBLICO -->
                <div
                    class="d-flex justify-content-between align-items-center mb-4 bg-dark bg-opacity-25 p-3 rounded shadow-sm border border-secondary border-opacity-25">
                    <div>
                        <span class="badge bg-danger mb-1">Inabion RPG - Powerfail 5.3</span>
                        <h2 class="mb-0">📜 <span class="text-warning">Scena:</span> <span x-text="scene.name"></span></h2>
                        <span class="small text-muted">Codice Sessione: <strong
                                class="text-info">{{ $session->share_code }}</strong></span>
                    </div>
                    <div class="text-end" x-show="scene.state === 'combat'">
                        <span class="badge bg-warning text-dark px-3 py-2">MODALITÀ COMBATTIMENTO</span>
                    </div>
                </div>

                <!-- LISTA ATTORI -->
                <div class="row row-cols-1 row-cols-md-2 g-3">
                    <template x-for="(actor, index) in actors" :key="actor.id">
                        <div class="col">
                            <div class="card bg-dark bg-opacity-50 border-secondary h-100"
                                :class="{ 'border-warning shadow-sm ring-1 ring-warning': index === activeActorIndex && scene.state === 'combat' }">

                                <div
                                    class="card-header p-2 bg-secondary bg-opacity-25 d-flex justify-content-between align-items-center">
                                    <h5 class="m-0 text-truncate"
                                        :class="actor.type === 'pc' ? 'text-info' : 'text-warning'">
                                        <span x-show="index === activeActorIndex && scene.state === 'combat'"
                                            class="text-warning">▶</span>
                                        <span x-text="actor.name"></span>
                                    </h5>
                                    <span class="badge" :class="actor.type === 'pc' ? 'bg-info' : 'bg-danger'"
                                        x-text="actor.type.toUpperCase()"></span>
                                </div>

                                <div class="card-body p-2">
                                    <div class="mb-2">
                                        <div class="text-center p-1 rounded x-small" :class="getDamageState(actor).class">
                                            <strong x-text="getDamageState(actor).state"></strong>
                                            <span x-show="getDamageState(actor).penalty"
                                                x-text="' (' + getDamageState(actor).penalty + ')' "></span>
                                        </div>
                                    </div>

                                    <!-- Defense Info (Public) -->
                                    <div class="d-flex justify-content-around bg-dark bg-opacity-50 rounded p-1 mb-2">
                                        <div class="text-center">
                                            <div class="x-small text-muted">MIS</div>
                                            <div class="fw-bold" x-text="actor.defenses?.mischia || 0"></div>
                                        </div>
                                        <div class="text-center">
                                            <div class="x-small text-muted">TIR</div>
                                            <div class="fw-bold" x-text="actor.defenses?.tiro || 0"></div>
                                        </div>
                                        <div class="text-center">
                                            <div class="x-small text-muted">MAG</div>
                                            <div class="fw-bold" x-text="actor.defenses?.magia || 0"></div>
                                        </div>
                                    </div>

                                    <div x-show="actor.notes" class="x-small text-muted italic text-truncate">
                                        Note: <span x-text="actor.notes"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div x-show="actors.length === 0" class="text-center text-muted py-5">
                    <i class="bi bi-clock-history fs-1 opacity-25"></i>
                    <p class="mt-2 text-white">In attesa che il Master inizi l'incontro o aggiunga personaggi...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function playerView(config) {
            return {
                actors: [],
                scene: { name: '...', state: 'narrative' },
                activeActorIndex: 0,
                diceLog: [],

                ...window.powerfailDiceLogic('Player'),

                init() {
                    this.fetchData();
                    setInterval(() => this.fetchData(), 300);
                },

                async fetchData() {
                    try {
                        const response = await fetch(config.sessionUrl);
                        if (response.ok) {
                            const session = await response.json();
                            if (session && session.data) {
                                const d = typeof session.data === 'string' ? JSON.parse(session.data) : session.data;
                                this.actors = d.actors || [];
                                this.scene = d.scene || { name: 'Incontro' };
                                this.activeActorIndex = d.activeActorIndex || 0;

                                // Sync diceLog but keep local ones if not present?
                                // For now, simple overwrite so they see Master's rolls.
                                if (d.diceLog) this.diceLog = d.diceLog;
                            }
                        }
                    } catch (e) {
                        console.error("Data Sync Error:", e);
                    }
                },

                getDamageState(actor) {
                    const vig = actor.stats?.vig || actor.stats?.fis || 2;
                    const base = vig * 2;
                    const dmg = actor.damage || 0;

                    if (dmg <= base * 1) return { state: 'Illeso', penalty: '', class: 'text-success' };
                    if (dmg <= base * 2) return { state: 'Malconcio', penalty: '', class: 'text-warning' };
                    if (dmg <= base * 3) return { state: 'Contuso', penalty: '-1 azione', class: 'text-warning' };
                    if (dmg <= base * 4) return { state: 'Colpito', penalty: '-2 azioni', class: 'text-danger' };
                    if (dmg <= base * 5) return { state: 'Ferito', penalty: 'Trauma', class: 'text-danger fw-bold' };
                    return { state: 'Inerme', penalty: 'Svenuto', class: 'bg-danger text-white' };
                }
            }
        }
    </script>
@endsection
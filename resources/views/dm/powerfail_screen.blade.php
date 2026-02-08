@extends('layouts.app')

@section('title', 'Schermo del Master - Powerfail System 5.3')

@section('include')
    <script src="//unpkg.com/alpinejs" defer></script>
@endsection

@section('content')
    <div class="container-fluid" x-data="powerfailMaster()">
        <!-- Header -->
        <div class="row bg-dark text-white p-2 align-items-center mb-3">
            <div class="col-md-6">
                <h3 class="m-0">⚡ Powerfail System 5.3 <span class="badge bg-secondary fs-6">Master Screen</span></h3>
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-sm btn-outline-light me-2" @click="saveSession()">
                    <i class="bi bi-save"></i> Salva Sessione
                </button>
                <button class="btn btn-sm btn-outline-warning" @click="loadSessionModal = true">
                    <i class="bi bi-folder2-open"></i> Carica
                </button>
            </div>
        </div>

        <div class="row h-100">
            <!-- LEFT COLUMN: Scene & Tools -->
            <div class="col-md-3 d-flex flex-column gap-3">

                <!-- SCENE CONTEXT -->
                <div class="card bg-dark text-white border-secondary">
                    <div class="card-header bg-secondary bg-opacity-25 fw-bold">1. Contesto Scena</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small text-muted">Nome Scena</label>
                            <input type="text" class="form-control bg-secondary text-white border-secondary"
                                x-model="scene.name" placeholder="Es. L'assalto alla locanda...">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-muted">Stato Scena</label>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm flex-grow-1"
                                    :class="scene.state === 'narrative' ? 'btn-info' : 'btn-outline-secondary'"
                                    @click="scene.state = 'narrative'">
                                    📜 Narrativa
                                </button>
                                <button class="btn btn-sm flex-grow-1"
                                    :class="scene.state === 'combat' ? 'btn-danger' : 'btn-outline-secondary'"
                                    @click="scene.state = 'combat'">
                                    ⚔️ Combattimento
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="form-label small text-muted">Note Master (Private)</label>
                            <textarea class="form-control bg-secondary text-white border-secondary small" rows="3"
                                x-model="scene.notes"></textarea>
                        </div>
                    </div>
                </div>

                <!-- TOOLS: Dice Roller -->
                <div class="card bg-dark text-white border-secondary">
                    <div class="card-header bg-secondary bg-opacity-25 fw-bold d-flex justify-content-between">
                        <span>🎲 Lancio Dadi (d10)</span>
                        <button class="btn btn-sm btn-link text-white p-0" @click="diceLog = []">Pulisci</button>
                    </div>
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <label class="small text-muted">Caratteristica</label>
                                <input type="number" class="form-control form-control-sm" x-model.number="roller.stat"
                                    min="1">
                            </div>
                            <div class="col-4">
                                <label class="small text-muted">Abilità</label>
                                <input type="number" class="form-control form-control-sm" x-model.number="roller.skill"
                                    min="0">
                            </div>
                            <div class="col-4 d-flex align-items-end">
                                <button class="btn btn-sm btn-light w-100 fw-bold" @click="rollDice()">TIRA</button>
                            </div>
                        </div>

                        <!-- Exploding 10s Modal/Prompt Area -->
                        <div x-show="roller.pendingExplosions > 0"
                            class="alert alert-warning p-2 small shadow-sm border-warning text-white">
                            <div class="fw-bold mb-2 text-dark">💥 <span x-text="roller.pendingExplosions"></span> "10"
                                ottenuti! Quanti dadi extra vuoi tirare?</div>

                            <div class="d-flex flex-wrap gap-1">
                                <template x-for="n in (roller.pendingExplosions + 1)">
                                    <button class="btn btn-sm btn-dark border-light fw-bold px-3"
                                        @click="rollExplosions(n-1)" x-text="n-1" title="Tira questa quantità di dadi">
                                    </button>
                                </template>
                            </div>
                        </div>

                        <!-- Dice Log -->
                        <div class="border rounded p-2 bg-secondary bg-opacity-50 small custom-scrollbar"
                            style="max-height: 200px; overflow-y: auto;">
                            <template x-for="(log, idx) in diceLog" :key="idx">
                                <div class="mb-2 border-bottom border-secondary border-opacity-25 pb-1">
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-bold" x-text="log.source || 'Master'"></span>
                                        <span class="text-muted" x-text="log.time"></span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        <template x-for="die in log.results">
                                            <span class="badge" :class="{
                                                                            'bg-success': die >= 8, 
                                                                            'bg-danger': die === 1, 
                                                                            'bg-secondary': die > 1 && die < 8,
                                                                            'border border-warning': die === 10
                                                                        }" x-text="die"></span>
                                        </template>
                                    </div>
                                    <div class="mt-1 x-small">
                                        Successi: <span class="text-success fw-bold" x-text="log.successes"></span>
                                        <span x-show="log.failures > 0" class="text-danger ms-2">Fallimenti: <span
                                                x-text="log.failures"></span></span>
                                    </div>
                                </div>
                            </template>
                            <div x-show="diceLog.length === 0" class="text-muted text-center italic">Nessun lancio
                                effettuato.</div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- CENTER COLUMN: Attori in Scena -->
            <div class="col-md-9">
                <div class="card bg-dark text-white border-secondary h-100">
                    <div
                        class="card-header bg-secondary bg-opacity-25 fw-bold d-flex justify-content-between align-items-center">
                        <span>👥 Attori in Scena</span>
                        <div>
                            <button class="btn btn-sm btn-outline-info me-2" @click="addActor('pc')">+ PG</button>
                            <button class="btn btn-sm btn-outline-danger me-2" @click="addActor('enemy')">+ Nemico</button>
                            <button class="btn btn-sm btn-outline-secondary" @click="addActor('minion')">+ Minion</button>
                        </div>
                    </div>
                    <div class="card-body overflow-auto custom-scrollbar d-flex gap-3 flex-wrap align-items-start">

                        <template x-for="(actor, index) in actors" :key="actor.id">
                            <div class="card border-0 shadow-lg" style="width: 300px;" :class="{
                                                            'bg-dark': actor.type === 'pc',
                                                            'bg-danger bg-opacity-10': actor.type === 'enemy',
                                                            'bg-secondary bg-opacity-10': actor.type === 'minion'
                                                        }">

                                <!-- Header Attore -->
                                <div class="card-header py-1 d-flex justify-content-between align-items-center" :class="{
                                                                'bg-info text-dark': actor.type === 'pc',
                                                                'bg-danger text-white': actor.type === 'enemy',
                                                                'bg-secondary text-white': actor.type === 'minion'
                                                            }">
                                    <input type="text"
                                        class="form-control form-control-sm bg-transparent border-0 fw-bold p-0"
                                        :class="actor.type === 'pc' ? 'text-dark' : 'text-white'" x-model="actor.name">
                                    <button class="btn btn-sm btn-link p-0"
                                        :class="actor.type === 'pc' ? 'text-dark' : 'text-white'"
                                        @click="removeActor(index)">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>

                                <div class="card-body p-2">
                                    <!-- HP & Stats Row -->
                                    <div class="row g-1 align-items-center mb-2">
                                        <div class="col-6">
                                            <label class="x-small text-muted">HP Attuali / Max</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number"
                                                    class="form-control bg-dark text-white border-secondary"
                                                    x-model.number="actor.hp" @input="checkHpTriggers(actor)">
                                                <span
                                                    class="input-group-text bg-secondary border-secondary text-light">/</span>
                                                <input type="number"
                                                    class="form-control bg-dark text-white border-secondary"
                                                    x-model.number="actor.maxHp">
                                            </div>
                                        </div>

                                        <!-- Only for PCs and Enzymes (Relevant Enemies) -->
                                        <template x-if="actor.type !== 'minion'">
                                            <div class="col-6">
                                                <label class="x-small text-muted">Fatica</label>
                                                <input type="number"
                                                    class="form-control form-control-sm bg-dark text-white border-secondary"
                                                    x-model.number="actor.fatigue">
                                            </div>
                                        </template>
                                    </div>

                                    <!-- Minion Triggers visualization -->
                                    <div x-show="actor.type === 'minion' && actor.statusMessage"
                                        class="alert alert-danger p-1 x-small mb-2">
                                        <i class="bi bi-exclamation-triangle"></i> <span
                                            x-text="actor.statusMessage"></span>
                                    </div>

                                    <!-- Attributes & Skills (Collapsible for compaction) -->
                                    <template x-if="actor.type !== 'minion'">
                                        <div class="mb-2">
                                            <div
                                                class="d-flex justify-content-between bg-secondary bg-opacity-25 p-1 rounded mb-1">
                                                <span class="x-small fw-bold">CARATTERISTICHE</span>
                                            </div>
                                            <div class="d-flex gap-1 mb-2">
                                                <input type="number"
                                                    class="form-control form-control-sm bg-dark text-white border-secondary"
                                                    placeholder="FIS" x-model.number="actor.stats.fis" title="Fisico">
                                                <input type="number"
                                                    class="form-control form-control-sm bg-dark text-white border-secondary"
                                                    placeholder="MEN" x-model.number="actor.stats.men" title="Mentale">
                                                <input type="number"
                                                    class="form-control form-control-sm bg-dark text-white border-secondary"
                                                    placeholder="SOC" x-model.number="actor.stats.soc" title="Sociale">
                                            </div>

                                            <div
                                                class="d-flex justify-content-between bg-secondary bg-opacity-25 p-1 rounded mb-1">
                                                <span class="x-small fw-bold">ABILITÀ VELOCI</span>
                                            </div>
                                            <div class="d-flex flex-wrap gap-1">
                                                <button class="btn btn-xs btn-outline-light flex-grow-1"
                                                    @click="quickRoll(actor, 'mischia')">Mischia</button>
                                                <button class="btn btn-xs btn-outline-light flex-grow-1"
                                                    @click="quickRoll(actor, 'tiro')">Tiro</button>
                                                <button class="btn btn-xs btn-outline-light flex-grow-1"
                                                    @click="quickRoll(actor, 'tecnica')">Tecnica</button>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Notes / Statuses -->
                                    <div>
                                        <label class="x-small text-muted">Stati & Note</label>
                                        <textarea
                                            class="form-control form-control-sm bg-dark text-white border-secondary p-1"
                                            rows="2" x-model="actor.notes"></textarea>
                                    </div>

                                </div>
                            </div>
                        </template>

                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <!-- Load Session Modal -->
        <div class="modal fade" id="loadSessionModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.8);"
            x-show="loadSessionModal" x-transition.opacity>
            <div class="modal-dialog">
                <div class="modal-content bg-dark text-white border-secondary">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title">Carica Sessione Powerfail</h5>
                        <button type="button" class="btn-close btn-close-white" @click="loadSessionModal = false"></button>
                    </div>
                    <div class="modal-body">
                        <button class="btn btn-sm btn-outline-light mb-3 w-100" @click="loadSessionsList()">🔄 Aggiorna
                            Lista</button>
                        <div class="list-group">
                            <template x-for="s in availableSessions" :key="s.id">
                                <button
                                    class="list-group-item list-group-item-action bg-dark text-white border-secondary d-flex justify-content-between"
                                    @click="loadSession(s.id)">
                                    <span x-text="s.name"></span>
                                    <span class="small text-muted" x-text="new Date(s.updated_at).toLocaleString()"></span>
                                </button>
                            </template>
                            <div x-show="availableSessions.length === 0" class="text-center text-muted p-3">
                                Nessuna sessione trovata.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function powerfailMaster() {
            return {
                scene: {
                    name: 'Nuova Scena',
                    state: 'narrative', // narrative, combat
                    notes: ''
                },
                actors: [],
                diceLog: [],
                currentSessionId: null,
                loadSessionModal: false,
                availableSessions: [],
                roller: {
                    stat: 0,
                    skill: 0,
                    pendingExplosions: 0,
                    pendingFailures: 0,
                    currentSuccesses: 0,
                    currentFailures: 0,
                    lastGenerations: [] // Array of Arrays [[10,5], [8]]
                },

                init() {
                    this.loadSessionsList();
                },

                addActor(type) {
                    this.actors.push({
                        id: Date.now(),
                        type: type,
                        name: type === 'pc' ? 'Nuovo Giocatore' : (type === 'minion' ? 'Minion' : 'Nemico'),
                        hp: 10,
                        maxHp: 10,
                        fatigue: 0,
                        stats: { fis: 2, men: 2, soc: 2 },
                        notes: '',
                        statusMessage: ''
                    });
                },

                removeActor(index) {
                    if (confirm('Rimuovere questo attore?')) {
                        this.actors.splice(index, 1);
                    }
                },

                checkHpTriggers(actor) {
                    if (actor.type === 'minion') {
                        if (actor.hp <= 0) {
                            actor.statusMessage = 'ELIMINATO - Rimuovi dal gioco';
                            actor.hp = 0; // Clamp visually
                        } else if (actor.hp <= actor.maxHp / 2) {
                            actor.statusMessage = 'FERITO - -1 Azione';
                        } else {
                            actor.statusMessage = '';
                        }
                    } else if (actor.type === 'enemy') {
                        if (actor.hp <= 0) {
                            actor.notes += '\n[SCONFITTO]';
                        }
                    }
                },

                // Dice Logic
                rollDice() {
                    const pool = (this.roller.stat || 0) + (this.roller.skill || 0);
                    if (pool <= 0) return;

                    this.performRoll(pool, true);
                },

                performRoll(count, isFresh) {
                    const results = [];
                    let tens = 0;
                    let ones = 0;
                    let successes = 0;

                    for (let i = 0; i < count; i++) {
                        const die = Math.floor(Math.random() * 10) + 1;
                        results.push(die);
                        if (die === 10) tens++;
                        if (die === 1) ones++;
                        if (die >= 8) successes++;
                    }

                    if (isFresh) {
                        this.roller.currentSuccesses = successes;
                        this.roller.currentFailures = ones;
                        this.roller.pendingExplosions = tens;
                        this.roller.lastGenerations = [results]; // Start fresh log with first generation
                    } else {
                        this.roller.currentSuccesses += successes;
                        this.roller.currentFailures += ones;
                        this.roller.pendingExplosions = tens;
                        this.roller.lastGenerations.push(results); // Add new generation
                    }

                    // Auto-calc net outcome if no explosions pending
                    if (this.roller.pendingExplosions === 0) {
                        this.finalizeRoll();
                    }
                },

                rollExplosions(count) {
                    if (count > this.roller.pendingExplosions) count = this.roller.pendingExplosions;
                    // Logic: User chose to roll 'count' extra dice.
                    // We consume the pending explosions state.
                    this.roller.pendingExplosions = 0;
                    if (count > 0) {
                        this.performRoll(count, false);
                    } else {
                        this.finalizeRoll(); // Chose 0, end
                    }
                },

                finalizeRoll() {
                    const netSuccesses = Math.max(0, this.roller.currentSuccesses - this.roller.currentFailures);
                    const failures = this.roller.currentFailures;

                    this.diceLog.unshift({
                        time: new Date().toLocaleTimeString(),
                        generations: JSON.parse(JSON.stringify(this.roller.lastGenerations)), // Deep copy
                        successes: netSuccesses,
                        failures: failures,
                        source: 'Master'
                    });

                    // Reset
                    this.roller.stat = 0;
                    this.roller.skill = 0;
                    this.roller.lastGenerations = [];
                },

                quickRoll(actor, skillName) {
                    // Placeholder for quick roll logic using actor stats
                    // For now just setting values for manual roll
                    this.roller.stat = actor.stats.fis; // Assume phys for everything for demo
                    this.roller.skill = 3;
                    alert(`Impostato tiro per ${actor.name} su ${skillName}. Premi TIRA.`);
                },

                async saveSession() {
                    // If it's a new session, ask for name
                    if (!this.currentSessionId) {
                        const name = prompt("Nome della nuova sessione:", this.scene.name);
                        if (!name) return;
                        this.scene.name = name;

                        try {
                            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                            const response = await fetch('/dm/api/sessions', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                                body: JSON.stringify({
                                    name: this.scene.name,
                                    system: 'powerfail',
                                    data: {
                                        scene: this.scene,
                                        actors: this.actors,
                                        diceLog: this.diceLog
                                    }
                                })
                            });
                            const data = await response.json();
                            this.currentSessionId = data.id;
                            alert('Sessione CREATA con successo!');
                        } catch (e) {
                            alert('Errore nel salvataggio: ' + e);
                        }
                    } else {
                        // Update existing
                        try {
                            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                            await fetch(`/dm/api/sessions/${this.currentSessionId}`, {
                                method: 'PATCH',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                                body: JSON.stringify({
                                    name: this.scene.name,
                                    system: 'powerfail',
                                    data: {
                                        scene: this.scene,
                                        actors: this.actors,
                                        diceLog: this.diceLog
                                    }
                                })
                            });
                            alert('Sessione AGGIORNATA con successo!');
                        } catch (e) {
                            alert('Errore nell\'aggiornamento: ' + e);
                        }
                    }
                },

                async loadSessionsList() {
                    const response = await fetch('/dm/api/sessions');
                    const sessions = await response.json();
                    this.availableSessions = sessions.filter(s => s.system === 'powerfail');
                },

                async loadSession(id) {
                    const response = await fetch(`/dm/api/sessions/${id}`);
                    const session = await response.json();

                    this.currentSessionId = session.id;
                    this.scene = session.data.scene || this.scene;
                    this.actors = session.data.actors || [];
                    // diceLog usually not loaded to keep history clean/relevant to current play, but acceptable to load
                    this.diceLog = session.data.diceLog || [];

                    this.loadSessionModal = false;
                }
            }
        }
    </script>

    <style>
        .x-small {
            font-size: 0.75rem;
        }

        .btn-xs {
            padding: 0.1rem 0.3rem;
            font-size: 0.7rem;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #666;
            border-radius: 3px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #222;
        }
    </style>
@endsection
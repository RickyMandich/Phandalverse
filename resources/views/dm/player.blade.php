@extends('layouts.app')

@section('content')
    <div class="container-fluid py-4" x-data="playerView({ 
                shareCode: '{{ $session->share_code }}',
                sessionId: {{ $session->id }}
            })">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <!-- HEADER PUBBLICO -->
                <div
                    class="d-flex justify-content-between align-items-center mb-4 bg-dark bg-opacity-25 p-3 rounded shadow-sm border border-secondary border-opacity-25">
                    <div>
                        <h2 class="mb-0">⚔️ <span class="text-warning">Incontro:</span> {{ $session->name }}</h2>
                        <span class="small text-muted">Codice Sessione: <strong
                                class="text-info">{{ $session->share_code }}</strong></span>
                    </div>
                    <div class="text-end">
                        <div class="fs-4 fw-bold text-warning">Round <span x-text="round"></span></div>
                    </div>
                </div>

                <!-- LISTA COMBATTENTI -->
                <div class="list-group custom-scrollbar">
                    <template x-for="(combatant, index) in combatants" :key="combatant.instanceId">
                        <div class="list-group-item mb-2 rounded border-0 shadow-sm combatant-card p-0 overflow-hidden"
                            x-show="!hideDead || combatant.hp > 0 || combatant.type === 'player'" :class="{
                                        'active-turn': currentTurnIndex === index,
                                        'dead-combatant bg-black bg-opacity-50 opacity-50': combatant.hp <= 0 && combatant.type !== 'player'
                                    }">

                            <div class="d-flex align-items-center p-3">
                                <!-- INITIATIVE -->
                                <div class="me-4 text-center" style="width: 45px;">
                                    <div class="initiative-badge rounded-circle d-flex align-items-center justify-content-center mx-auto"
                                        style="width: 40px; height: 40px;"
                                        :class="currentTurnIndex === index ? 'bg-warning text-dark shadow' : 'bg-dark text-white border border-secondary'">
                                        <span class="fw-bold fs-5" x-text="combatant.initiative"></span>
                                    </div>
                                </div>

                                <!-- NAME -->
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center">
                                        <h4 class="mb-0" :class="combatant.type === 'player' ? 'text-info' : 'text-warning'"
                                            x-text="combatant.alias || (combatant.type === 'player' ? combatant.name : 'Nemico')">
                                        </h4>
                                        <span class="ms-3 badge bg-danger"
                                            x-show="combatant.hp <= 0 && combatant.type !== 'player'">CADUTO</span>
                                    </div>
                                    <div class="mt-2 d-flex flex-wrap gap-1">
                                        <template x-for="status in combatant.statuses" :key="status">
                                            <span class="badge bg-danger rounded-pill px-2" x-text="status"></span>
                                        </template>
                                        <span x-show="combatant.statuses.length === 0"
                                            class="small text-muted italic">Nessuno stato</span>
                                    </div>
                                </div>

                                <!-- INDICATORE TURNO -->
                                <div x-show="currentTurnIndex === index" class="ms-3">
                                    <span class="badge bg-warning text-dark px-3 py-2 animate-pulse">
                                        <i class="bi bi-caret-left-fill me-1"></i> TURNO ATTUALE
                                    </span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div x-show="combatants.length === 0" class="text-center text-muted py-5">
                        <i class="bi bi-clock-history fs-1 opacity-25"></i>
                        <p class="mt-2">In attesa che il Master inizi l'incontro...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function playerView(config) {
            return {
                combatants: [],
                round: 1,
                currentTurnIndex: 0,
                hideDead: false,
                pollingInterval: null,

                init() {
                    this.fetchData();
                    // Polling ogni 3 secondi per aggiornamenti in tempo reale
                    this.pollingInterval = setInterval(() => this.fetchData(), 3000);
                },

                async fetchData() {
                    try {
                        const response = await fetch(`/dm/api/public/sessions/${config.shareCode}`);
                        if (response.ok) {
                            const session = await response.json();
                            const data = session.data || {};
                            this.combatants = data.combatants || [];
                            this.round = data.round || 1;
                            this.currentTurnIndex = data.currentTurnIndex || 0;
                            this.hideDead = data.hideDead || false;

                            // Sort locally to ensure same order as Master
                            this.combatants.sort((a, b) => b.initiative - a.initiative);
                        }
                    } catch (e) {
                        console.error("Errore aggiornamento dati player view:", e);
                    }
                }
            }
        }
    </script>

    <style>
        .combatant-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 255, 255, 0.05);
            background: rgba(255, 255, 255, 0.03);
        }

        .active-turn {
            border: 2px solid #ffc107 !important;
            background: rgba(255, 193, 7, 0.05) !important;
            transform: scale(1.02);
            z-index: 5;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3) !important;
        }

        .animate-pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.6;
            }

            100% {
                opacity: 1;
            }
        }

        .dead-combatant {
            filter: grayscale(100%);
        }
    </style>
@endsection
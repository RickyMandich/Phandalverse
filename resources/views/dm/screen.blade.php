@extends('layouts.app')

@section('main-class', 'p-0 text-white')
@section('container-class', 'container-fluid p-0')

@section('content')
    <div x-data="dmScreen({ 
                isMaster: {{ Auth::user()->isMaster() ? 'true' : 'false' }},
                isMasterUtils: {{ Auth::user()->isMasterUtils() ? 'true' : 'false' }} 
            })">
        <div class="row gx-0">
            <!-- SIDEBAR LEFT: Libreria -->
            <div class="col-md-3 border-end vh-100 overflow-auto bg-dark p-3 custom-scrollbar">
                <h4 class="mb-3 text-light">📚 Libreria</h4>

                <ul class="nav nav-tabs mb-3" id="libraryTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active py-1 px-2" id="templates-tab" data-bs-toggle="tab"
                            data-bs-target="#templates-pane" type="button">Mostri</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link py-1 px-2" id="players-tab" data-bs-toggle="tab"
                            data-bs-target="#players-pane" type="button">Giocatori</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link py-1 px-2" id="groups-tab" data-bs-toggle="tab"
                            data-bs-target="#groups-pane" type="button">Gruppi</button>
                    </li>
                </ul>

                <div class="tab-content text-light">
                    <!-- MOSTRI -->
                    <div class="tab-pane fade show active" id="templates-pane">
                        <button class="btn btn-sm btn-success w-100 mb-2" @click="openCharacterModal('template')">+ Nuovo
                            Mostro</button>
                        <div class="list-group list-group-flush">
                            <template x-for="char in templates" :key="char.id">
                                <div class="list-group-item list-group-item-action bg-dark border-secondary p-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong class="text-light" x-text="char.name"></strong>
                                        <button class="btn btn-sm btn-outline-secondary border-0 p-0"
                                            @click="openCharacterModal('template', char)"><i
                                                class="bi bi-pencil small"></i></button>
                                    </div>
                                    <div class="d-flex align-items-center mt-1">
                                        <input type="number"
                                            class="form-control form-control-sm bg-secondary text-white border-0 w-25 me-1"
                                            x-model.number="char.qty">
                                        <button class="btn btn-sm btn-primary flex-grow-1 py-0 shadow-sm"
                                            @click="addToCombat(char)">Aggiungi</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- GIOCATORI -->
                    <div class="tab-pane fade" id="players-pane">
                        <button class="btn btn-sm btn-info w-100 mb-2" @click="openCharacterModal('player')">+ Nuovo
                            Giocatore</button>
                        <div class="list-group list-group-flush">
                            <template x-for="char in players" :key="char.id">
                                <div
                                    class="list-group-item list-group-item-action bg-dark border-secondary p-2 d-flex justify-content-between align-items-center">
                                    <span class="text-light" x-text="char.name"></span>
                                    <div>
                                        <button class="btn btn-sm btn-outline-secondary border-0 me-1"
                                            @click="openCharacterModal('player', char)"><i
                                                class="bi bi-pencil"></i></button>
                                        <button class="btn btn-sm btn-primary py-0 shadow-sm"
                                            @click="addToCombat(char)">Aggiungi</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- GRUPPI -->
                    <div class="tab-pane fade" id="groups-pane">
                        <button class="btn btn-sm btn-warning w-100 mb-2 text-dark font-weight-bold"
                            @click="openGroupModal()">+ Nuovo Gruppo</button>
                        <div class="list-group list-group-flush">
                            <template x-for="group in groups" :key="group.id">
                                <div
                                    class="list-group-item list-group-item-action bg-dark border-secondary p-2 d-flex justify-content-between align-items-center">
                                    <span class="text-light" x-text="group.name"></span>
                                    <div>
                                        <button class="btn btn-sm btn-outline-secondary border-0 me-1"
                                            @click="openGroupModal(group)"><i class="bi bi-pencil"></i></button>
                                        <button class="btn btn-sm btn-warning py-0 text-dark shadow-sm"
                                            @click="addGroupToCombat(group)">Aggiungi</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CENTER: Combat Tracker -->
            <div class="col-md-6 vh-100 d-flex flex-column bg-secondary bg-opacity-10 shadow-sm">
                <!-- HEADER FISSO -->
                <div class="p-3 bg-dark bg-opacity-25 border-bottom border-dark">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <h2 class="mb-0 me-3">⚔️ <span class="badge bg-warning text-dark font-monospace"
                                    x-text="'R' + round"></span></h2>

                            <!-- Session Selector -->
                            <div class="dropdown">
                                <button
                                    class="btn btn-sm btn-outline-light dropdown-toggle border-0 d-flex align-items-center"
                                    type="button" data-bs-toggle="dropdown" @click="loadSessionsList()">
                                    <i class="bi bi-folder2-open me-2"></i>
                                    <span class="text-truncate" style="max-width: 150px;"
                                        x-text="currentSession.name"></span>
                                    <span class="ms-2 badge bg-warning text-dark px-2" x-text="currentSession.share_code"
                                        x-show="currentSession.share_code"></span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-dark shadow border-secondary">
                                    <li><a class="dropdown-item small" href="#" @click.prevent="createSession()">+ Nuova
                                            Sessione</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <template x-for="s in availableSessions" :key="s.id">
                                        <li class="d-flex align-items-center justify-content-between px-2 py-1">
                                            <div class="flex-grow-1 d-flex flex-column" @click="switchSession(s)"
                                                style="cursor: pointer;">
                                                <span class="small fw-bold text-light" x-text="s.name"></span>
                                                <span class="x-small text-muted" x-text="s.share_code"></span>
                                            </div>
                                            <button class="btn btn-sm btn-link text-danger p-0 ms-1 shadow-none"
                                                @click.stop="deleteSession(s.id)" x-show="availableSessions.length > 1">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </li>
                                    </template>
                                </ul>
                            </div>

                            <!-- COPY BUTTON -->
                            <button class="btn btn-sm btn-outline-info border-0 ms-2 px-2"
                                x-show="currentSession.share_code"
                                @click="const url = window.location.origin + '/dm/player/' + currentSession.share_code; navigator.clipboard.writeText(url); alert('Link copiato!');"
                                title="Copia link giocatori">
                                <i class="bi bi-link-45deg"></i>
                            </button>
                        </div>

                        <div class="d-flex align-items-center">
                            <div class="form-check form-switch me-3 d-none d-lg-block">
                                <input class="form-check-input" type="checkbox" id="hideDeadSwitch" x-model="hideDead">
                                <label class="form-check-label small" for="hideDeadSwitch">Nascondi Morti</label>
                            </div>
                            <button class="btn btn-sm btn-success me-1 px-3 shadow-sm" @click="startCombat()">⚔️
                                Inizia</button>
                            <button class="btn btn-sm btn-warning text-dark px-3 shadow-sm" @click="nextTurn()">⏩
                                Prossimo</button>
                        </div>
                    </div>
                </div>

                <!-- LISTA SCORREVOLE -->
                <div class="flex-grow-1 overflow-auto p-3 custom-scrollbar">
                    <div class="list-group">
                        <template x-for="(combatant, index) in combatants" :key="combatant.instanceId">
                            <div class="list-group-item mb-2 rounded border-0 shadow-sm combatant-card p-0 overflow-hidden"
                                x-show="!hideDead || combatant.hp > 0 || combatant.type === 'player'" :class="{
                                        'active-turn': currentTurnIndex === index,
                                        'selected-combatant': selectedCombatant && selectedCombatant.instanceId === combatant.instanceId,
                                        'dead-combatant bg-black bg-opacity-50 opacity-75': combatant.hp <= 0 && combatant.type !== 'player'
                                    }" @click="selectCombatant(combatant)">

                                <div class="d-flex align-items-center p-2">
                                    <!-- INITIATIVE -->
                                    <div class="me-3 text-center" style="width: 45px;">
                                        <div class="initiative-badge rounded-circle d-flex align-items-center justify-content-center mx-auto"
                                            style="width: 32px; height: 32px;"
                                            :class="currentTurnIndex === index ? 'bg-warning text-dark shadow' : 'bg-dark text-white border border-secondary'">
                                            <input type="number"
                                                class="form-control form-control-sm text-center p-0 border-0 bg-transparent fw-bold"
                                                :class="currentTurnIndex === index ? 'text-dark' : 'text-white'"
                                                x-model.number="combatant.initiative" @change="sortCombat()">
                                        </div>
                                    </div>

                                    <!-- INFO -->
                                    <div class="flex-grow-1 text-truncate">
                                        <div class="d-flex align-items-center overflow-hidden">
                                            <h6 class="mb-0 text-truncate fw-bold"
                                                :class="combatant.type === 'player' ? 'text-info' : 'text-warning'"
                                                x-text="combatant.alias || combatant.name"></h6>
                                            <span class="x-small text-muted ms-2" x-show="combatant.alias">(<span
                                                    x-text="combatant.name"></span>)</span>
                                        </div>
                                        <div class="d-flex align-items-center mt-1">
                                            <span class="badge bg-dark me-1 border border-secondary x-small">CA <span
                                                    x-text="combatant.ac"></span></span>
                                            <template x-for="status in combatant.statuses" :key="status">
                                                <span class="badge bg-danger me-1 x-small" x-text="status"></span>
                                            </template>
                                            <i class="bi bi-journal-text ms-2" x-show="combatant.personalNotes && isMaster"
                                                @click.stop="combatant.showNotesInline = !combatant.showNotesInline"
                                                :class="combatant.showNotesInline ? 'text-info' : ''"></i>
                                        </div>
                                    </div>

                                    <!-- HP -->
                                    <div class="d-flex align-items-center bg-dark bg-opacity-25 rounded px-2 py-1 mx-2"
                                        style="min-width: 170px;">
                                        <button class="btn btn-sm text-danger p-0 border-0 shadow-none"
                                            @click.stop="modifyHp(combatant, -1)">-</button>
                                        <input type="number"
                                            class="form-control form-control-sm text-center p-0 fw-bold border-0 bg-transparent mx-1 text-white"
                                            style="width: 35px;" x-model.number="combatant.hp"
                                            :class="{'text-danger': combatant.hp <= 0, 'text-warning': combatant.hp > 0 && combatant.hp < (combatant.maxHp/2), 'text-success': combatant.hp >= (combatant.maxHp/2)}">
                                        <span class="text-muted x-small">/</span>
                                        <input type="number"
                                            class="form-control form-control-sm text-center p-0 border-0 bg-transparent text-muted ms-1"
                                            style="width: 35px; font-size: 0.8rem;" x-model.number="combatant.maxHp"
                                            @change="saveSession()">
                                        <button class="btn btn-sm text-success p-0 border-0 shadow-none"
                                            @click.stop="modifyHp(combatant, 1)">+</button>
                                    </div>

                                    <!-- MENU -->
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-dark px-1 py-0 border-0 shadow-none" type="button"
                                            data-bs-toggle="dropdown">⋮</button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow border-secondary">
                                            <li><a class="dropdown-item py-1 small" href="#"
                                                    @click.prevent="addStatus(combatant)">Stato</a></li>
                                            <li><a class="dropdown-item py-1 small" href="#"
                                                    @click.prevent="let a = prompt('Alias:', combatant.alias); if(a !== null) combatant.alias = a">Alias</a>
                                            </li>
                                            <li>
                                                <hr class="dropdown-divider my-1">
                                            </li>
                                            <li><a class="dropdown-item text-danger py-1 small" href="#"
                                                    @click.prevent="removeCombatant(index)">Rimuovi</a></li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- NOTE -->
                                <div x-show="combatant.showNotesInline && isMaster" x-transition
                                    class="p-2 border-top border-secondary bg-dark bg-opacity-50">
                                    <textarea
                                        class="form-control form-control-sm bg-transparent text-light border-0 x-small"
                                        rows="2" placeholder="Note rapide..." x-model="combatant.personalNotes"
                                        @change="saveSession()"></textarea>
                                </div>
                            </div>
                        </template>

                        <div x-show="combatants.length === 0" class="text-center text-muted py-5">
                            <i class="bi bi-shield-x fs-1 opacity-25"></i>
                            <p class="mt-2 small">Nessun partecipante. Aggiungili dalla libreria a sinistra.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SIDEBAR RIGHT: Dettagli -->
            <div class="col-md-3 border-start vh-100 overflow-auto bg-dark p-3 text-light custom-scrollbar">
                <h4 class="mb-3">📜 Dettagli</h4>
                <template x-if="selectedCombatant">
                    <div class="animate-fade-in">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h4 class="mb-0 fw-bold" x-text="selectedCombatant.alias || selectedCombatant.name"
                                    :class="selectedCombatant.type === 'player' ? 'text-info' : 'text-warning'"></h4>
                                <p class="x-small text-muted" x-show="selectedCombatant.alias">Orig: <span
                                        x-text="selectedCombatant.name"></span></p>
                            </div>
                            <span class="badge bg-secondary border border-secondary shadow-sm">CA <span
                                    x-text="selectedCombatant.ac"></span></span>
                        </div>

                        <!-- STATS -->
                        <div class="row g-1 mb-3 text-center">
                            <template x-for="(val, stat) in selectedCombatant.stats.attributes" :key="stat">
                                <div class="col-4">
                                    <div class="bg-secondary bg-opacity-10 rounded p-1 border border-secondary">
                                        <div class="text-uppercase x-small fw-bold opacity-50" x-text="stat"></div>
                                        <div class="fw-bold fs-5" x-text="val"></div>
                                        <div class="x-small fw-bold text-info"
                                            x-text="(getStatModifier(val) >= 0 ? '+' : '') + getStatModifier(val)"></div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- NOTE DM -->
                        <div class="mb-3" x-show="isMaster">
                            <label class="x-small text-info fw-bold mb-1"><i class="bi bi-pencil-square"></i> Note Segrete
                                DM</label>
                            <textarea class="form-control bg-secondary bg-opacity-25 text-white border-0 small" rows="4"
                                x-model="selectedCombatant.personalNotes" placeholder="Appunti..."></textarea>
                        </div>

                        <!-- STAT BLOCK -->
                        <div class="mb-3">
                            <h5 class="border-bottom border-secondary pb-1 text-warning x-small fw-bold text-uppercase"><i
                                    class="bi bi-shield-shaded"></i> Stat Block</h5>
                            <div x-html="selectedStatBlock"
                                class="statblock-rendered p-2 bg-dark rounded small shadow-sm border border-secondary">
                            </div>
                        </div>
                    </div>
                </template>
                <div x-show="!selectedCombatant" class="text-muted text-center pt-5">
                    <i class="bi bi-info-circle fs-3 opacity-25"></i>
                    <p class="small mt-2">Clicca su un personaggio per i dettagli</p>
                </div>
            </div>
        </div>
    </div>

    <!-- MODALS -->
    <!-- (Il resto dei modali e degli stili rimane invariato rispetto alla versione precedente per brevità, 
              ma inclusi qui per integrità del database di conoscenza se necessario) -->

    <!-- MODAL: Crea/Modifica Personaggio -->
    <div class="modal fade" id="characterModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark text-white border-secondary shadow">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" x-text="modalMode === 'create' ? 'Crea' : 'Modifica'"></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-2 text-white">
                                <label class="small">Nome</label>
                                <input type="text" class="form-control form-control-sm bg-secondary text-white border-0"
                                    x-model="characterForm.name">
                            </div>
                            <div class="mb-2">
                                <label class="small">Classe Armatura (CA)</label>
                                <input type="number" class="form-control form-control-sm bg-secondary text-white border-0"
                                    x-model.number="characterForm.stats.ac">
                            </div>
                            <div class="mb-2">
                                <label class="small"
                                    x-text="characterForm.type === 'template' ? 'Formula HP (es. 3d8+4)' : 'HP Iniziali'"></label>
                                <input type="text" class="form-control form-control-sm bg-secondary text-white border-0"
                                    x-model="characterForm.stats.hp_formula">
                            </div>
                        </div>
                        <div class="col-md-6 border-start border-secondary">
                            <label class="small mb-1 d-block text-center">Caratteristiche e T.S.</label>
                            <div class="row g-2">
                                <template x-for="(val, stat) in characterForm.stats.attributes" :key="stat">
                                    <div class="col-4 text-center">
                                        <div class="text-uppercase x-small fw-bold" x-text="stat"></div>
                                        <input type="number"
                                            class="form-control form-control-sm bg-secondary text-white border-0 text-center"
                                            x-model.number="characterForm.stats.attributes[stat]">
                                        <div class="form-check form-check-inline mt-1">
                                            <input class="form-check-input" type="checkbox"
                                                x-model="characterForm.stats.saves[stat]">
                                            <label class="form-check-label x-small">T.S.</label>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                    <button type="button" class="btn btn-primary shadow" @click="saveCharacter()">Salva</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: Gruppi -->
    <div class="modal fade" id="groupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white border-secondary shadow">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">Gestione Gruppo</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="small">Nome Gruppo</label>
                        <input type="text" class="form-control bg-secondary text-white border-0" x-model="groupForm.name">
                    </div>
                    <label class="small">Membri</label>
                    <template x-for="(member, idx) in groupForm.members" :key="idx">
                        <div class="d-flex mb-2 align-items-center">
                            <select class="form-control form-control-sm bg-secondary text-white border-0 me-1"
                                x-model="member.character_id">
                                <option value="">Scegli...</option>
                                <optgroup label="Giocatori">
                                    <template x-for="p in players" :key="p.id">
                                        <option :value="p.id" x-text="p.name"></option>
                                    </template>
                                </optgroup>
                                <optgroup label="Mostri">
                                    <template x-for="t in templates" :key="t.id">
                                        <option :value="t.id" x-text="t.name"></option>
                                    </template>
                                </optgroup>
                            </select>
                            <input type="number"
                                class="form-control form-control-sm bg-secondary text-white border-0 w-25 me-1"
                                x-model.number="member.qty">
                            <button class="btn btn-sm btn-outline-danger border-0" @click="removeGroupMember(idx)"><i
                                    class="bi bi-trash"></i></button>
                        </div>
                    </template>
                    <button class="btn btn-sm btn-outline-info w-100" @click="addGroupMember()">+ Aggiungi</button>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                    <button type="button" class="btn btn-primary" @click="saveGroup()">Salva</button>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/dm-screen.js') }}?v={{ time() }}" defer></script>
    <script src="//unpkg.com/alpinejs" defer></script>

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 5px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #222;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #444;
            border-radius: 5px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .combatant-card {
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid #333 !important;
            background: #222;
        }

        .active-turn {
            scale: 1.02;
            border: 2px solid #ffc107 !important;
            box-shadow: 0 0 15px rgba(255, 193, 7, 0.3);
            z-index: 5;
        }

        .selected-combatant {
            border: 2px solid #0dcaf0 !important;
        }

        .dead-combatant {
            filter: grayscale(100%);
            opacity: 0.5;
        }

        .x-small {
            font-size: 0.7rem;
        }

        .statblock-rendered {
            max-height: 400px;
            overflow-y: auto;
            background: #111;
            border: 1px solid #333;
        }

        .statblock-rendered h1,
        .statblock-rendered h2,
        .statblock-rendered h3 {
            color: #f39c12;
            border-bottom: 1px solid #f39c12;
            margin-top: 10px;
            font-size: 1.1rem;
        }

        .animate-fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
@endsection
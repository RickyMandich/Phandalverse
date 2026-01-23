@extends('layouts.app')

@section('main-class', 'p-0')
@section('container-class', 'container-fluid p-0')

@section('content')
    <div x-data="dmScreen({ 
                        isMaster: {{ Auth::user()->isMaster() ? 'true' : 'false' }},
                        isMasterUtils: {{ Auth::user()->isMasterUtils() ? 'true' : 'false' }} 
                    })">
        <div class="row gx-0">
            <!-- SIDEBAR: Libreria -->
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
                                        <button class="btn btn-sm btn-primary flex-grow-1 py-0"
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
                                        <button class="btn btn-sm btn-primary py-0"
                                            @click="addToCombat(char)">Aggiungi</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- GRUPPI -->
                    <div class="tab-pane fade" id="groups-pane">
                        <button class="btn btn-sm btn-warning w-100 mb-2 text-dark" @click="openGroupModal()">+ Nuovo
                            Gruppo</button>
                        <div class="list-group list-group-flush">
                            <template x-for="group in groups" :key="group.id">
                                <div
                                    class="list-group-item list-group-item-action bg-dark border-secondary p-2 d-flex justify-content-between align-items-center">
                                    <span class="text-light" x-text="group.name"></span>
                                    <div>
                                        <button class="btn btn-sm btn-outline-secondary border-0 me-1"
                                            @click="openGroupModal(group)"><i class="bi bi-pencil"></i></button>
                                        <button class="btn btn-sm btn-warning py-0 text-dark"
                                            @click="addGroupToCombat(group)">Aggiungi Gruppo</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CENTER: Combat Tracker -->
            <div class="col-md-6 vh-100 p-0 d-flex flex-column bg-secondary bg-opacity-10 shadow-inner">
                <!-- FIXED HEADER -->
                <div class="p-3 border-bottom border-dark bg-dark bg-opacity-25 shadow-sm sticky-top" style="z-index: 10;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center">
                            <h2 class="mb-0 me-3">⚔️ <span class="badge bg-warning text-dark" x-text="'R' + round"></span>
                            </h2>
                            <!-- Session Switcher -->
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-light dropdown-toggle border-0" type="button"
                                    data-bs-toggle="dropdown" @click="loadSessionsList()">
                                    <i class="bi bi-folder2-open me-1"></i> <span x-text="currentSession.name"></span>
                                    <span class="ms-2 badge bg-warning text-dark" x-text="currentSession.share_code"
                                        x-show="currentSession.share_code"></span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-dark shadow border-secondary">
                                    <li><a class="dropdown-item small py-1" href="#" @click.prevent="createSession()">+
                                            Nuova Sessione</a></li>
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
                                                @click.stop="deleteSession(s.id)" x-show="availableSessions.length > 1"><i
                                                    class="bi bi-x-circle"></i></button>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                            <!-- COPY LINK BUTTON -->
                            <button class="btn btn-sm btn-outline-info border-0 ms-1 px-2"
                                x-show="currentSession.share_code"
                                @click="const url = window.location.origin + '/dm/player/' + currentSession.share_code; navigator.clipboard.writeText(url); alert('Link copiato: ' + url)"
                                title="Copia link per i giocatori">
                                <i class="bi bi-link-45deg"></i>
                            </button>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="form-check form-switch me-3">
                                <input class="form-check-input" type="checkbox" id="hideDeadSwitch" x-model="hideDead">
                                <label class="form-check-label small" for="hideDeadSwitch">Nascondi Morti</label>
                            </div>
                            <button class="btn btn-sm btn-success me-1 shadow-sm" @click="startCombat()">⚔️ Inizia</button>
                            <button class="btn btn-sm btn-warning text-dark shadow-sm" @click="nextTurn()">⏩
                                Prossimo</button>
                            <button class="btn btn-sm btn-outline-danger ms-1 border-0" @click="resetCombat()"
                                title="Reset"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </div>

                <!-- SCROLLABLE CONTENT -->
                <div class="flex-grow-1 overflow-auto p-3 pt-4 custom-scrollbar">
                    <div class="list-group">
                        <template x-for="(combatant, index) in combatants" :key="combatant.instanceId">
                            <div class="list-group-item mb-2 rounded border-0 shadow-sm combatant-card p-0 overflow-hidden"
                                x-show="!hideDead || combatant.hp > 0 || combatant.type === 'player'" :class="{
                                                    'active-turn': currentTurnIndex === index,
                                                    'selected-combatant': selectedCombatant && selectedCombatant.instanceId === combatant.instanceId,
                                                    'dead-combatant bg-black bg-opacity-50': combatant.hp <= 0 && combatant.type !== 'player'
                                                }" @click="selectCombatant(combatant)">

                                <div class="d-flex align-items-center p-2">
                                    <!-- INITIATIVE -->
                                    <div class="me-3 text-center" style="width: 45px;">
                                        <div class="initiative-badge rounded-circle d-flex align-items-center justify-content-center mx-auto"
                                            style="width: 32px; height: 32px;"
                                            :class="currentTurnIndex === index ? 'bg-warning text-dark' : 'bg-dark text-white border border-secondary'">
                                            <input type="number"
                                                class="form-control form-control-sm text-center p-0 border-0 bg-transparent fw-bold"
                                                :class="currentTurnIndex === index ? 'text-dark' : 'text-white'"
                                                x-model.number="combatant.initiative" @change="sortCombat()">
                                        </div>
                                    </div>

                                    <!-- NAME & TIPO -->
                                    <div class="flex-grow-1 text-truncate">
                                        <div class="d-flex align-items-center overflow-hidden">
                                            <h5 class="mb-0 text-truncate"
                                                :class="combatant.type === 'player' ? 'text-info' : 'text-warning'"
                                                x-text="combatant.alias || combatant.name"></h5>
                                            <span class="small text-muted ms-2" x-show="combatant.alias"
                                                style="font-size: 0.7rem;">(<span x-text="combatant.name"></span>)</span>
                                        </div>
                                        <div class="small text-muted d-flex align-items-center">
                                            <span class="badge bg-dark me-1 border border-secondary">CA <span
                                                    x-text="combatant.ac"></span></span>
                                            <template x-for="status in combatant.statuses" :key="status">
                                                <span class="badge bg-danger me-1" x-text="status"></span>
                                            </template>
                                            <i class="bi bi-journal-text ms-2" x-show="combatant.personalNotes && isMaster"
                                                @click.stop="combatant.showNotesInline = !combatant.showNotesInline"
                                                :class="combatant.showNotesInline ? 'text-info' : ''"></i>
                                        </div>
                                    </div>

                                    <!-- HP CONTROLS -->
                                    <div class="d-flex align-items-center me-2 bg-dark bg-opacity-50 rounded px-2 py-1 shadow-sm"
                                        style="min-width: 220px;">
                                        <!-- HP Attuali -->
                                        <div class="d-flex align-items-center">
                                            <button class="btn btn-sm btn-outline-danger border-0 p-0 me-1"
                                                style="width: 20px;" @click="modifyHp(combatant, -1)">-</button>
                                            <input type="number"
                                                class="form-control form-control-sm text-center p-0 fw-bold border-0 bg-transparent"
                                                style="width: 35px; outline: none; box-shadow: none;"
                                                x-model.number="combatant.hp"
                                                :class="{'text-danger': combatant.hp <= 0, 'text-warning': combatant.hp > 0 && combatant.hp < (combatant.maxHp/2), 'text-success': combatant.hp >= (combatant.maxHp/2)}">

                                            <span class="text-muted mx-1">/</span>

                                            <!-- HP Massimi (Editabili) -->
                                            <input type="number"
                                                class="form-control form-control-sm text-center p-0 border-0 bg-transparent text-muted"
                                                style="width: 35px; font-size: 0.85rem;" x-model.number="combatant.maxHp"
                                                @change="saveSession()">

                                            <button class="btn btn-sm btn-outline-success border-0 p-0 ms-1"
                                                style="width: 20px;" @click="modifyHp(combatant, 1)">+</button>
                                        </div>

                                        <!-- HP Bonus / Temporanei -->
                                        <div class="ms-2 ps-2 border-start border-secondary d-flex align-items-center">
                                            <span class="small text-info me-1">+</span>
                                            <input type="number"
                                                class="form-control form-control-sm text-center p-0 border-0 bg-transparent text-info fw-bold"
                                                style="width: 30px; font-size: 0.85rem;" placeholder="0"
                                                x-model.number="combatant.tempHp" @change="saveSession()">
                                        </div>
                                    </div>

                                    <!-- AZIONI -->
                                    <div class="dropdown me-1">
                                        <button class="btn btn-sm btn-dark px-1 py-0 shadow-none border-0" type="button"
                                            data-bs-toggle="dropdown">⋮</button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow border-secondary">
                                            <li><a class="dropdown-item py-1" href="#"
                                                    @click.prevent="addStatus(combatant)">Aggiungi Stato</a></li>
                                            <li><a class="dropdown-item py-1" href="#"
                                                    @click.prevent="let a = prompt('Alias:', combatant.alias); if(a !== null) combatant.alias = a">Imposta
                                                    Alias</a></li>
                                            <li>
                                                <hr class="dropdown-divider my-1">
                                            </li>
                                            <li><a class="dropdown-item text-danger py-1" href="#"
                                                    @click.prevent="removeCombatant(index)">Rimuovi</a></li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- NOTE RAPIDE -->
                                <div x-show="combatant.showNotesInline && isMaster" x-transition
                                    class="p-2 border-top border-secondary bg-dark bg-opacity-50 small">
                                    <textarea class="form-control form-control-sm bg-transparent text-light border-0"
                                        rows="2" placeholder="Note rapide..." x-model="combatant.personalNotes"
                                        @change="saveSession()"></textarea>
                                </div>
                            </div>
                        </template>

                        <div x-show="combatants.length === 0" class="text-center text-muted py-5">
                            <i class="bi bi-shield-x fs-1 opacity-25"></i>
                            <p class="mt-2">Nessun combattente attivo</p>
                        </div>
                    </div>
                </div>
                <!-- Center column ends here, do NOT close the row yet -->

                <!-- RIGHT: Pannello Dettagli -->
                <div class="col-md-3 border-start vh-100 overflow-auto bg-dark p-3 text-light custom-scrollbar">
                    <h4 class="mb-3">📜 Dettagli</h4>
                    <template x-if="selectedCombatant">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h3 class="mb-0" x-text="selectedCombatant.alias || selectedCombatant.name"
                                        :class="selectedCombatant.type === 'player' ? 'text-info' : 'text-warning'"></h3>
                                    <p class="small text-muted" x-show="selectedCombatant.alias">Originale: <span
                                            x-text="selectedCombatant.name"></span></p>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-secondary">CA <span x-text="selectedCombatant.ac"></span></span>
                                </div>
                            </div>

                            <!-- GRIGLIA CARATTERISTICHE -->
                            <div class="row g-1 mb-3 text-center">
                                <template x-for="(val, stat) in selectedCombatant.stats.attributes" :key="stat">
                                    <div class="col-4">
                                        <div class="bg-secondary bg-opacity-25 rounded p-1 border"
                                            :class="selectedCombatant.stats.saves[stat] ? 'border-info' : 'border-secondary'">
                                            <div class="text-uppercase small fw-bold" x-text="stat"
                                                style="font-size: 0.6rem;">
                                            </div>
                                            <div class="fs-5 fw-bold" x-text="val"></div>
                                            <div class="small fw-bold text-info" style="font-size: 0.7rem;"
                                                x-text="(getStatModifier(val) >= 0 ? '+' : '') + getStatModifier(val)">
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- NOTE PERSONALI -->
                            <div class="mb-3" x-show="isMaster">
                                <label class="small text-info fw-bold mb-1"><i class="bi bi-pencil-square"></i> Note
                                    Personali
                                    DM</label>
                                <textarea class="form-control bg-secondary text-white border-0" rows="5"
                                    x-model="selectedCombatant.personalNotes"
                                    placeholder="Note per questo incontro..."></textarea>
                            </div>

                            <!-- STAT BLOCK -->
                            <div class="mb-3">
                                <h5 class="border-bottom border-secondary pb-1 text-warning small fw-bold text-uppercase"><i
                                        class="bi bi-shield-shaded"></i> Stat Block</h5>
                                <div x-html="selectedStatBlock"
                                    class="statblock-rendered p-2 bg-dark text-white rounded small shadow-sm"></div>
                            </div>
                        </div>
                    </template>
                    <div x-show="!selectedCombatant" class="text-muted text-center pt-5">
                        <i class="bi bi-info-circle fs-3"></i>
                        <p>Seleziona un combattente per i dettagli</p>
                    </div>
                </div>
            </div>

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
                                    <div class="mb-2">
                                        <label class="small">Nome</label>
                                        <input type="text"
                                            class="form-control form-control-sm bg-secondary text-white border-0"
                                            x-model="characterForm.name">
                                    </div>
                                    <div class="mb-2">
                                        <label class="small">Classe Armatura (CA)</label>
                                        <input type="number"
                                            class="form-control form-control-sm bg-secondary text-white border-0"
                                            x-model.number="characterForm.stats.ac">
                                    </div>
                                    <div class="mb-2">
                                        <label class="small"
                                            x-text="characterForm.type === 'template' ? 'Formula HP (es. 3d8+4)' : 'HP Iniziali'"></label>
                                        <input type="text"
                                            class="form-control form-control-sm bg-secondary text-white border-0"
                                            x-model="characterForm.stats.hp_formula">
                                    </div>
                                </div>
                                <!-- GRIGLIA CARATTERISTICHE -->
                                <div class="col-md-6 border-start border-secondary">
                                    <label class="small mb-1 d-block text-center">Caratteristiche e Tiri Salvezza</label>
                                    <div class="row g-2">
                                        <template x-for="(val, stat) in characterForm.stats.attributes" :key="stat">
                                            <div class="col-4 text-center">
                                                <div class="text-uppercase small fw-bold" x-text="stat"></div>
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
                            <div class="mt-3">
                                <label class="small">Stat Block (Markdown)</label>
                                <textarea class="form-control bg-secondary text-white border-0" rows="6"
                                    x-model="characterForm.stats.notes"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-secondary">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                            <button type="button" class="btn btn-primary" @click="saveCharacter()">Salva</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MODAL: Gestione Gruppi -->
            <div class="modal fade" id="groupModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content bg-dark text-white border-secondary shadow">
                        <div class="modal-header border-secondary">
                            <h5 class="modal-title">Insieme / Gruppo</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label>Nome Gruppo (es. Il Party)</label>
                                <input type="text" class="form-control bg-secondary text-white border-0"
                                    x-model="groupForm.name">
                            </div>
                            <label class="small">Membri</label>
                            <template x-for="(member, idx) in groupForm.members" :key="idx">
                                <div class="d-flex mb-2 align-items-center">
                                    <select class="form-control form-control-sm bg-secondary text-white border-0 me-1"
                                        x-model="member.character_id">
                                        <option value="">Seleziona...</option>
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
                                    <button class="btn btn-sm btn-outline-danger border-0"
                                        @click="removeGroupMember(idx)"><i class="bi bi-trash"></i></button>
                                </div>
                            </template>
                            <button class="btn btn-sm btn-outline-info w-100" @click="addGroupMember()">+ Aggiungi
                                Membro</button>
                        </div>
                        <div class="modal-footer border-secondary">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                            <button type="button" class="btn btn-primary" @click="saveGroup()">Salva Gruppo</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SCRIPTS -->
        <script src="{{ asset('js/dm-screen.js') }}?v={{ time() }}" defer></script>
        <script src="//unpkg.com/alpinejs" defer></script>
        <style>
            .combatant-card {
                transition: all 0.2s;
                border: 1px solid #444;
            }

            .active-turn {
                border: 3px solid #ffc107 !important;
                box-shadow: 0 0 10px rgba(255, 193, 7, 0.4);
                z-index: 2;
            }

            .selected-combatant {
                border: 3px solid #0dcaf0 !important;
                box-shadow: 0 0 10px rgba(13, 202, 240, 0.4);
                z-index: 1;
            }

            .dead-combatant {
                filter: grayscale(80%);
                opacity: 0.6;
                border-color: #dc3545 !important;
            }

            .grayscale {
                filter: grayscale(80%);
            }

            .x-small {
                font-size: 0.65rem;
            }

            .statblock-rendered {
                max-height: 500px;
                overflow-y: auto;
            }

            .statblock-rendered blockquote {
                border-left: 3px solid #ddd;
                padding-left: 10px;
                color: #555;
            }

            .statblock-rendered table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 10px;
            }

            .statblock-rendered table td,
            .statblock-rendered table th {
                border: 1px solid #ddd;
                padding: 4px;
            }

            .statblock-rendered h1,
            .statblock-rendered h2,
            .statblock-rendered h3 {
                color: #822;
                border-bottom: 2px solid #822;
                margin-top: 15px;
                margin-bottom: 5px;
            }

            .nav-tabs .nav-link {
                color: #aaa;
            }

            .nav-tabs .nav-link.active {
                background-color: #333;
                color: #fff;
                border-color: #444;
            }
        </style>
@endsection
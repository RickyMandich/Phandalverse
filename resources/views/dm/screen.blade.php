@extends('layouts.app')

@section('content')
    <div class="container-fluid" x-data="dmScreen()">
        <div class="row">
            <!-- SIDEBAR: Library & Templates -->
            <div class="col-md-3 border-end vh-100 overflow-auto bg-dark p-3">
                <h4 class="mb-3 text-light">📚 Library</h4>

                <ul class="nav nav-tabs mb-3" id="libraryTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active py-1 px-2" id="templates-tab" data-bs-toggle="tab"
                            data-bs-target="#templates-pane" type="button">Monster</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link py-1 px-2" id="players-tab" data-bs-toggle="tab"
                            data-bs-target="#players-pane" type="button">Player</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link py-1 px-2" id="groups-tab" data-bs-toggle="tab"
                            data-bs-target="#groups-pane" type="button">Group</button>
                    </li>
                </ul>

                <div class="tab-content text-light">
                    <!-- TEMPLATES -->
                    <div class="tab-pane fade show active" id="templates-pane">
                        <button class="btn btn-sm btn-success w-100 mb-2" @click="openCharacterModal('template')">+ New
                            Monster</button>
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
                                            @click="addToCombat(char)">Add</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- PLAYERS -->
                    <div class="tab-pane fade" id="players-pane">
                        <button class="btn btn-sm btn-info w-100 mb-2" @click="openCharacterModal('player')">+ New
                            Player</button>
                        <div class="list-group list-group-flush">
                            <template x-for="char in players" :key="char.id">
                                <div
                                    class="list-group-item list-group-item-action bg-dark border-secondary p-2 d-flex justify-content-between align-items-center">
                                    <span class="text-light" x-text="char.name"></span>
                                    <div>
                                        <button class="btn btn-sm btn-outline-secondary border-0 me-1"
                                            @click="openCharacterModal('player', char)"><i
                                                class="bi bi-pencil"></i></button>
                                        <button class="btn btn-sm btn-primary py-0" @click="addToCombat(char)">Add</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- GROUPS -->
                    <div class="tab-pane fade" id="groups-pane">
                        <button class="btn btn-sm btn-warning w-100 mb-2 text-dark" @click="openGroupModal()">+ New
                            Group</button>
                        <div class="list-group list-group-flush">
                            <template x-for="group in groups" :key="group.id">
                                <div
                                    class="list-group-item list-group-item-action bg-dark border-secondary p-2 d-flex justify-content-between align-items-center">
                                    <span class="text-light" x-text="group.name"></span>
                                    <div>
                                        <button class="btn btn-sm btn-outline-secondary border-0 me-1"
                                            @click="openGroupModal(group)"><i class="bi bi-pencil"></i></button>
                                        <button class="btn btn-sm btn-warning py-0 text-dark"
                                            @click="addGroupToCombat(group)">Add Group</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CENTER: Combat Tracker -->
            <div class="col-md-6 vh-100 overflow-auto p-3 main-combat-area bg-secondary bg-opacity-10">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="mb-0">⚔️ Tracker <span class="badge bg-dark" x-text="'R' + round"></span></h2>
                    <div class="d-flex align-items-center">
                        <div class="form-check form-switch me-3">
                            <input class="form-check-input" type="checkbox" id="hideDeadSwitch" x-model="hideDead">
                            <label class="form-check-label small" for="hideDeadSwitch">Hide Dead</label>
                        </div>
                        <button class="btn btn-sm btn-warning me-2 text-dark" @click="nextTurn()">⏩ Next Turn</button>
                        <button class="btn btn-sm btn-danger" @click="resetCombat()">Reset</button>
                    </div>
                </div>

                <div class="combat-list">
                    <template x-for="(combatant, index) in combatants" :key="combatant.instanceId">
                        <div class="card mb-2 combatant-card"
                            x-show="!hideDead || combatant.hp > 0 || combatant.type === 'player'" :class="{
                                    'active-turn': currentTurnIndex === index, 
                                    'selected-combatant': selectedCombatant && selectedCombatant.instanceId === combatant.instanceId,
                                    'dead-combatant': combatant.hp <= 0 && combatant.type !== 'player'
                                }" :id="'combatant-' + combatant.instanceId">
                            <div class="card-body p-2">
                                <div class="d-flex align-items-center">
                                    <!-- INITIATIVE -->
                                    <div class="me-3 text-center" style="width: 50px;">
                                        <label class="small text-muted" style="font-size: 0.7rem;">Init</label>
                                        <input type="number" class="form-control form-control-sm text-center p-0"
                                            x-model.number="combatant.initiative" @change="sortCombat()"
                                            style="font-weight: bold;">
                                    </div>

                                    <!-- NAME & TYPE -->
                                    <div class="flex-grow-1" @click="selectCombatant(combatant)" style="cursor: pointer;">
                                        <div class="fw-bold d-flex align-items-center">
                                            <span x-text="combatant.alias || combatant.name"
                                                :class="combatant.type === 'player' ? 'text-info' : ''"></span>
                                            <span class="badge bg-secondary ms-2" x-show="combatant.enemyCount"
                                                x-text="'#' + combatant.enemyCount"></span>
                                            <span class="small text-muted ms-2" x-show="combatant.alias"
                                                style="font-size: 0.7rem;">(<span x-text="combatant.name"></span>)</span>
                                        </div>
                                        <div class="small text-muted d-flex align-items-center">
                                            <span class="badge bg-dark me-1 border border-secondary">AC <span
                                                    x-text="combatant.ac"></span></span>
                                            <template x-for="status in combatant.statuses" :key="status">
                                                <span class="badge bg-danger me-1" x-text="status"></span>
                                            </template>
                                            <!-- ICONS -->
                                            <i class="bi bi-journal-text ms-2" x-show="combatant.personalNotes"
                                                @click.stop="combatant.showNotesInline = !combatant.showNotesInline"
                                                :class="combatant.showNotesInline ? 'text-info' : ''"></i>
                                        </div>
                                    </div>

                                    <!-- HP CONTROLS -->
                                    <div class="d-flex align-items-center me-2" style="width: 140px;">
                                        <button class="btn btn-sm btn-outline-danger px-1 py-0"
                                            @click="modifyHp(combatant, -1)">-</button>
                                        <input type="number"
                                            class="form-control form-control-sm text-center mx-1 p-0 fw-bold border-0 bg-transparent"
                                            x-model.number="combatant.hp"
                                            :class="{'text-danger': combatant.hp <= 0, 'text-warning': combatant.hp > 0 && combatant.hp < (combatant.maxHp/2)}">
                                        <span class="text-muted small">/<span x-text="combatant.maxHp"></span></span>
                                        <button class="btn btn-sm btn-outline-success px-1 py-0"
                                            @click="modifyHp(combatant, 1)">+</button>
                                    </div>

                                    <!-- ACTIONS -->
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-dark px-1 py-0" type="button"
                                            data-bs-toggle="dropdown">⋮</button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow">
                                            <li><a class="dropdown-item py-1" href="#"
                                                    @click.prevent="addStatus(combatant)">Add Status</a></li>
                                            <li><a class="dropdown-item py-1" href="#"
                                                    @click.prevent="let a = prompt('Alias:', combatant.alias); if(a !== null) combatant.alias = a">Set
                                                    Alias</a></li>
                                            <li>
                                                <hr class="dropdown-divider my-1">
                                            </li>
                                            <li><a class="dropdown-item text-danger py-1" href="#"
                                                    @click.prevent="removeCombatant(index)">Remove</a></li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- INLINE NOTES -->
                                <div x-show="combatant.showNotesInline" x-transition
                                    class="mt-2 p-2 bg-dark rounded border border-secondary">
                                    <textarea class="form-control form-control-sm bg-transparent text-light border-0"
                                        rows="2" placeholder="Note rapide..." x-model="combatant.personalNotes"></textarea>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div class="text-center text-muted mt-5" x-show="combatants.length === 0">
                        <i class="bi bi-emoji-dizzy fs-1"></i>
                        <p>No combatants. Add from Library.</p>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Details Panel -->
            <div class="col-md-3 border-start vh-100 overflow-auto bg-dark p-3 text-light">
                <h4 class="mb-3">📜 Details</h4>
                <template x-if="selectedCombatant">
                    <div>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h3 class="mb-0" x-text="selectedCombatant.alias || selectedCombatant.name"
                                    :class="selectedCombatant.type === 'player' ? 'text-info' : 'text-warning'"></h3>
                                <p class="small text-muted" x-show="selectedCombatant.alias">Original: <span
                                        x-text="selectedCombatant.name"></span></p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-secondary">AC <span x-text="selectedCombatant.ac"></span></span>
                            </div>
                        </div>

                        <!-- ATTRIBUTES GRID -->
                        <div class="row g-1 mb-3 text-center">
                            <template x-for="(val, stat) in selectedCombatant.stats.attributes" :key="stat">
                                <div class="col-4">
                                    <div class="bg-secondary bg-opacity-25 rounded p-1 border"
                                        :class="selectedCombatant.stats.saves[stat] ? 'border-info' : 'border-secondary'">
                                        <div class="text-uppercase small fw-bold" x-text="stat" style="font-size: 0.6rem;">
                                        </div>
                                        <div class="fs-5 fw-bold" x-text="val"></div>
                                        <div class="small fw-bold text-info" style="font-size: 0.7rem;"
                                            x-text="(getStatModifier(val) >= 0 ? '+' : '') + getStatModifier(val)"></div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- PERSONAL NOTES -->
                        <div class="mb-3">
                            <label class="small text-info fw-bold mb-1"><i class="bi bi-pencil-square"></i> Personal DM
                                Notes</label>
                            <textarea class="form-control bg-secondary text-white border-0" rows="5"
                                x-model="selectedCombatant.personalNotes"
                                placeholder="Write encounter-specific notes here..."></textarea>
                        </div>

                        <!-- STAT BLOCK (FROM TEMPLATE) -->
                        <div class="mb-3">
                            <h5 class="border-bottom border-secondary pb-1 text-warning small fw-bold text-uppercase"><i
                                    class="bi bi-shield-shaded"></i> Stat Block</h5>
                            <div x-html="selectedStatBlock"
                                class="statblock-rendered p-2 bg-light text-dark rounded small shadow-sm"></div>
                        </div>
                    </div>
                </template>
                <div x-show="!selectedCombatant" class="text-muted text-center pt-5">
                    <i class="bi bi-info-circle fs-3"></i>
                    <p>Select a combatant to see details</p>
                </div>
            </div>
        </div>

        <!-- MODAL: Add/Edit Character -->
        <div class="modal fade" id="characterModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content bg-dark text-white border-secondary shadow">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title" x-text="modalMode === 'create' ? 'Create' : 'Edit'"></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label class="small">Name</label>
                                    <input type="text" class="form-control form-control-sm bg-secondary text-white border-0"
                                        x-model="characterForm.name">
                                </div>
                                <div class="mb-2">
                                    <label class="small">AC</label>
                                    <input type="number"
                                        class="form-control form-control-sm bg-secondary text-white border-0"
                                        x-model.number="characterForm.stats.ac">
                                </div>
                                <div class="mb-2">
                                    <label class="small"
                                        x-text="characterForm.type === 'template' ? 'HP Formula (3d8+4)' : 'Default HP'"></label>
                                    <input type="text" class="form-control form-control-sm bg-secondary text-white border-0"
                                        x-model="characterForm.stats.hp_formula">
                                </div>
                            </div>
                            <!-- STATS GRID -->
                            <div class="col-md-6 border-start border-secondary">
                                <label class="small mb-1 d-block text-center">Attributes & Saves</label>
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
                                                <label class="form-check-label x-small">Save</label>
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
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" @click="saveCharacter()">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: Group Management -->
        <div class="modal fade" id="groupModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content bg-dark text-white border-secondary shadow">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title">Ensemble / Group</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Group Name (e.g. The Brave Ones)</label>
                            <input type="text" class="form-control bg-secondary text-white border-0"
                                x-model="groupForm.name">
                        </div>
                        <label class="small">Members</label>
                        <template x-for="(member, idx) in groupForm.members" :key="idx">
                            <div class="d-flex mb-2 align-items-center">
                                <select class="form-control form-control-sm bg-secondary text-white border-0 me-1"
                                    x-model="member.character_id">
                                    <option value="">Select...</option>
                                    <optgroup label="Players">
                                        <template x-for="p in players" :key="p.id">
                                            <option :value="p.id" x-text="p.name"></option>
                                        </template>
                                    </optgroup>
                                    <optgroup label="Monsters">
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
                        <button class="btn btn-sm btn-outline-info w-100" @click="addGroupMember()">+ Add Member</button>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" @click="saveGroup()">Save Group</button>
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
    </style>
@endsection
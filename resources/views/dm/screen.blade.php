@extends('layouts.app')

@section('content')
    <div class="container-fluid" x-data="dmScreen()" x-init="init()">
        <div class="row">
            <!-- SIDEBAR: Library & Templates -->
            <div class="col-md-3 border-end vh-100 overflow-auto bg-dark text-light p-3">
                <h4 class="mb-3">📚 Library</h4>

                <ul class="nav nav-tabs mb-3" id="libraryTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="templates-tab" data-bs-toggle="tab"
                            data-bs-target="#templates-pane" type="button">Templates</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="players-tab" data-bs-toggle="tab" data-bs-target="#players-pane"
                            type="button">Players</button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- TEMPLATES (Monsters) -->
                    <div class="tab-pane fade show active" id="templates-pane">
                        <button class="btn btn-sm btn-success w-100 mb-2" @click="openCharacterModal('template')">+ New
                            Template</button>
                        <div class="list-group list-group-flush">
                            <template x-for="char in templates" :key="char.id">
                                <div
                                    class="list-group-item list-group-item-action bg-dark text-light border-secondary d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong x-text="char.name"></strong>
                                        <div class="small text-muted" x-text="'HP: ' + (char.stats.hp_formula || 'N/A')">
                                        </div>
                                    </div>
                                    <div>
                                        <button class="btn btn-sm btn-primary py-0" @click="addToCombat(char)"
                                            title="Add to Combat"><i class="bi bi-plus-lg"></i></button>
                                        <button class="btn btn-sm btn-outline-secondary py-0" @click="editCharacter(char)"
                                            title="Edit"><i class="bi bi-pencil"></i></button>
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
                                    class="list-group-item list-group-item-action bg-dark text-light border-secondary d-flex justify-content-between align-items-center">
                                    <span x-text="char.name"></span>
                                    <div>
                                        <button class="btn btn-sm btn-primary py-0" @click="addToCombat(char)"><i
                                                class="bi bi-plus-lg"></i></button>
                                        <button class="btn btn-sm btn-outline-secondary py-0"
                                            @click="editCharacter(char)"><i class="bi bi-pencil"></i></button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CENTER: Combat Tracker -->
            <div class="col-md-6 vh-100 overflow-auto p-3 main-combat-area">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>⚔️ Combat Tracker <span class="badge bg-secondary" x-text="'Round ' + round"></span></h2>
                    <div>
                        <button class="btn btn-warning me-2" @click="nextTurn()">⏩ Next Turn</button>
                        <button class="btn btn-danger" @click="resetCombat()">Reset</button>
                    </div>
                </div>

                <div class="combat-list">
                    <template x-for="(combatant, index) in combatants" :key="combatant.instanceId">
                        <div class="card mb-2"
                            :class="{'border-warning': currentTurnIndex === index, 'opacity-75': combatant.hp <= 0}"
                            :id="'combatant-' + combatant.instanceId">
                            <div class="card-body p-2 d-flex align-items-center">
                                <!-- INITIATIVE -->
                                <div class="me-3 text-center" style="width: 50px;">
                                    <label class="small text-muted">Init</label>
                                    <input type="number" class="form-control form-control-sm text-center"
                                        x-model.number="combatant.initiative" @change="sortCombat()"
                                        style="font-weight: bold;">
                                </div>

                                <!-- NAME & TYPE -->
                                <div class="flex-grow-1" @click="selectCombatant(combatant)" style="cursor: pointer;">
                                    <div class="fw-bold fs-5">
                                        <span x-text="combatant.name"></span>
                                        <span class="badge bg-secondary ms-2" x-show="combatant.enemyCount"
                                            x-text="'#' + combatant.enemyCount"></span>
                                    </div>
                                    <div class="small">
                                        <span class="badge bg-success me-1" x-show="combatant.ac">AC: <span
                                                x-text="combatant.ac"></span></span>
                                        <!-- STATUS BADGES -->
                                        <template x-for="status in combatant.statuses" :key="status">
                                            <span class="badge bg-danger me-1" x-text="status"></span>
                                        </template>
                                    </div>
                                </div>

                                <!-- HP CONTROLS -->
                                <div class="d-flex align-items-center" style="width: 150px;">
                                    <button class="btn btn-sm btn-outline-danger"
                                        @click="modifyHp(combatant, -1)">-</button>
                                    <input type="number" class="form-control form-control-sm text-center mx-1"
                                        x-model.number="combatant.hp"
                                        :class="{'text-danger': combatant.hp < (combatant.maxHp/2), 'text-success': combatant.hp >= (combatant.maxHp/2)}">
                                    <span class="text-muted small me-2">/<span x-text="combatant.maxHp"></span></span>
                                    <button class="btn btn-sm btn-outline-success"
                                        @click="modifyHp(combatant, 1)">+</button>
                                </div>

                                <!-- ACTIONS -->
                                <div class="ms-2 dropdown">
                                    <button class="btn btn-sm btn-light dropdown-toggle" type="button"
                                        data-bs-toggle="dropdown">⋮</button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#" @click.prevent="addStatus(combatant)">Add
                                                Status</a></li>
                                        <li>
                                            <hr class="dropdown-divider">
                                        </li>
                                        <li><a class="dropdown-item text-danger" href="#"
                                                @click.prevent="removeCombatant(index)">Remove</a></li>
                                    </ul>
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
            <div class="col-md-3 border-start vh-100 overflow-auto bg-light p-3">
                <h4 class="mb-3">📜 Details</h4>
                <div x-show="selectedCombatant">
                    <h3 x-text="selectedCombatant?.name"></h3>

                    <div class="mb-3">
                        <label>Notes / Temp HP / Conditions</label>
                        <textarea class="form-control" rows="5" x-model="selectedCombatant?.notes"></textarea>
                    </div>

                    <div class="mb-3">
                        <h5>Stat Block</h5>
                        <div x-html="selectedStatBlock" class="p-2 border bg-white rounded shadow-sm"></div>
                    </div>
                </div>
                <div x-show="!selectedCombatant" class="text-muted">
                    Select a combatant to view details.
                </div>
            </div>
        </div>

        <!-- MODAL: Add/Edit Character -->
        <div class="modal fade" id="characterModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" x-text="modalMode === 'create' ? 'Create Character' : 'Edit Character'">
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Name</label>
                            <input type="text" class="form-control" x-model="characterForm.name">
                        </div>
                        <div class="mb-3">
                            <label>Type</label>
                            <select class="form-control" x-model="characterForm.type">
                                <option value="template">Monster/Template</option>
                                <option value="player">Player Character</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>AC (Armor Class)</label>
                            <input type="number" class="form-control" x-model.number="characterForm.stats.ac">
                        </div>
                        <div class="mb-3" x-show="characterForm.type === 'template'">
                            <label>HP Formula (e.g. 3d8+4)</label>
                            <input type="text" class="form-control" x-model="characterForm.stats.hp_formula">
                        </div>
                        <div class="mb-3" x-show="characterForm.type === 'player'">
                            <label>Default HP</label>
                            <input type="number" class="form-control" x-model.number="characterForm.stats.hp_formula">
                        </div>
                        <div class="mb-3">
                            <label>Dexterity Modifier (for Initiative)</label>
                            <input type="number" class="form-control" x-model.number="characterForm.stats.dex_mod">
                        </div>
                        <div class="mb-3">
                            <label>Stat Block (Markdown)</label>
                            <textarea class="form-control" rows="5" x-model="characterForm.stats.notes"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" @click="saveCharacter()">Save</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script src="//unpkg.com/alpinejs" defer></script>
    <script src="{{ asset('js/dm-screen.js') }}"></script>
    <style>
        .master-block {
            border-left: 3px solid gold;
            padding-left: 10px;
            background: rgba(255, 215, 0, 0.1);
        }
    </style>
@endsection
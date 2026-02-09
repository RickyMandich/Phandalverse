@extends('layouts.app')

@section('title', 'Schermo del Master - Powerfail System 5.3')

@section('include')
    <script src="//unpkg.com/alpinejs" defer></script>
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid" x-data="powerfailMaster()">
        <!-- Header -->
        <div class="row bg-dark text-white p-2 align-items-center mb-3 border-bottom border-secondary">
            <div class="col-md-6 d-flex align-items-center">
                <h4 class="m-0 me-3 text-warning"><i class="bi bi-shield-shaded"></i> <span x-text="scene.name"></span></h4>
                <div x-show="currentSessionCode" class="badge bg-secondary font-monospace p-2">
                    CODE: <a :href="'/dm/player/' + currentSessionCode" target="_blank"
                        class="text-info text-decoration-none" title="Apri Player View">
                        <span x-text="currentSessionCode"></span> <i class="bi bi-box-arrow-up-right small"></i>
                    </a>
                </div>
                <!-- SHARE BUTTON -->
                <button class="btn btn-sm btn-outline-info ms-2 border-0" x-show="currentSessionCode"
                    @click="copySessionLink()" title="Copia link per i giocatori">
                    <i class="bi bi-share-fill"></i>
                </button>
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-sm btn-outline-success me-2 border-0" @click="startNewSession()"
                    title="Nuova Sessione">
                    <i class="bi bi-plus-circle"></i> Nuovo
                </button>
                <span x-show="saveTimer" class="small text-muted me-2"><i class="bi bi-cloud-upload"></i>...</span>
                <button class="btn btn-sm btn-outline-warning" @click="loadSessionModal = true">
                    <i class="bi bi-folder2-open"></i> Carica
                </button>
                <!-- Utility Modal (Alert/Prompt) -->
                <div class="modal fade" x-show="utilModal.show" x-cloak :class="{ 'show d-block': utilModal.show }"
                    style="background: rgba(0,0,0,0.8); z-index: 1060;">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content bg-dark text-white border-primary shadow-lg">
                            <div class="modal-header border-primary">
                                <h5 class="modal-title" x-text="utilModal.title"></h5>
                            </div>
                            <div class="modal-body">
                                <p x-text="utilModal.message"></p>
                                <template x-if="utilModal.type === 'prompt'">
                                    <input type="text" class="form-control bg-secondary text-white border-primary"
                                        x-model="utilModal.inputValue"
                                        @keyup.enter="utilModal.onConfirm(utilModal.inputValue); utilModal.show = false">
                                </template>
                            </div>
                            <div class="modal-footer border-primary">
                                <button type="button" class="btn btn-outline-secondary"
                                    @click="utilModal.show = false">Annulla</button>
                                <button type="button" class="btn btn-primary"
                                    @click="utilModal.onConfirm(utilModal.inputValue); utilModal.show = false">Conferma</button>
                            </div>
                        </div>
                    </div>
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
                    @include('dm.partials.dice_roller')
                </div>


                <!-- CENTER COLUMN: Attori in Scena -->
                <div class="col-md-9">
                    <div class="card bg-dark text-white border-secondary h-100">
                        <div
                            class="card-header bg-secondary bg-opacity-25 fw-bold d-flex justify-content-between align-items-center">
                            <span>👥 Attori in Scena</span>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-success" @click="nextTurn()"
                                    x-show="scene.state === 'combat'">
                                    <i class="bi bi-play-fill"></i> Prossimo Turno
                                </button>
                                <button class="btn btn-sm btn-outline-warning" @click="sortActorsByInitiative()"
                                    title="Ordina per Iniziativa">
                                    <i class="bi bi-sort-numeric-down"></i> Ordina
                                </button>
                                <button class="btn btn-sm btn-outline-info" @click="addActor('pc')">+ PG</button>
                                <button class="btn btn-sm btn-outline-danger" @click="bestiaryModal = true">+ Aggiungi
                                    Nemico</button>
                                <button class="btn btn-sm btn-outline-secondary" @click="addActor('minion')">+
                                    Minion</button>
                            </div>
                        </div>
                        <div class="card-body p-2 overflow-auto" style="min-height: 400px;">
                            <div class="row row-cols-1 row-cols-lg-2 row-cols-xl-3 g-2">
                                <template x-for="(actor, index) in actors" :key="actor.id">
                                    <div class="col">
                                        <div class="card bg-secondary bg-opacity-10 border-secondary h-100 position-relative"
                                            :class="{ 'border-warning shadow-sm': index === activeActorIndex && scene.state === 'combat' }">
                                            <div
                                                class="card-header p-1 d-flex justify-content-between align-items-center bg-secondary bg-opacity-25">
                                                <div class="d-flex align-items-center">
                                                    <span x-show="index === activeActorIndex && scene.state === 'combat'"
                                                        class="me-1 text-warning">▶</span>
                                                    <input type="text"
                                                        class="form-control form-control-sm bg-transparent border-0 text-white fw-bold p-0"
                                                        x-model="actor.name" style="width: auto;">
                                                </div>
                                                <div>
                                                    <button class="btn btn-sm btn-link p-0 me-1 text-white"
                                                        @click="cloneActor(actor)" title="Clona">
                                                        <i class="bi bi-files"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-link p-0 text-danger"
                                                        @click="removeActor(index)">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="card-body p-2">
                                                <!-- Damage & Wound State (Inabion System) -->
                                                <div class="mb-2">
                                                    <label class="x-small text-muted">Danni Attuali</label>
                                                    <input type="number"
                                                        class="form-control form-control-sm bg-dark text-white border-secondary"
                                                        x-model.number="actor.damage" @input="checkHpTriggers(actor)"
                                                        min="0">

                                                    <!-- Wound State Display -->
                                                    <div class="mt-1 p-1 rounded text-center x-small"
                                                        :class="getDamageState(actor).class">
                                                        <strong x-text="getDamageState(actor).state"></strong>
                                                        <span x-show="getDamageState(actor).penalty"
                                                            x-text="' - ' + getDamageState(actor).penalty"></span>
                                                    </div>

                                                    <!-- Thresholds Reference (for PCs) -->
                                                    <template x-if="actor.type === 'pc'">
                                                        <div class="x-small text-muted mt-1">
                                                            Soglie: <span x-text="(actor.stats.vig || 2) * 1"></span> /
                                                            <span x-text="(actor.stats.vig || 2) * 2"></span> /
                                                            <span x-text="(actor.stats.vig || 2) * 3"></span> /
                                                            <span x-text="(actor.stats.vig || 2) * 4"></span> /
                                                            <span x-text="(actor.stats.vig || 2) * 5"></span>
                                                        </div>
                                                    </template>
                                                </div>

                                                <!-- Fatigue, Traumas & Defenses -->
                                                <div class="row g-2 mb-2">
                                                    <template x-if="actor.type !== 'minion'">
                                                        <div class="col-6">
                                                            <label class="x-small text-muted">Fatica</label>
                                                            <input type="number"
                                                                class="form-control form-control-sm bg-dark text-white border-secondary"
                                                                x-model.number="actor.fatigue">
                                                        </div>
                                                    </template>
                                                    <div class="col-6">
                                                        <label class="x-small text-muted">Traumi</label>
                                                        <input type="number"
                                                            class="form-control form-control-sm bg-dark text-white border-secondary"
                                                            x-model.number="actor.traumas">
                                                    </div>
                                                </div>

                                                <!-- Defenses & Combat Stats -->
                                                <div class="mb-2">
                                                    <label class="x-small text-muted d-block mb-1">Difese</label>
                                                    <div class="d-flex gap-1">
                                                        <div class="flex-grow-1">
                                                            <div class="x-small text-muted text-center"
                                                                style="font-size: 0.6rem;">
                                                                MIS</div>
                                                            <input type="number"
                                                                class="form-control form-control-sm bg-dark text-white border-secondary text-center px-0"
                                                                x-model.number="actor.defenses.mischia">
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="x-small text-muted text-center"
                                                                style="font-size: 0.6rem;">
                                                                TIR</div>
                                                            <input type="number"
                                                                class="form-control form-control-sm bg-dark text-white border-secondary text-center px-0"
                                                                x-model.number="actor.defenses.tiro">
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="x-small text-muted text-center"
                                                                style="font-size: 0.6rem;">
                                                                MAG</div>
                                                            <input type="number"
                                                                class="form-control form-control-sm bg-dark text-white border-secondary text-center px-0"
                                                                x-model.number="actor.defenses.magia">
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Enemy Combat Stats (ATK, DMG, FAT, ACTIONS) -->
                                                <div x-show="actor.type !== 'pc'" class="row g-1 mb-2">
                                                    <div class="col-3" title="Dadi Attacco">
                                                        <div
                                                            class="bg-danger bg-opacity-10 p-1 rounded border border-danger border-opacity-10 text-center">
                                                            <div class="x-small text-danger" style="font-size: 0.6rem;">ATK
                                                            </div>
                                                            <input type="number"
                                                                class="form-control form-control-sm bg-transparent text-white border-0 text-center p-0 fw-bold"
                                                                x-model.number="actor.attackDice">
                                                        </div>
                                                    </div>
                                                    <div class="col-3" title="Danno Inflitto">
                                                        <div
                                                            class="bg-warning bg-opacity-10 p-1 rounded border border-warning border-opacity-10 text-center">
                                                            <div class="x-small text-warning" style="font-size: 0.6rem;">DMG
                                                            </div>
                                                            <input type="number"
                                                                class="form-control form-control-sm bg-transparent text-white border-0 text-center p-0 fw-bold"
                                                                x-model.number="actor.damageInflicted">
                                                        </div>
                                                    </div>
                                                    <div class="col-3" title="Costo Fatica">
                                                        <div
                                                            class="bg-info bg-opacity-10 p-1 rounded border border-info border-opacity-10 text-center">
                                                            <div class="x-small text-info" style="font-size: 0.6rem;">FAT
                                                            </div>
                                                            <input type="number"
                                                                class="form-control form-control-sm bg-transparent text-white border-0 text-center p-0 fw-bold"
                                                                x-model.number="actor.fatigueCost">
                                                        </div>
                                                    </div>
                                                    <div class="col-3" title="Azioni Rimaste">
                                                        <div
                                                            class="bg-primary bg-opacity-10 p-1 rounded border border-primary border-opacity-25 text-center">
                                                            <div class="x-small text-primary fw-bold"
                                                                style="font-size: 0.6rem;">
                                                                AZIONI</div>
                                                            <input type="number"
                                                                class="form-control form-control-sm bg-transparent text-white border-0 text-center p-0 fw-bold underline-0"
                                                                x-model.number="actor.actions">
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- PC Actions Display -->
                                                <div x-show="actor.type === 'pc'" class="mb-2">
                                                    <div
                                                        class="bg-primary bg-opacity-10 p-1 rounded border border-primary border-opacity-10 d-flex justify-content-between align-items-center px-2">
                                                        <span class="x-small text-primary fw-bold text-uppercase">Azioni
                                                            Disponibili</span>
                                                        <input type="number"
                                                            class="form-control form-control-sm bg-transparent text-white border-0 text-end p-0 fw-bold"
                                                            x-model.number="actor.actions" style="width: 40px;">
                                                    </div>
                                                </div>

                                                <!-- Minion Triggers -->
                                                <div x-show="actor.type === 'minion' && actor.statusMessage"
                                                    class="alert alert-danger p-1 x-small mb-2">
                                                    <i class="bi bi-exclamation-triangle"></i> <span
                                                        x-text="actor.statusMessage"></span>
                                                </div>

                                                <!-- Initiative -->
                                                <div class="mb-2">
                                                    <label class="x-small text-muted">Iniziativa</label>
                                                    <div class="input-group input-group-sm">
                                                        <input type="number"
                                                            class="form-control bg-dark text-white border-secondary"
                                                            x-model.number="actor.initiative">
                                                        <button class="btn btn-sm btn-outline-warning"
                                                            @click="rollInitiative(actor)"
                                                            title="Tira 1d10 + caratteristica">
                                                            🎲
                                                        </button>
                                                    </div>
                                                </div>

                                                <!-- Attributes & Skills -->
                                                <template x-if="actor.type !== 'minion'">
                                                    <div class="mb-2">
                                                        <div
                                                            class="d-flex justify-content-between bg-secondary bg-opacity-25 p-1 rounded mb-1">
                                                            <span class="x-small fw-bold">CARATTERISTICHE</span>
                                                        </div>

                                                        <!-- PC: 6 Inabion Characteristics -->
                                                        <template x-if="actor.type === 'pc'">
                                                            <div>
                                                                <!-- Fisiche -->
                                                                <div class="x-small text-muted mb-1">Fisiche</div>
                                                                <div class="d-flex gap-1 mb-2">
                                                                    <input type="number"
                                                                        class="form-control form-control-sm bg-dark text-white border-secondary"
                                                                        placeholder="VIG" x-model.number="actor.stats.vig"
                                                                        title="Vigore">
                                                                    <input type="number"
                                                                        class="form-control form-control-sm bg-dark text-white border-secondary"
                                                                        placeholder="DES" x-model.number="actor.stats.des"
                                                                        title="Destrezza">
                                                                </div>

                                                                <!-- Mente -->
                                                                <div class="x-small text-muted mb-1">Mente</div>
                                                                <div class="d-flex gap-1 mb-2">
                                                                    <input type="number"
                                                                        class="form-control form-control-sm bg-dark text-white border-secondary"
                                                                        placeholder="INT" x-model.number="actor.stats.int"
                                                                        title="Intuito">
                                                                    <input type="number"
                                                                        class="form-control form-control-sm bg-dark text-white border-secondary"
                                                                        placeholder="RAG" x-model.number="actor.stats.rag"
                                                                        title="Ragione">
                                                                </div>

                                                                <!-- Anima -->
                                                                <div class="x-small text-muted mb-1">Anima</div>
                                                                <div class="d-flex gap-1 mb-2">
                                                                    <input type="number"
                                                                        class="form-control form-control-sm bg-dark text-white border-secondary"
                                                                        placeholder="CAR" x-model.number="actor.stats.car"
                                                                        title="Carisma">
                                                                    <input type="number"
                                                                        class="form-control form-control-sm bg-dark text-white border-secondary"
                                                                        placeholder="SPI" x-model.number="actor.stats.spi"
                                                                        title="Spirito">
                                                                </div>
                                                            </div>
                                                        </template>

                                                        <!-- Enemy: 3 Stats -->
                                                        <template x-if="actor.type === 'enemy'">
                                                            <div class="d-flex gap-1 mb-2">
                                                                <input type="number"
                                                                    class="form-control form-control-sm bg-dark text-white border-secondary"
                                                                    placeholder="FIS" x-model.number="actor.stats.fis"
                                                                    title="Fisico">
                                                                <input type="number"
                                                                    class="form-control form-control-sm bg-dark text-white border-secondary"
                                                                    placeholder="MEN" x-model.number="actor.stats.men"
                                                                    title="Mentale">
                                                                <input type="number"
                                                                    class="form-control form-control-sm bg-dark text-white border-secondary"
                                                                    placeholder="SOC" x-model.number="actor.stats.soc"
                                                                    title="Sociale">
                                                            </div>
                                                        </template>

                                                        <div
                                                            class="d-flex justify-content-between bg-secondary bg-opacity-25 p-1 rounded mb-1">
                                                            <span class="x-small fw-bold">ABILITÀ VELOCI</span>
                                                        </div>
                                                        <div class="d-flex gap-1">
                                                            <button @click="quickRoll(actor, 'mischia')"
                                                                class="btn btn-outline-danger btn-sm flex-grow-1 x-small"
                                                                title="Attacco Mischia (VIG)">MIS</button>
                                                            <button @click="quickRoll(actor, 'tiro')"
                                                                class="btn btn-outline-warning btn-sm flex-grow-1 x-small"
                                                                title="Attacco Distanza (DES)">TIR</button>
                                                            <button @click="quickRoll(actor, 'tecnica')"
                                                                class="btn btn-outline-info btn-sm flex-grow-1 x-small"
                                                                title="Prova Tecnica (INT)">TEC</button>
                                                            <button @click="quickRoll(actor, 'magia')"
                                                                class="btn btn-outline-success btn-sm flex-grow-1 x-small"
                                                                title="Prova Magia (SPI)">MAG</button>
                                                        </div>
                                                    </div>
                                                </template>

                                                <!-- Notes -->
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

                <!-- MODALS -->

                <!-- Load Session Modal -->
                <div class="modal fade" x-show="loadSessionModal" x-cloak :class="{ 'show d-block': loadSessionModal }"
                    id="loadSessionModal" tabindex="-1" role="dialog" aria-hidden="true"
                    @click.self="loadSessionModal = false" :style="loadSessionModal ? 'background: rgba(0,0,0,0.8);' : ''">
                    <div class="modal-dialog">
                        <div class="modal-content bg-dark text-white border-secondary">
                            <div class="modal-header border-secondary">
                                <h5 class="modal-title">Carica Sessione Powerfail</h5>
                                <button type="button" class="btn-close btn-close-white"
                                    @click="loadSessionModal = false"></button>
                            </div>
                            <div class="modal-body">
                                <!-- New Session Option -->
                                <button class="btn btn-success w-100 mb-2 py-2 fw-bold"
                                    @click="loadSessionModal = false; startNewSession();">
                                    <i class="bi bi-plus-circle"></i> Inizia Nuova Sessione
                                </button>

                                <div class="border-top border-secondary my-3"></div>

                                <button class="btn btn-sm btn-outline-light mb-3 w-100" @click="loadSessionsList()">🔄
                                    Aggiorna
                                    Lista</button>
                                <div class="list-group">
                                    <template x-for="s in availableSessions" :key="s.id">
                                        <button
                                            class="list-group-item list-group-item-action bg-dark text-white border-secondary d-flex justify-content-between"
                                            @click="loadSession(s.id)">
                                            <span x-text="s.name"></span>
                                            <span class="small text-muted"
                                                x-text="new Date(s.updated_at).toLocaleString()"></span>
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

                <!-- Bestiary Modal -->
                <div class="modal fade" x-show="bestiaryModal" x-cloak :class="{ 'show d-block': bestiaryModal }"
                    id="bestiaryModal" tabindex="-1" role="dialog" aria-hidden="true" @click.self="bestiaryModal = false"
                    :style="bestiaryModal ? 'background: rgba(0,0,0,0.8);' : ''">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content bg-dark text-white border-secondary">
                            <div class="modal-header border-secondary">
                                <h5 class="modal-title">Archivio Mostri & NPC</h5>
                                <button type="button" class="btn-close btn-close-white"
                                    @click="bestiaryModal = false"></button>
                            </div>
                            <div class="modal-body">
                                <!-- LIST VIEW -->
                                <div x-show="bestiaryView === 'list'">
                                    <button class="btn btn-primary w-100 mb-3" @click="openMonsterForm()">
                                        <i class="bi bi-plus-circle"></i> Nuovo Mostro
                                    </button>

                                    <div class="row g-3">
                                        <template x-for="monster in bestiary" :key="monster.id">
                                            <div class="col-md-6">
                                                <div
                                                    class="card bg-secondary bg-opacity-10 border-secondary h-100 position-relative">

                                                    <!-- Edit/Delete Controls -->
                                                    <div class="position-absolute top-0 end-0 p-1">
                                                        <button class="btn btn-sm btn-link text-warning p-0 me-1"
                                                            @click="openMonsterForm(monster)">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-link text-danger p-0"
                                                            @click="deleteMonster(monster.id)">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>

                                                    <div
                                                        class="card-body d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <h6 class="mb-0 fw-bold" x-text="monster.name"></h6>
                                                            <div class="small text-muted">
                                                                <span x-text="monster.stats?.fis || 2"></span>/<span
                                                                    x-text="monster.stats?.men || 2"></span>/<span
                                                                    x-text="monster.stats?.soc || 2"></span> |
                                                                <span class="badge bg-dark border border-secondary"
                                                                    x-text="(monster.stats?.type || 'enemy').toUpperCase()"></span>
                                                            </div>
                                                        </div>
                                                        <button class="btn btn-sm btn-outline-success"
                                                            @click="addFromBestiary(monster)">
                                                            <i class="bi bi-plus-lg"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                        <div x-show="bestiary.length === 0" class="text-center text-muted col-12">
                                            Nessun mostro in archivio.
                                        </div>
                                    </div>
                                </div>

                                <!-- FORM VIEW -->
                                <div x-show="bestiaryView === 'form'">
                                    <h6 class="mb-3 border-bottom border-secondary pb-2">
                                        <span x-text="editingMonsterId ? 'Modifica Mostro' : 'Nuovo Mostro'"></span>
                                    </h6>
                                    <div class="row g-2">
                                        <div class="col-8">
                                            <label class="small text-muted">Nome</label>
                                            <input type="text" class="form-control bg-dark text-white border-secondary"
                                                x-model="monsterForm.name">
                                        </div>
                                        <div class="col-4">
                                            <label class="small text-muted">Tipo</label>
                                            <select class="form-select bg-dark text-white border-secondary"
                                                x-model="monsterForm.type">
                                                <option value="enemy">Nemico</option>
                                                <option value="minion">Minion</option>
                                            </select>
                                        </div>

                                        <div class="col-4">
                                            <label class="small text-muted">FIS</label>
                                            <input type="number" class="form-control bg-dark text-white border-secondary"
                                                x-model.number="monsterForm.fis">
                                        </div>
                                        <div class="col-4">
                                            <label class="small text-muted">MEN</label>
                                            <input type="number" class="form-control bg-dark text-white border-secondary"
                                                x-model.number="monsterForm.men">
                                        </div>
                                        <div class="col-4">
                                            <label class="small text-muted">SOC</label>
                                            <input type="number" class="form-control bg-dark text-white border-secondary"
                                                x-model.number="monsterForm.soc">
                                        </div>

                                        <!-- Combat Stats -->
                                        <div class="col-4">
                                            <label class="small text-danger">ATK (Dadi)</label>
                                            <input type="number"
                                                class="form-control bg-dark text-white border-danger border-opacity-25"
                                                x-model.number="monsterForm.attackDice">
                                        </div>
                                        <div class="col-4">
                                            <label class="small text-warning">DMG (Danni)</label>
                                            <input type="number"
                                                class="form-control bg-dark text-white border-warning border-opacity-25"
                                                x-model.number="monsterForm.damageInflicted">
                                        </div>
                                        <div class="col-4">
                                            <label class="small text-info">FAT (Costo)</label>
                                            <input type="number"
                                                class="form-control bg-dark text-white border-info border-opacity-25"
                                                x-model.number="monsterForm.fatigueCost">
                                        </div>

                                        <!-- Defenses -->
                                        <div class="col-4">
                                            <label class="small text-muted">Def. MIS</label>
                                            <input type="number" class="form-control bg-dark text-white border-secondary"
                                                x-model.number="monsterForm.def_m">
                                        </div>
                                        <div class="col-4">
                                            <label class="small text-muted">Def. TIR</label>
                                            <input type="number" class="form-control bg-dark text-white border-secondary"
                                                x-model.number="monsterForm.def_t">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="small text-muted">Note</label>
                                <textarea class="form-control bg-dark text-white border-secondary" rows="3"
                                    x-model="monsterForm.notes"></textarea>
                            </div>
                        </div>
                        <div class="mt-3 d-flex justify-content-end gap-2">
                            <button class="btn btn-secondary" @click="bestiaryView = 'list'">Annulla</button>
                            <button class="btn btn-success" @click="saveMonster()">Salva</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <!-- Utility Modal (Alert/Prompt) -->
    <div class="modal fade" x-show="utilModal.show" x-cloak :class="{ 'show d-block': utilModal.show }"
        style="background: rgba(0,0,0,0.8); z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white border-primary shadow-lg">
                <div class="modal-header border-primary">
                    <h5 class="modal-title" x-text="utilModal.title"></h5>
                </div>
                <div class="modal-body">
                    <p x-text="utilModal.message"></p>
                    <template x-if="utilModal.type === 'prompt'">
                        <input type="text" class="form-control bg-secondary text-white border-primary"
                            x-model="utilModal.inputValue"
                            @keyup.enter="utilModal.onConfirm(utilModal.inputValue); utilModal.show = false">
                    </template>
                </div>
                <div class="modal-footer border-primary">
                    <button type="button" class="btn btn-outline-secondary" @click="utilModal.show = false">Annulla</button>
                    <button type="button" class="btn btn-primary"
                        @click="utilModal.onConfirm(utilModal.inputValue); utilModal.show = false">Conferma</button>
                </div>
            </div>
        </div>
    </div>
    </div>

    <script>
        console.log("Defining powerfailMaster...");
        window.powerfailMaster = function () {
            console.log("Initializing Powerfail Component...");
            return {
                scene: {
                    name: 'Nuova Scena',
                    state: 'narrative',
                    notes: ''
                },
                actors: [],
                activeActorIndex: 0, // Track whose turn it is
                diceLog: [],
                currentSessionId: null,
                currentSessionCode: null, // Public share code
                loadSessionModal: false,
                bestiaryModal: false,
                availableSessions: [],

                ...window.powerfailDiceLogic('Master'),

                // Utility Modal (Replacement for alert/prompt)

                bestiary: [],
                bestiaryView: 'list', // 'list' or 'form'
                editingMonsterId: null,
                monsterForm: {
                    name: '',
                    type: 'enemy',
                    fis: 2, men: 2, soc: 2,
                    def_m: 0, def_t: 0, def_a: 0,
                    attackDice: 1,
                    damageInflicted: 2,
                    fatigueCost: 1,
                    notes: ''
                },

                saveTimer: null,

                init() {
                    this.loadSessionsList();
                    this.loadBestiary();

                    // SET UP AUTO-SAVE (Watcher style)
                    this.$watch('actors', () => this.triggerAutoSave());
                    this.$watch('scene', () => this.triggerAutoSave(), { deep: true });
                    this.$watch('activeActorIndex', () => this.triggerAutoSave());
                    this.$watch('diceLog', () => this.triggerAutoSave());

                    // Auto-open load session modal on start
                    setTimeout(() => this.loadSessionModal = true, 500);
                },

                triggerAutoSave() {
                    if (!this.currentSessionId) return;
                    if (this.saveTimer) clearTimeout(this.saveTimer);
                    this.saveTimer = setTimeout(() => {
                        this.executeSave(true).then(() => {
                            this.saveTimer = null;
                        });
                    }, 500); // 500ms
                },

                sanitizeActor(actor) {
                    // Ensure all required fields exist with defaults
                    if (!actor.defenses) actor.defenses = { mischia: 0, tiro: 0, magia: 0 };
                    if (actor.damage === undefined) actor.damage = 0;
                    if (actor.fatigue === undefined) actor.fatigue = 0;
                    if (actor.traumas === undefined) actor.traumas = 0;
                    if (actor.initiative === undefined) actor.initiative = 0;
                    if (actor.actions === undefined) {
                        const base = actor.type === 'pc' ? (actor.stats.des || 2) : (actor.stats.fis || 2);
                        actor.actions = base;
                    }
                    if (actor.attackDice === undefined) actor.attackDice = 1;
                    if (actor.damageInflicted === undefined) actor.damageInflicted = 2;
                    if (actor.fatigueCost === undefined) actor.fatigueCost = 1;
                    if (!actor.stats) actor.stats = actor.type === 'pc' ? { vig: 2, des: 2, int: 2, rag: 2, car: 2, spi: 2 } : { fis: 2, men: 2, soc: 2 };
                    return actor;
                },

                // UI UTILITIES
                showAlert(title, message) {
                    this.utilModal = {
                        show: true,
                        type: 'alert',
                        title: title,
                        message: message,
                        onConfirm: () => { }
                    };
                },

                showPrompt(title, message, defaultValue, callback) {
                    this.utilModal = {
                        show: true,
                        type: 'prompt',
                        title: title,
                        message: message,
                        inputValue: defaultValue || '',
                        onConfirm: callback
                    };
                },

                copySessionLink() {
                    if (!this.currentSessionCode) return;
                    const link = `${window.location.origin}/dm/player/${this.currentSessionCode}`;
                    navigator.clipboard.writeText(link).then(() => {
                        this.showAlert("Link Copiato!", "Il link per i giocatori è stato copiato negli appunti.");
                    });
                },

                // --- BESTIARY MANAGEMENT ---

                async loadBestiary() {
                    try {
                        const response = await fetch('/dm/api/characters');
                        if (!response.ok) return;
                        const data = await response.json();
                        // Filter for Powerfail system templates
                        this.bestiary = data.filter(c => c.type === 'template' && c.stats?.system === 'powerfail');
                    } catch (e) {
                        console.error("Error loading bestiary", e);
                    }
                },

                openMonsterForm(monster = null) {
                    this.bestiaryView = 'form';
                    if (monster) {
                        this.editingMonsterId = monster.id;
                        // Map stats back to form
                        const s = monster.stats || {};
                        this.monsterForm = {
                            name: monster.name,
                            type: s.type || 'enemy',
                            fis: s.fis || 2,
                            men: s.men || 2,
                            soc: s.soc || 2,
                            def_m: s.def_m || 0,
                            def_t: s.def_t || 0,
                            def_a: s.def_a || 0,
                            attackDice: s.attackDice || 1,
                            damageInflicted: s.damageInflicted || 2,
                            fatigueCost: s.fatigueCost || 1,
                            notes: s.notes || ''
                        };
                    } else {
                        this.editingMonsterId = null;
                        this.monsterForm = {
                            name: '',
                            type: 'enemy',
                            fis: 2, men: 2, soc: 2,
                            def_m: 0, def_t: 0, def_a: 0,
                            attackDice: 1,
                            damageInflicted: 2,
                            fatigueCost: 1,
                            notes: ''
                        };
                    }
                },

                async saveMonster() {
                    const payload = {
                        name: this.monsterForm.name,
                        type: 'template',
                        stats: {
                            system: 'powerfail',
                            type: this.monsterForm.type,
                            fis: this.monsterForm.fis,
                            men: this.monsterForm.men,
                            soc: this.monsterForm.soc,
                            def_m: this.monsterForm.def_m || 0,
                            def_t: this.monsterForm.def_t || 0,
                            def_a: this.monsterForm.def_a || 0,
                            attackDice: this.monsterForm.attackDice || 1,
                            damageInflicted: this.monsterForm.damageInflicted || 2,
                            fatigueCost: this.monsterForm.fatigueCost || 1,
                            notes: this.monsterForm.notes
                        }
                    };

                    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token };

                    try {
                        let url = '/dm/api/characters';
                        let method = 'POST';

                        if (this.editingMonsterId) {
                            url += `/${this.editingMonsterId}`;
                            method = 'PATCH';
                        }

                        const res = await fetch(url, { method, headers, body: JSON.stringify(payload) });
                        if (res.ok) {
                            await this.loadBestiary();
                            this.bestiaryView = 'list';
                            this.showAlert("Successo", "Mostro salvato correttamente.");
                        } else {
                            this.showAlert("Errore", "Errore salvataggio mostro");
                        }
                    } catch (e) {
                        console.error(e);
                        this.showAlert("Errore", "Errore di connessione");
                    }
                },

                async deleteMonster(id) {
                    this.showPrompt("Conferma", "Scrivi 'ELIMINA' per confermare l'eliminazione:", "", async (val) => {
                        if (val !== 'ELIMINA') return;
                        try {
                            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                            const res = await fetch(`/dm/api/characters/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': token } });
                            if (res.ok) {
                                await this.loadBestiary();
                                this.showAlert("Eliminato", "Mostro rimosso dall'archivio.");
                            } else {
                                this.showAlert("Errore", "Errore eliminazione");
                            }
                        } catch (e) { console.error(e); }
                    });
                },

                // --- ACTOR MANAGEMENT ---

                addActor(type) {
                    const isPc = type === 'pc';
                    this.actors.push({
                        id: Date.now(),
                        type: type,
                        name: isPc ? 'Nuovo Giocatore' : (type === 'minion' ? 'Minion' : 'Nemico'),
                        damage: 0,
                        fatigue: 0,
                        traumas: 0,
                        initiative: 0,
                        defenses: { mischia: 0, tiro: 0, magia: 0 },
                        // Combat Stats
                        attackDice: 1,
                        damageInflicted: 2,
                        fatigueCost: 1,
                        // PCs use Inabion 6 characteristics (Fisiche, Mente, Anima), others use simplified 3
                        stats: isPc ? {
                            vig: 2, des: 2,          // Fisiche
                            int: 2, rag: 2,          // Mente
                            car: 2, spi: 2           // Anima
                        } : {
                            fis: 2, men: 2, soc: 2
                        },
                        notes: '',
                        statusMessage: ''
                    });
                },

                nextTurn() {
                    if (this.actors.length === 0) return;
                    this.activeActorIndex = (this.activeActorIndex + 1) % this.actors.length;
                },

                addFromBestiary(monster) {
                    const s = monster.stats || {};
                    const type = s.type === 'minion' ? 'minion' : 'enemy';

                    // Bestiary is mostly enemies, so use 3 stats or 6 if needed.
                    // For now, assume bestiary = simplified stats unless 'pc' (rare for bestiary).
                    // If complex NPC, we might need 6 stats, but current bestiary logic supports simplified.

                    this.actors.push({
                        id: Date.now(),
                        type: type,
                        name: monster.name,
                        damage: 0,  // Current damage points
                        fatigue: 0,
                        traumas: 0,
                        initiative: 0,
                        defenses: {
                            mischia: s.def_m || 0,
                            tiro: s.def_t || 0,
                            magia: s.def_a || 0
                        },
                        attackDice: s.attackDice || 1,
                        damageInflicted: s.damageInflicted || 2,
                        fatigueCost: s.fatigueCost || 1,
                        stats: {
                            fis: s.fis || 2,
                            men: s.men || 2,
                            soc: s.soc || 2
                        },
                        notes: s.notes || '',
                        statusMessage: ''
                    });
                    this.bestiaryModal = false;
                },

                cloneActor(actor) {
                    this.showPrompt("Clona Personaggio", `Quante copie di ${actor.name} vuoi creare?`, "1", (val) => {
                        const num = parseInt(val);
                        if (!num || num < 1) return;

                        for (let i = 0; i < num; i++) {
                            const clone = JSON.parse(JSON.stringify(actor));
                            clone.id = Date.now() + i;
                            clone.name = `${actor.name} ${i + 1}`;
                            this.actors.push(this.sanitizeActor(clone));
                        }
                    });
                },

                removeActor(index) {
                    const actor = this.actors[index];
                    this.showPrompt("Conferma", `Vuoi rimuovere ${actor.name}? Scrivi 'RIMUOVI' per confermare:`, "", (val) => {
                        if (val === 'RIMUOVI') {
                            this.actors.splice(index, 1);
                        }
                    });
                },

                getDamageState(actor) {
                    const vig = actor.stats.vig || actor.stats.fis || 2;
                    const base = vig * 2;
                    const dmg = actor.damage || 0;

                    if (dmg <= base * 1) return { state: 'Illeso', penalty: '', penaltyVal: 0, class: 'text-success' };
                    if (dmg <= base * 2) return { state: 'Malconcio', penalty: '', penaltyVal: 0, class: 'text-warning' };
                    if (dmg <= base * 3) return { state: 'Contuso', penalty: '-1 azione', penaltyVal: 1, class: 'text-warning' };
                    if (dmg <= base * 4) return { state: 'Colpito', penalty: '-2 azioni', penaltyVal: 2, class: 'text-danger' };
                    if (dmg <= base * 5) return { state: 'Ferito', penalty: 'Trauma, -3 az.', penaltyVal: 3, class: 'text-danger fw-bold' };
                    return { state: 'Inerme', penalty: 'Inerme', penaltyVal: 5, class: 'bg-danger text-white' };
                },

                checkHpTriggers(actor) {
                    // 1. Minion Message logic
                    if (actor.type === 'minion') {
                        const threshold = (actor.stats.fis || 2) * 2;
                        if (actor.damage >= threshold * 1) {
                            actor.statusMessage = 'ELIMINATO';
                        } else if (actor.damage >= threshold * 0.5) {
                            actor.statusMessage = 'FERITO - Metà Azioni';
                        } else {
                            actor.statusMessage = '';
                        }
                    }

                    // 2. Automatic Action Scaling (FIS or DES - Base Penalties)
                    const baseActions = actor.type === 'pc' ? (actor.stats.des || 2) : (actor.stats.fis || 2);
                    const state = this.getDamageState(actor);
                    let finalActions = baseActions - state.penaltyVal;

                    // Minion wounding rule (halve)
                    if (actor.type === 'minion' && actor.damage >= ((actor.stats.fis || 2) * 2) * 0.5) {
                        finalActions = Math.floor(finalActions / 2);
                    }

                    actor.actions = Math.max(0, finalActions);
                },



                quickRoll(actor, skillName) {
                    this.roller.level = 3;

                    if (actor.type === 'pc') {
                        // Inabion system: VIG for melee, DES for ranged, INT for technical
                        const s = actor.stats || {};
                        let pool = 2;
                        switch (skillName) {
                            case 'mischia': pool = (s.vig || 2); break;  // Vigore for melee
                            case 'tiro': pool = (s.des || 2); break;     // Destrezza for ranged
                            case 'tecnica': pool = (s.int || 2); break;  // Intuito for technical
                            case 'magia': pool = (s.spi || 2); break;
                            default: pool = 2;
                        }
                        this.roller.pool = Math.max(1, pool);
                    } else {
                        // Minion/Enemy logic (3 stats)
                        const s = actor.stats || {};
                        const fis = s.fis || 2;
                        const men = s.men || 2;
                        const soc = s.soc || 2;

                        // Heuristic mapping
                        if (skillName === 'mischia') this.roller.pool = fis;
                        else if (skillName === 'tiro') this.roller.pool = Math.max(1, fis - 1); // slightly less
                        else if (skillName === 'tecnica') this.roller.pool = men;
                        else if (skillName === 'magia') this.roller.pool = men;
                        else this.roller.pool = soc;
                    }

                    this.rollDice();
                },

                rollInitiative(actor) {
                    const d10 = Math.floor(Math.random() * 10) + 1;
                    let modifier = 0;

                    if (actor.type === 'pc') {
                        modifier = actor.stats.des || 0;
                    } else {
                        modifier = actor.stats.fis || 0;
                    }

                    actor.initiative = d10 + modifier;
                },

                sortActorsByInitiative() {
                    this.actors.sort((a, b) => (b.initiative || 0) - (a.initiative || 0));
                    this.activeActorIndex = 0; // Reset turn to top after sorting
                },

                startNewSession() {
                    this.showPrompt("Nuova Sessione", "Inserisci il nome per la nuova sessione:", "Nuova Scena", (name) => {
                        console.log("Creating new session with name:", name);
                        if (name) {
                            this.currentSessionId = null;
                            this.currentSessionCode = null;
                            this.scene = { name: name, state: 'narrative', notes: '' };
                            this.actors = [];
                            this.diceLog = [];
                            this.activeActorIndex = 0;

                            // Resetting to ensure no stale data
                            this.executeSave(false);
                        }
                    });
                },

                // --- SESSION PERSISTENCE ---

                async saveSession() {
                    this.executeSave(false);
                },

                async executeSave(silent = false) {
                    try {
                        const nameToSave = this.scene.name || 'Nuova Scena';
                        console.log("Saving Session. ID:", this.currentSessionId, "Name:", nameToSave);

                        const payload = {
                            name: nameToSave,
                            system: 'powerfail',
                            data: {
                                scene: this.scene,
                                actors: this.actors,
                                activeActorIndex: this.activeActorIndex,
                                diceLog: this.diceLog
                            }
                        };

                        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                        const headers = {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest'
                        };

                        console.log("Saving Session via FETCH...");
                        console.log("Endpoint:", !this.currentSessionId ? '/dm/api/sessions (POST)' : `/dm/api/sessions/${this.currentSessionId} (PATCH)`);
                        console.log("Payload Name:", nameToSave);

                        let response;
                        if (!this.currentSessionId) {
                            response = await fetch('/dm/api/sessions', { method: 'POST', headers, body: JSON.stringify(payload) });
                        } else {
                            response = await fetch(`/dm/api/sessions/${this.currentSessionId}`, { method: 'PATCH', headers, body: JSON.stringify(payload) });
                        }

                        console.info("Server Response Status:", response.status);

                        if (response.ok) {
                            const data = await response.json();
                            console.info("Save Successful. New ID?", data.id);
                            if (data.id) this.currentSessionId = data.id;
                            if (data.share_code) this.currentSessionCode = data.share_code;
                            if (!silent) this.showAlert("Salvato", "Sessione salvata: " + (data.name || nameToSave));
                            this.loadSessionsList();
                        } else {
                            const errData = await response.json();
                            console.error("Save failed:", errData);
                            if (!silent) this.showAlert("Errore", "Errore salvataggio: " + (errData.error || response.statusText));
                        }
                    } catch (e) {
                        console.error("Critical Save Error:", e);
                        if (!silent) this.showAlert("Errore", 'Errore Connessione: ' + e);
                    }
                },

                async loadSessionsList() {
                    try {
                        const response = await fetch('/dm/api/sessions');
                        if (!response.ok) {
                            console.warn('API Sessioni non raggiungibile o errore server.');
                            return;
                        }
                        const sessions = await response.json();
                        if (Array.isArray(sessions)) {
                            this.availableSessions = sessions.filter(s => s.system === 'powerfail');
                        } else {
                            console.error('Formato risposta sessioni non valido:', sessions);
                            this.availableSessions = [];
                        }
                    } catch (e) {
                        console.error("Errore init sessioni:", e);
                        this.availableSessions = [];
                    }
                },

                async loadSession(id) {
                    try {
                        const response = await fetch(`/dm/api/sessions/${id}`);
                        const session = await response.json();

                        this.currentSessionId = session.id;
                        this.currentSessionCode = session.share_code;
                        this.scene = session.data.scene || this.scene;

                        // SANITIZE ALL ACTORS UPON LOADING
                        const loadedActors = session.data.actors || [];
                        this.actors = loadedActors.map(a => this.sanitizeActor(a));

                        this.activeActorIndex = session.data.activeActorIndex || 0;
                        this.diceLog = session.data.diceLog || [];

                        this.loadSessionModal = false;
                        this.showAlert("Sessione Caricata", "Benvenuto in " + session.name);
                    } catch (e) {
                        console.error("Load failed", e);
                        this.showAlert("Errore", "Errore caricamento sessione.");
                    }
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
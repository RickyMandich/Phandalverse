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
                        <button class="btn btn-sm btn-link text-white p-0" @click="diceLog = []">Pulisci Log</button>
                    </div>
                    <div class="card-body">
                        <!-- Inputs -->
                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <label class="small text-muted">Livello (1-5)</label>
                                <input type="number" class="form-control form-control-sm text-center fw-bold"
                                    x-model.number="roller.level" min="1" max="5">
                            </div>
                            <div class="col-4">
                                <label class="small text-muted">Dadi (Pool)</label>
                                <input type="number" class="form-control form-control-sm text-center fw-bold"
                                    x-model.number="roller.pool" min="1">
                            </div>
                            <div class="col-4 d-flex align-items-end">
                                <button class="btn btn-sm btn-light w-100 fw-bold" @click="rollDice()">TIRA</button>
                            </div>
                        </div>

                        <!-- Active Roll Area -->
                        <div x-show="roller.active"
                            class="border border-info rounded p-2 mb-3 bg-secondary bg-opacity-10 position-relative">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-info text-dark">Lancio in Corso</span>
                                <button class="btn btn-xs btn-success" @click="finalizeRoll()">✅ Conferma</button>
                            </div>

                            <!-- Generations -->
                            <div class="d-flex flex-column gap-2">
                                <template x-for="(gen, genIdx) in roller.generations" :key="genIdx">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-muted x-small">W<span x-text="genIdx + 1"></span></span>
                                        <div class="d-flex flex-wrap gap-1">
                                            <template x-for="(die, dieIdx) in gen" :key="dieIdx">
                                                <button
                                                    class="btn btn-sm p-0 d-flex justify-content-center align-items-center rounded-circle"
                                                    style="width: 28px; height: 28px;" :class="{
                                                                'btn-success': isSuccess(die),
                                                                'btn-danger': die === 1,
                                                                'btn-secondary': !isSuccess(die) && die !== 1,
                                                                'border border-warning border-2': die === 10
                                                            }" @click="rerollDie(genIdx, dieIdx)" :disabled="!canReroll()"
                                                    :title="canReroll() ? 'Clicca per ritirare (Livello 3+)' : ''">
                                                    <span class="fw-bold" x-text="die"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- Controls: Reroll & Explosions -->
                            <div class="mt-2 border-top border-secondary pt-2">
                                <div class="x-small text-muted mb-1" x-show="roller.level >= 3 && !roller.rerollUsed">
                                    💡 Livello 3+: Clicca su un dado per ritirarlo.
                                </div>
                                <div class="x-small text-muted mb-1" x-show="roller.reraollUsed">
                                    ⚠️ Reroll utilizzato.
                                </div>

                                <!-- Explosions Prompt -->
                                <div x-show="countTens() > 0 && roller.level >= 2" class="mt-2">
                                    <div class="fw-bold text-warning small mb-1">
                                        💥 <span x-text="countTens()"></span> "10" ottenuti! Esplodi?
                                    </div>
                                    <div class="d-flex flex-wrap gap-1">
                                        <!-- Dynamic Buttons for Explosions -->
                                        <template x-for="n in (countTens() + 1)">
                                            <button class="btn btn-sm btn-dark border-secondary px-2 py-0"
                                                @click="explode(n-1)" x-text="n-1"
                                                :class="{'btn-warning': (n-1) === countTens()}">
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- History Log -->
                        <div class="border rounded p-2 bg-secondary bg-opacity-50 small custom-scrollbar"
                            style="max-height: 250px; overflow-y: auto;">
                            <template x-for="(log, idx) in diceLog" :key="idx">
                                <div class="mb-2 border-bottom border-secondary border-opacity-25 pb-1">
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-bold" x-text="log.source || 'Master'"></span>
                                        <span class="text-muted" x-text="log.time"></span>
                                    </div>

                                    <!-- Log Generations -->
                                    <template x-for="(gen, genIdx) in log.generations" :key="genIdx">
                                        <div class="d-flex gap-2 align-items-center mt-1">
                                            <span class="text-muted x-small">W<span x-text="genIdx + 1"></span></span>
                                            <div class="d-flex flex-wrap gap-1">
                                                <template x-for="die in gen">
                                                    <span class="badge" :class="{
                                                                    'bg-success': (log.level >= 4 ? die >= 7 : die >= 8), 
                                                                    'bg-danger': die === 1, 
                                                                    'bg-secondary': (log.level >= 4 ? die < 7 : die < 8) && die !== 1,
                                                                    'border border-warning': die === 10
                                                                }" x-text="die"></span>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <div class="mt-1 x-small d-flex justify-content-between">
                                        <span>
                                            Successi: <span class="text-success fw-bold" x-text="log.successes"></span>
                                            <span x-show="log.failures > 0" class="text-danger ms-2">Fallimenti: <span
                                                    x-text="log.failures"></span></span>
                                        </span>
                                        <span class="badge bg-dark border border-secondary text-muted">Lv.<span
                                                x-text="log.level"></span></span>
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
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-info" @click="addActor('pc')">+ PG</button>
                            <button class="btn btn-sm btn-outline-danger" @click="bestiaryModal = true">+ Aggiungi
                                Nemico</button>
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
                                    <div>
                                        <button class="btn btn-sm btn-link p-0 me-1"
                                            :class="actor.type === 'pc' ? 'text-dark' : 'text-white'"
                                            @click="cloneActor(actor)" title="Clona Attore">
                                            <i class="bi bi-files"></i>
                                        </button>
                                        <button class="btn btn-sm btn-link p-0"
                                            :class="actor.type === 'pc' ? 'text-dark' : 'text-white'"
                                            @click="removeActor(index)">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
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

                                        <template x-if="actor.type !== 'minion'">
                                            <div class="col-6">
                                                <label class="x-small text-muted">Fatica</label>
                                                <input type="number"
                                                    class="form-control form-control-sm bg-dark text-white border-secondary"
                                                    x-model.number="actor.fatigue">
                                            </div>
                                        </template>
                                    </div>

                                    <!-- Minion Triggers -->
                                    <div x-show="actor.type === 'minion' && actor.statusMessage"
                                        class="alert alert-danger p-1 x-small mb-2">
                                        <i class="bi bi-exclamation-triangle"></i> <span
                                            x-text="actor.statusMessage"></span>
                                    </div>

                                    <!-- Attributes & Skills -->
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

        <!-- Bestiary Modal -->
        <div class="modal fade" id="bestiaryModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.8);"
            x-show="bestiaryModal" x-transition.opacity>
            <div class="modal-dialog modal-lg">
                <div class="modal-content bg-dark text-white border-secondary">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title">Archivio Mostri & NPC</h5>
                        <button type="button" class="btn-close btn-close-white" @click="bestiaryModal = false"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <template x-for="monster in bestiary" :key="monster.name">
                                <div class="col-md-6">
                                    <div class="card bg-secondary bg-opacity-10 border-secondary h-100">
                                        <div class="card-body d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0 fw-bold" x-text="monster.name"></h6>
                                                <div class="small text-muted">
                                                    HP: <span x-text="monster.hp"></span> |
                                                    <span class="badge bg-dark border border-secondary"
                                                        x-text="monster.type"></span>
                                                </div>
                                            </div>
                                            <button class="btn btn-sm btn-outline-success"
                                                @click="addFromBestiary(monster)">
                                                <i class="bi bi-plus-lg"></i> Aggiungi
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
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
                    state: 'narrative',
                    notes: ''
                },
                actors: [],
                diceLog: [],
                currentSessionId: null,
                loadSessionModal: false,
                bestiaryModal: false,
                availableSessions: [],

                roller: {
                    active: false,
                    level: 1, // 1-5
                    pool: 1,  // Number of dice
                    generations: [], // [[dice...], [dice...]]
                    rerollUsed: false
                },

                // Simple Hardcoded Bestiary
                bestiary: [
                    { name: 'Bandito', type: 'enemy', hp: 10, maxHp: 10, stats: { fis: 3, men: 2, soc: 1 }, notes: 'Armato di spada corta.' },
                    { name: 'Goblin', type: 'minion', hp: 5, maxHp: 5, stats: { fis: 2, men: 1, soc: 1 }, notes: 'Attacca in gruppo.' },
                    { name: 'Orco', type: 'enemy', hp: 20, maxHp: 20, stats: { fis: 5, men: 1, soc: 1 }, notes: 'Pelle dura (Riduzione Danni 1).' },
                    { name: 'Guardia', type: 'enemy', hp: 12, maxHp: 12, stats: { fis: 3, men: 2, soc: 2 }, notes: 'Ligio al dovere.' },
                    { name: 'Cultista', type: 'minion', hp: 6, maxHp: 6, stats: { fis: 1, men: 3, soc: 2 }, notes: 'Fanatico.' }
                ],

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

                addFromBestiary(monster) {
                    this.actors.push({
                        id: Date.now(),
                        type: monster.type,
                        name: monster.name,
                        hp: monster.hp,
                        maxHp: monster.maxHp,
                        fatigue: 0,
                        stats: JSON.parse(JSON.stringify(monster.stats)), // Deep copy
                        notes: monster.notes || '',
                        statusMessage: ''
                    });
                    this.bestiaryModal = false;
                },

                cloneActor(actor) {
                    const count = prompt(`Quante copie di ${actor.name} vuoi creare?`, "1");
                    const num = parseInt(count);
                    if (!num || num < 1) return;

                    for (let i = 0; i < num; i++) {
                        const clone = JSON.parse(JSON.stringify(actor));
                        clone.id = Date.now() + i; // Ensure unique ID
                        clone.name = `${actor.name} ${i + 1}`;
                        this.actors.push(clone);
                    }
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
                            actor.hp = 0;
                        } else if (actor.hp <= actor.maxHp / 2) {
                            actor.statusMessage = 'FERITO - -1 Azione';
                        } else {
                            actor.statusMessage = '';
                        }
                    } else if (actor.type === 'enemy') {
                        if (actor.hp <= 0) {
                            // avoid appending multiple times if edited
                            if (!actor.notes.includes('[SCONFITTO]'))
                                actor.notes += '\n[SCONFITTO]';
                        }
                    }
                },

                // --- DICE LOGIC REVISED ---

                isSuccess(die) {
                    const threshold = this.roller.level >= 4 ? 7 : 8;
                    return die >= threshold;
                },

                canReroll() {
                    return this.roller.level >= 3 && !this.roller.rerollUsed;
                },

                rollDice() {
                    if (this.roller.pool < 1) return;
                    this.roller.active = true;
                    this.roller.generations = [];
                    this.roller.rerollUsed = false;

                    // Initial Roll
                    this.performRoll(this.roller.pool);
                },

                performRoll(count) {
                    const results = [];
                    for (let i = 0; i < count; i++) {
                        results.push(Math.floor(Math.random() * 10) + 1);
                    }
                    this.roller.generations.push(results);
                },

                rerollDie(genIdx, dieIdx) {
                    if (!this.canReroll()) return;

                    this.roller.generations[genIdx][dieIdx] = Math.floor(Math.random() * 10) + 1;
                    this.roller.rerollUsed = true;
                },

                explode(count) {
                    // count is number of dice to roll (logic handled by UI buttons passing specific count)
                    // If user clicks "Explode 2", we roll 2 dice.
                    // We do NOT clear the 'pending' explosions because 
                    // the user might have more 10s in the new roll.
                    // Actually, the UI calculates ALL 10s currently on board.
                    // If I roll 2 new dice and get a 10, the countTens() increases.
                    // The UI button says "Roll X".
                    if (count > 0) {
                        this.performRoll(count);
                    } else {
                        // If user explicitly chooses "0" or "None", maybe finalize?
                        // For now, let's just do nothing or finalize.
                        // The user can click "Conferma" to finish.
                    }
                },

                countTens() {
                    // Count 10s in the Last Generation? Or ALL generations?
                    // Typically explosions happen on the *newly* rolled 10s.
                    // If I rolled 3 tens in gen 1, I explode 3.
                    // If I get 1 ten in gen 2, I explode 1.
                    // So I should only count 10s in the LAST generation?
                    // "Quando hai una abilità al livello 2 Il dieci esplode"
                    // Usually this means indefinite explosions.
                    // The UI should probably offer to roll for 10s in the LAST generation.
                    // Because previous generations were already handled.
                    if (this.roller.generations.length === 0) return 0;
                    const lastGen = this.roller.generations[this.roller.generations.length - 1];
                    return lastGen.filter(d => d === 10).length;
                },

                finalizeRoll() {
                    // Calculate totals
                    let netSuccesses = 0;
                    let failures = 0;
                    const threshold = this.roller.level >= 4 ? 7 : 8;

                    let allDice = [];
                    this.roller.generations.forEach(gen => {
                        gen.forEach(die => {
                            allDice.push(die);
                            if (die >= threshold) netSuccesses++;
                            if (die === 1) failures++;
                        });
                    });

                    // Failures cancel successes (usually? or mostly just narrative complications? In previous logic it was net)
                    // Assuming net:
                    netSuccesses = Math.max(0, netSuccesses - failures);

                    this.diceLog.unshift({
                        time: new Date().toLocaleTimeString(),
                        generations: JSON.parse(JSON.stringify(this.roller.generations)),
                        successes: netSuccesses,
                        failures: failures,
                        level: this.roller.level,
                        source: 'Master'
                    });

                    // Reset Active State
                    this.roller.active = false;
                    this.roller.generations = [];
                },

                quickRoll(actor, skillName) {
                    // Auto-set and Roll
                    // Assume Level based on skill? For now hardcode or random for demo
                    // The user said "Quando premo per tirare ... tira in automatico"
                    this.roller.level = 3; // Default or calculate
                    this.roller.pool = actor.stats.fis || 2; // Default

                    // Trigger visual roll
                    this.rollDice();
                },

                // --- SESSION PERSISTENCE ---

                async saveSession() {
                    const payload = {
                        name: this.scene.name,
                        system: 'powerfail',
                        data: {
                            scene: this.scene,
                            actors: this.actors,
                            diceLog: this.diceLog
                        }
                    };

                    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token };

                    try {
                        let response;
                        if (!this.currentSessionId) {
                            const name = prompt("Nome della nuova sessione:", this.scene.name);
                            if (!name) return;
                            this.scene.name = name;
                            payload.name = name;

                            response = await fetch('/dm/api/sessions', { method: 'POST', headers, body: JSON.stringify(payload) });
                        } else {
                            response = await fetch(`/dm/api/sessions/${this.currentSessionId}`, { method: 'PATCH', headers, body: JSON.stringify(payload) });
                        }

                        if (response.ok) {
                            const data = await response.json();
                            if (data.id) this.currentSessionId = data.id; // update ID if new
                            alert('Sessione salvata!');
                            this.loadSessionsList(); // Refresh list
                        } else {
                            alert('Errore server nel salvataggio.');
                        }
                    } catch (e) {
                        alert('Errore: ' + e);
                    }
                },

                async loadSessionsList() {
                    const response = await fetch('/dm/api/sessions');
                    const sessions = await response.json();
                    this.availableSessions = sessions.filter(s => s.system === 'powerfail');
                },

                async loadSession(id) {
                    try {
                        const response = await fetch(`/dm/api/sessions/${id}`);
                        const session = await response.json();

                        this.currentSessionId = session.id;
                        this.scene = session.data.scene || this.scene;
                        this.actors = session.data.actors || [];
                        this.diceLog = session.data.diceLog || [];

                        this.loadSessionModal = false;
                        console.log("Loaded Session:", this.currentSessionId);
                    } catch (e) {
                        console.error("Load failed", e);
                        alert("Errore caricamento sessione.");
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
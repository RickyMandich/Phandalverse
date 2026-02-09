<!-- TOOLS: Dice Roller Component -->
<div class="card bg-dark text-white border-secondary">
    <div class="card-header bg-secondary bg-opacity-25 fw-bold d-flex justify-content-between">
        <span>🎲 Lancio Dadi (d10)</span>
        <div>
            <button class="btn btn-sm btn-link text-info p-0 me-2" @click="helpModal = true" title="Guida al Sistema">
                <i class="bi bi-question-circle"></i>
            </button>
            <button class="btn btn-sm btn-link text-white p-0" @click="diceLog = []">Pulisci Log</button>
        </div>
    </div>
    <div class="card-body">
        <!-- Inputs -->
        <div class="row g-2 mb-3">
            <div class="col-4">
                <label class="small text-muted">Livello (1-5)</label>
                <input type="number"
                    class="form-control form-control-sm text-center fw-bold text-white bg-dark border-secondary"
                    x-model.number="roller.level" min="1" max="5">
            </div>
            <div class="col-4">
                <label class="small text-muted">Dadi (Pool)</label>
                <input type="number"
                    class="form-control form-control-sm text-center fw-bold text-white bg-dark border-secondary"
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
                <div class="x-small text-muted mb-1" x-show="roller.rerollUsed">
                    ⚠️ Reroll utilizzato.
                </div>

                <!-- Explosions Prompt -->
                <div x-show="countTens() > 0 && roller.level >= 2" class="mt-2">
                    <div class="fw-bold text-warning small mb-1">
                        💥 <span x-text="countTens()"></span> "10" ottenuti! Esplodi?
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        <template x-for="n in (countTens() + 1)">
                            <button class="btn btn-sm btn-dark border-secondary px-2 py-0" @click="explode(n-1)"
                                x-text="n-1" :class="{'btn-warning': (n-1) === countTens()}">
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
                        <span class="fw-bold" x-text="log.source || 'Player'"></span>
                        <span class="text-muted" x-text="log.time"></span>
                    </div>

                    <template x-for="(gen, genIdx) in log.generations" :key="genIdx">
                        <div class="d-flex gap-2 align-items-center mb-1 mt-1">
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
            <div x-show="diceLog.length === 0" class="text-muted text-center italic">Nessun lancio effettuato.</div>
        </div>
    </div>
</div>

<!-- Help Modal (Included in partial to avoid repetition) -->
<div class="modal fade" :class="{ 'show d-block': helpModal }" id="helpModal" tabindex="-1"
    style="background: rgba(0,0,0,0.8); z-index: 1100;" role="dialog" aria-hidden="true"
    @click.self="helpModal = false">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-white border-info">
            <div class="modal-header border-info">
                <h5 class="modal-title"><i class="bi bi-info-circle"></i> Regole Powerfail System</h5>
                <button type="button" class="btn-close btn-close-white" @click="helpModal = false"></button>
            </div>
            <div class="modal-body small">
                <ul class="list-unstyled">
                    <li class="mb-2"><strong>🎲 Base:</strong> Tira una pool di d10.</li>
                    <li class="mb-2"><strong>✅ Successo:</strong> Risultato 8+ (o 7+ a Lv.4+).</li>
                    <li class="mb-2"><strong>❌ Fallimento:</strong> Risultato 1. Cancella 1 successo.</li>
                    <li class="mb-2"><strong>💥 Esplosioni (Lv.2+):</strong> Ogni 10 permette di tirare un dado extra.
                    </li>
                    <li class="mb-2"><strong>🔄 Reroll (Lv.3+):</strong> Puoi ritirare 1 dado dal tavolo, ma SOLO dopo
                        aver risolto tutte le esplosioni pendenti.</li>
                </ul>
                <div class="alert alert-info py-1 mb-0">
                    Premi <strong>TIRA</strong> per iniziare. Se ottieni dei 10, appariranno i tasti per esplodere.
                    Alla fine, <strong>Conferma</strong> per salvare nel log.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Shared Dice Roller Logic for Alpine.js
    window.powerfailDiceLogic = (initialSource = 'Player') => ({
        helpModal: false,
        roller: {
            active: false,
            level: 1,
            pool: 1,
            generations: [],
            rerollUsed: false
        },
        // Note: diceLog must be defined in the parent component or here if standalone.
        // We'll assume the parent provides it if it wants to sync, or we initialize it.

        isSuccess(die) {
            const threshold = this.roller.level >= 4 ? 7 : 8;
            return die >= threshold;
        },

        canReroll() {
            return this.roller.level >= 3 && !this.roller.rerollUsed && this.countTens() === 0;
        },

        rollDice() {
            if (this.roller.pool < 1) return;
            this.roller.active = true;
            this.roller.generations = [];
            this.roller.rerollUsed = false;
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
            if (count > 0) this.performRoll(count);
        },

        countTens() {
            if (this.roller.generations.length === 0) return 0;
            const lastGen = this.roller.generations[this.roller.generations.length - 1];
            return lastGen.filter(d => d === 10).length;
        },

        finalizeRoll() {
            let netSuccesses = 0;
            let failures = 0;
            const threshold = this.roller.level >= 4 ? 7 : 8;

            this.roller.generations.forEach(gen => {
                gen.forEach(die => {
                    if (die >= threshold) netSuccesses++;
                    if (die === 1) failures++;
                });
            });

            netSuccesses = Math.max(0, netSuccesses - failures);

            // Add to log
            const logEntry = {
                time: new Date().toLocaleTimeString(),
                generations: JSON.parse(JSON.stringify(this.roller.generations)),
                successes: netSuccesses,
                failures: failures,
                level: this.roller.level,
                source: initialSource
            };

            if (this.diceLog) {
                this.diceLog.unshift(logEntry);
            }

            this.roller.active = false;
            this.roller.generations = [];

            // If parent has a trigger for auto-save, it will be caught by watcher on diceLog
        }
    });
</script>
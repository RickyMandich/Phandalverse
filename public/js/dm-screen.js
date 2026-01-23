
document.addEventListener('alpine:init', () => {
    Alpine.data('dmScreen', () => ({
        templates: [],
        players: [],
        combatants: [],
        round: 1,
        currentTurnIndex: 0,

        // Modal & Selection
        selectedCombatant: null,
        selectedStatBlock: '',
        characterModal: null,
        modalMode: 'create', // or 'edit'
        characterForm: {
            id: null,
            name: '',
            type: 'template',
            stats: {
                ac: 10,
                hp_formula: '1d8',
                dex_mod: 0,
                notes: '',
                saving_throws: {}
            }
        },

        init() {
            this.loadCharacters();
            this.loadSession();

            // Initialization might happen before bootstrap is ready or modal is in DOM
            this.$nextTick(() => {
                const modalEl = document.getElementById('characterModal');
                if (modalEl && window.bootstrap) {
                    this.characterModal = new window.bootstrap.Modal(modalEl);
                }
            });

            // Auto-save every 30 seconds
            setInterval(() => {
                this.saveSession();
            }, 30000);
        },

        async loadCharacters() {
            const response = await fetch('/dm/api/characters');
            const data = await response.json();
            this.templates = data.filter(c => c.type === 'template');
            this.players = data.filter(c => c.type === 'player');
        },

        openCharacterModal(type) {
            this.modalMode = 'create';
            this.characterForm = {
                id: null,
                name: '',
                type: type,
                stats: {
                    ac: 10,
                    hp_formula: type === 'template' ? '1d8' : '10',
                    dex_mod: 0,
                    notes: '',
                    saving_throws: {}
                }
            };
            this.showModal();
        },

        editCharacter(char) {
            this.modalMode = 'edit';
            this.characterForm = {
                id: char.id,
                name: char.name,
                type: char.type,
                stats: typeof char.stats === 'string' ? JSON.parse(char.stats) : JSON.parse(JSON.stringify(char.stats))
            };
            this.showModal();
        },

        showModal() {
            if (!this.characterModal) {
                const modalEl = document.getElementById('characterModal');
                if (modalEl && window.bootstrap) {
                    this.characterModal = new window.bootstrap.Modal(modalEl);
                }
            }
            if (this.characterModal) {
                this.characterModal.show();
            } else {
                console.error('Bootstrap Modal not initialized.');
            }
        },

        async saveCharacter() {
            const method = this.modalMode === 'create' ? 'POST' : 'PATCH';
            const url = this.modalMode === 'create'
                ? '/dm/api/characters'
                : `/dm/api/characters/${this.characterForm.id}`;

            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            try {
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify(this.characterForm)
                });

                if (response.ok) {
                    await this.loadCharacters();
                    if (this.characterModal) this.characterModal.hide();
                } else {
                    alert('Error saving character');
                }
            } catch (e) {
                console.error(e);
                alert('Error saving character');
            }
        },

        // --- COMBAT LOGIC ---

        resolveFormula(formula) {
            if (!formula) return 0;
            formula = String(formula).toLowerCase().replace(/\s/g, '');
            if (/^\d+$/.test(formula)) return parseInt(formula);

            let parts = formula.split('d');
            if (parts.length !== 2) {
                try {
                    return Function('"use strict";return (' + formula.replace(/[^0-9+\-*\/()]/g, '') + ')')();
                } catch { return 0; }
            }

            let numDice = parseInt(parts[0]) || 1;
            let rest = parts[1];
            let dieSize = 0;
            let modifier = 0;
            let modIndex = rest.search(/[+\-]/);
            if (modIndex !== -1) {
                dieSize = parseInt(rest.substring(0, modIndex));
                modifier = parseInt(rest.substring(modIndex));
            } else {
                dieSize = parseInt(rest);
            }

            let total = 0;
            for (let i = 0; i < numDice; i++) {
                total += Math.floor(Math.random() * dieSize) + 1;
            }
            return total + modifier;
        },

        addToCombat(char) {
            let init = 0;
            if (char.type === 'player') {
                init = 0;
            } else {
                let mod = parseInt(char.stats.dex_mod) || 0;
                init = Math.floor(Math.random() * 20) + 1 + mod;
            }

            let hp = this.resolveFormula(char.stats.hp_formula);
            let count = this.combatants.filter(c => c.name === char.name).length;

            let combatant = {
                instanceId: Date.now() + Math.random(),
                id: char.id,
                name: char.name,
                type: char.type,
                ac: char.stats.ac,
                maxHp: hp,
                hp: hp,
                initiative: init,
                statuses: [],
                notes: char.stats.notes || '',
                enemyCount: char.type === 'template' ? count + 1 : null
            };

            this.combatants.push(combatant);
            this.sortCombat();
            this.saveSession();
        },

        sortCombat() {
            let activeId = null;
            if (this.combatants.length > 0 && this.combatants[this.currentTurnIndex]) {
                activeId = this.combatants[this.currentTurnIndex].instanceId;
            }

            this.combatants.sort((a, b) => b.initiative - a.initiative);

            if (activeId) {
                let newIndex = this.combatants.findIndex(c => c.instanceId === activeId);
                if (newIndex !== -1) {
                    this.currentTurnIndex = newIndex;
                }
            }
        },

        nextTurn() {
            if (this.combatants.length === 0) return;
            this.currentTurnIndex++;
            if (this.currentTurnIndex >= this.combatants.length) {
                this.currentTurnIndex = 0;
                this.round++;
            }
            this.saveSession();
        },

        resetCombat() {
            if (confirm('Clear all combatants?')) {
                this.combatants = [];
                this.round = 1;
                this.currentTurnIndex = 0;
                this.selectedCombatant = null;
                this.selectedStatBlock = '';
                this.saveSession();
            }
        },

        removeCombatant(index) {
            if (index < this.currentTurnIndex) {
                this.currentTurnIndex--;
            }
            this.combatants.splice(index, 1);
            this.saveSession();
        },

        modifyHp(combatant, amount) {
            combatant.hp += amount;
            this.saveSession();
        },

        addStatus(combatant) {
            let status = prompt("Status name (e.g. Stunned):");
            if (status) {
                combatant.statuses.push(status);
                this.saveSession();
            }
        },

        async selectCombatant(combatant) {
            this.selectedCombatant = combatant;
            if (combatant.notes) {
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const response = await fetch('/dm/api/render-stat-block', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({ content: combatant.notes })
                });
                const data = await response.json();
                this.selectedStatBlock = data.html;
            } else {
                this.selectedStatBlock = '';
            }
        },

        async saveSession() {
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            await fetch('/dm/api/session', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify({
                    data: {
                        combatants: this.combatants,
                        round: this.round,
                        currentTurnIndex: this.currentTurnIndex
                    }
                })
            });
        },

        async loadSession() {
            const response = await fetch('/dm/api/session');
            const data = await response.json();
            if (data) {
                this.combatants = data.combatants || [];
                this.round = data.round || 1;
                this.currentTurnIndex = data.currentTurnIndex || 0;
            }
        }
    }));
});


document.addEventListener('alpine:init', () => {
    Alpine.data('dmScreen', () => ({
        templates: [],
        players: [],
        groups: [],
        combatants: [],
        round: 1,
        currentTurnIndex: 0,
        hideDead: false,

        // Modal & Selection
        selectedCombatant: null,
        selectedStatBlock: '',
        characterModal: null,
        groupModal: null,
        modalMode: 'create', // or 'edit'

        characterForm: {
            id: null,
            name: '',
            type: 'template',
            stats: {
                ac: 10,
                hp_formula: '1d8',
                attributes: {
                    str: 10, dex: 10, con: 10, int: 10, wis: 10, cha: 10
                },
                saves: {
                    str: false, dex: false, con: false, int: false, wis: false, cha: false
                },
                notes: ''
            }
        },

        groupForm: {
            id: null,
            name: '',
            members: [] // { character_id: X, qty: Y }
        },

        init() {
            this.loadCharacters();
            this.loadSession();

            this.$nextTick(() => {
                const modalEl = document.getElementById('characterModal');
                if (modalEl && window.bootstrap) {
                    this.characterModal = new window.bootstrap.Modal(modalEl);
                }
                const groupModalEl = document.getElementById('groupModal');
                if (groupModalEl && window.bootstrap) {
                    this.groupModal = new window.bootstrap.Modal(groupModalEl);
                }
            });

            setInterval(() => {
                this.saveSession();
            }, 10000); // More frequent auto-save (10s)
        },

        async loadCharacters() {
            const response = await fetch('/dm/api/characters');
            const data = await response.json();
            this.templates = data.filter(c => c.type === 'template');
            this.players = data.filter(c => c.type === 'player');
            // Groups are saved as templates but we can identify them by a flag in stats or a naming convention
            // Better: use the 'type' field if we update the migration/enum, or just a flag in JSON.
            // For now, let's assume we use a specific type if possible, or filter from templates.
            this.groups = data.filter(c => c.type === 'group');
        },

        getStatModifier(val) {
            return Math.floor((val - 10) / 2);
        },

        openCharacterModal(type, char = null) {
            this.modalMode = char ? 'edit' : 'create';
            if (char) {
                this.characterForm = {
                    id: char.id,
                    name: char.name,
                    type: char.type,
                    stats: typeof char.stats === 'string' ? JSON.parse(char.stats) : JSON.parse(JSON.stringify(char.stats))
                };
                // Ensure nested structures exist
                if (!this.characterForm.stats.attributes) this.characterForm.stats.attributes = { str: 10, dex: 10, con: 10, int: 10, wis: 10, cha: 10 };
                if (!this.characterForm.stats.saves) this.characterForm.stats.saves = { str: false, dex: false, con: false, int: false, wis: false, cha: false };
            } else {
                this.characterForm = {
                    id: null,
                    name: '',
                    type: type,
                    stats: {
                        ac: 10,
                        hp_formula: type === 'template' ? '1d8' : '10',
                        attributes: { str: 10, dex: 10, con: 10, int: 10, wis: 10, cha: 10 },
                        saves: { str: false, dex: false, con: false, int: false, wis: false, cha: false },
                        notes: ''
                    }
                };
            }
            this.showModal(this.characterModal);
        },

        openGroupModal(group = null) {
            this.modalMode = group ? 'edit' : 'create';
            if (group) {
                const stats = typeof group.stats === 'string' ? JSON.parse(group.stats) : group.stats;
                this.groupForm = {
                    id: group.id,
                    name: group.name,
                    members: stats.members || []
                };
            } else {
                this.groupForm = {
                    id: null,
                    name: '',
                    members: []
                };
            }
            this.showModal(this.groupModal);
        },

        addGroupMember() {
            this.groupForm.members.push({ character_id: '', qty: 1 });
        },

        removeGroupMember(index) {
            this.groupForm.members.splice(index, 1);
        },

        showModal(modalObj) {
            if (modalObj) {
                modalObj.show();
            } else {
                console.error('Modal not initialized.');
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
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify(this.characterForm)
                });
                if (response.ok) {
                    await this.loadCharacters();
                    if (this.characterModal) this.characterModal.hide();
                }
            } catch (e) { console.error(e); }
        },

        async saveGroup() {
            const method = this.modalMode === 'create' ? 'POST' : 'PATCH';
            const url = this.modalMode === 'create'
                ? '/dm/api/characters'
                : `/dm/api/characters/${this.groupForm.id}`;

            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const payload = {
                name: this.groupForm.name,
                type: 'group',
                stats: { members: this.groupForm.members }
            };

            try {
                const response = await fetch(url, {
                    method: method,
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify(payload)
                });
                if (response.ok) {
                    await this.loadCharacters();
                    if (this.groupModal) this.groupModal.hide();
                }
            } catch (e) { console.error(e); }
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

        addToCombat(char, qty = 1) {
            qty = parseInt(qty) || 1;

            for (let i = 0; i < qty; i++) {
                let init = 0;
                let dexVal = (char.stats.attributes && char.stats.attributes.dex) ? char.stats.attributes.dex : 10;
                let dexMod = this.getStatModifier(dexVal);

                if (char.type === 'player') {
                    init = 0; // Manual input for players
                } else {
                    init = Math.floor(Math.random() * 20) + 1 + dexMod;
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
                    enemyCount: char.type === 'template' ? count + 1 : null,
                    stats: char.stats // Keep ref to attributes/saves
                };

                this.combatants.push(combatant);
            }
            this.sortCombat();
            this.saveSession();
        },

        addGroupToCombat(group) {
            const stats = typeof group.stats === 'string' ? JSON.parse(group.stats) : group.stats;
            const members = stats.members || [];

            members.forEach(m => {
                const char = [...this.templates, ...this.players].find(c => c.id == m.character_id);
                if (char) {
                    this.addToCombat(char, m.qty);
                }
            });
        },

        sortCombat() {
            let activeId = null;
            if (this.combatants.length > 0 && this.combatants[this.currentTurnIndex]) {
                activeId = this.combatants[this.currentTurnIndex].instanceId;
            }

            this.combatants.sort((a, b) => {
                if (b.initiative !== a.initiative) return b.initiative - a.initiative;
                // Tie breaker: higher Dex mod
                let modA = this.getStatModifier(a.stats?.attributes?.dex || 10);
                let modB = this.getStatModifier(b.stats?.attributes?.dex || 10);
                return modB - modA;
            });

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
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
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
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({
                    data: {
                        combatants: this.combatants,
                        round: this.round,
                        currentTurnIndex: this.currentTurnIndex,
                        hideDead: this.hideDead
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
                this.hideDead = data.hideDead || false;
            }
        }
    }));
});

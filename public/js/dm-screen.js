
function dmScreen() {
    return {
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
                attributes: { str: 10, dex: 10, con: 10, int: 10, wis: 10, cha: 10 },
                saves: { str: false, dex: false, con: false, int: false, wis: false, cha: false },
                notes: ''
            }
        },

        groupForm: {
            id: null,
            name: '',
            members: []
        },

        init() {
            this.loadCharacters();
            this.loadSession();

            // Inizializzazione modal con controllo esistenza bootstrap
            this.$nextTick(() => {
                this.initModals();
            });

            setInterval(() => {
                this.saveSession();
            }, 10000);
        },

        initModals() {
            const modalEl = document.getElementById('characterModal');
            if (modalEl && window.bootstrap) {
                this.characterModal = new window.bootstrap.Modal(modalEl);
            }
            const groupModalEl = document.getElementById('groupModal');
            if (groupModalEl && window.bootstrap) {
                this.groupModal = new window.bootstrap.Modal(groupModalEl);
            }
        },

        async loadCharacters() {
            try {
                const response = await fetch('/dm/api/characters');
                const data = await response.json();
                // Aggiungiamo qty: 1 a ogni elemento per gestire l'input di inserimento multiplo
                this.templates = data.filter(c => c.type === 'template').map(c => ({ ...c, qty: 1 }));
                this.players = data.filter(c => c.type === 'player').map(c => ({ ...c, qty: 1 }));
                this.groups = data.filter(c => c.type === 'group');
            } catch (e) {
                console.error("Errore caricamento libreria:", e);
            }
        },

        getStatModifier(val) {
            return Math.floor((val - 10) / 2);
        },

        openCharacterModal(type, char = null) {
            this.modalMode = char ? 'edit' : 'create';
            if (char) {
                const stats = typeof char.stats === 'string' ? JSON.parse(char.stats) : (char.stats || {});
                this.characterForm = {
                    id: char.id,
                    name: char.name,
                    type: char.type,
                    stats: {
                        ac: stats.ac || 10,
                        hp_formula: stats.hp_formula || '10',
                        attributes: stats.attributes || { str: 10, dex: 10, con: 10, int: 10, wis: 10, cha: 10 },
                        saves: stats.saves || { str: false, dex: false, con: false, int: false, wis: false, cha: false },
                        notes: stats.notes || ''
                    }
                };
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
                const stats = typeof group.stats === 'string' ? JSON.parse(group.stats) : (group.stats || {});
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
            if (!modalObj) this.initModals();
            if (modalObj) {
                modalObj.show();
            } else {
                alert('Impossibile caricare il modal. Riprova tra un istante.');
            }
        },

        async saveCharacter() {
            const method = this.modalMode === 'create' ? 'POST' : 'PATCH';
            const url = this.modalMode === 'create' ? '/dm/api/characters' : `/dm/api/characters/${this.characterForm.id}`;
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
            const url = this.modalMode === 'create' ? '/dm/api/characters' : `/dm/api/characters/${this.groupForm.id}`;
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

        addToCombat(char, qty = null) {
            // Se qty è null, prendiamo quello dall'oggetto (popolato da x-model)
            const countToAdd = qty !== null ? parseInt(qty) : (parseInt(char.qty) || 1);

            for (let i = 0; i < countToAdd; i++) {
                let init = 0;
                let stats = typeof char.stats === 'string' ? JSON.parse(char.stats) : (char.stats || {});
                let dexVal = (stats.attributes && stats.attributes.dex) ? stats.attributes.dex : 10;
                let dexMod = this.getStatModifier(dexVal);

                if (char.type === 'player') {
                    init = 0;
                } else {
                    init = Math.floor(Math.random() * 20) + 1 + dexMod;
                }

                let hp = this.resolveFormula(stats.hp_formula);
                let countSameName = this.combatants.filter(c => c.name === char.name).length;

                let combatant = {
                    instanceId: Date.now() + Math.random(),
                    id: char.id,
                    name: char.name,
                    type: char.type,
                    ac: stats.ac || 10,
                    maxHp: hp,
                    hp: hp,
                    initiative: init,
                    statuses: [],
                    notes: stats.notes || '',
                    enemyCount: char.type === 'template' ? countSameName + 1 : null,
                    stats: stats
                };
                this.combatants.push(combatant);
            }
            this.sortCombat();
            this.saveSession();
        },

        addGroupToCombat(group) {
            const stats = typeof group.stats === 'string' ? JSON.parse(group.stats) : (group.stats || {});
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
            try {
                const response = await fetch('/dm/api/session');
                const data = await response.json();
                if (data) {
                    this.combatants = data.combatants || [];
                    this.round = data.round || 1;
                    this.currentTurnIndex = data.currentTurnIndex || 0;
                    this.hideDead = data.hideDead || false;
                }
            } catch (e) {
                console.error("Errore caricamento sessione:", e);
            }
        }
    }
}
window.dmScreen = dmScreen;

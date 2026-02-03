
function dmScreen(config = {}) {
    return {
        // --- DATA PROPERTIES ---
        isMaster: config.isMaster || false,
        isMasterUtils: config.isMasterUtils || false,
        templates: [],
        players: [],
        groups: [],
        combatants: [],
        round: 1,
        currentTurnIndex: 0,
        hideDead: false,

        availableSessions: [],
        currentSession: { id: null, name: 'Nuova Sessione' },

        selectedCombatant: null,
        selectedStatBlock: '',
        characterModal: null,
        groupModal: null,
        modalMode: 'create',

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

        // --- INITIALIZATION ---
        init() {
            this.loadCharacters();
            this.loadSessionsList();
            this.loadDefaultSession();

            this.$nextTick(() => {
                this.initModals();
            });

            setInterval(() => {
                this.saveSession();
            }, 15000);
        },

        initModals() {
            if (!window.bootstrap) return;
            const modalEl = document.getElementById('characterModal');
            if (modalEl) this.characterModal = new window.bootstrap.Modal(modalEl);
            const groupModalEl = document.getElementById('groupModal');
            if (groupModalEl) this.groupModal = new window.bootstrap.Modal(groupModalEl);
        },

        async loadCharacters() {
            try {
                const response = await fetch('/dm/api/characters');
                const data = await response.json();
                this.templates = data.filter(c => c.type === 'template').map(c => ({ ...c, id: String(c.id), qty: 1 }));
                this.players = data.filter(c => c.type === 'player').map(c => ({ ...c, id: String(c.id), qty: 1 }));
                this.groups = data.filter(c => c.type === 'group').map(g => ({ ...g, id: String(g.id) }));
            } catch (e) {
                console.error("Errore caricamento libreria:", e);
            }
        },

        getStatModifier(val) {
            return Math.floor((parseInt(val || 10) - 10) / 2);
        },

        // --- SESSION MANAGEMENT ---
        async loadSessionsList() {
            const response = await fetch('/dm/api/sessions');
            this.availableSessions = await response.json();
        },

        async createSession() {
            let name = prompt("Nome della nuova sessione:");
            if (!name) return;

            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const response = await fetch('/dm/api/sessions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ name: name, data: { combatants: [], round: 1, currentTurnIndex: 0, hideDead: false } })
            });

            if (response.ok) {
                const newSession = await response.json();
                this.switchSession(newSession);
                await this.loadSessionsList();
            }
        },

        async switchSession(session) {
            this.currentSession = {
                id: session.id,
                name: session.name,
                share_code: session.share_code
            };
            const data = typeof session.data === 'string' ? JSON.parse(session.data) : session.data;
            if (data) {
                this.combatants = data.combatants || [];
                this.round = data.round || 1;
                this.currentTurnIndex = data.currentTurnIndex || 0;
                this.hideDead = data.hideDead || false;
            } else {
                this.combatants = [];
                this.round = 1;
                this.currentTurnIndex = 0;
            }
            this.selectedCombatant = null;
            this.selectedStatBlock = '';
        },

        async loadDefaultSession() {
            const response = await fetch('/dm/api/session');
            const session = await response.json();
            if (session) {
                this.switchSession(session);
            }
        },

        async deleteSession(id) {
            if (!confirm('Eliminare questa sessione?')) return;
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            await fetch(`/dm/api/sessions/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': token }
            });
            await this.loadSessionsList();
            if (this.currentSession.id === String(id)) {
                this.currentSession = { id: null, name: 'Nessuna Sessione' };
                this.combatants = [];
            }
        },

        // --- MODAL TRIGGERS ---
        openCharacterModal(type, char = null) {
            this.modalMode = char ? 'edit' : 'create';

            const defaults = {
                ac: 10,
                hp_formula: type === 'template' ? '1d8' : '10',
                attributes: { str: 10, dex: 10, con: 10, int: 10, wis: 10, cha: 10 },
                saves: { str: false, dex: false, con: false, int: false, wis: false, cha: false },
                notes: ''
            };

            if (char) {
                const s = typeof char.stats === 'string' ? JSON.parse(char.stats) : (char.stats || {});
                this.characterForm = {
                    id: char.id,
                    name: char.name,
                    type: char.type,
                    stats: {
                        ac: s.ac || defaults.ac,
                        hp_formula: s.hp_formula || defaults.hp_formula,
                        attributes: { ...defaults.attributes, ...(s.attributes || {}) },
                        saves: { ...defaults.saves, ...(s.saves || {}) },
                        notes: s.notes || ''
                    }
                };
            } else {
                this.characterForm = {
                    id: null,
                    name: '',
                    type: type,
                    stats: JSON.parse(JSON.stringify(defaults))
                };
            }
            this.showModal(this.characterModal);
        },

        openGroupModal(group = null) {
            this.modalMode = group ? 'edit' : 'create';
            if (group) {
                const stats = typeof group.stats === 'string' ? JSON.parse(group.stats) : (group.stats || {});
                let members = stats.members || [];

                // Assicuriamoci che sia un array (se arrivasse come oggetto associativo dal JSON)
                if (!Array.isArray(members)) {
                    members = Object.values(members);
                }

                this.groupForm.id = String(group.id);
                this.groupForm.name = group.name;
                // Clona l'array e assicura che gli ID siano stringhe per il matching del select del browser
                this.groupForm.members = JSON.parse(JSON.stringify(members)).map(m => ({
                    character_id: m.character_id ? String(m.character_id) : '',
                    qty: parseInt(m.qty) || 1
                }));
            } else {
                this.groupForm.id = null;
                this.groupForm.name = '';
                this.groupForm.members = [];
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
            if (modalObj) modalObj.show();
            else alert('Errore Modal Bootstrap.');
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
                } else {
                    const err = await response.json();
                    alert("Errore: " + (err.message || "Salvataggio fallito"));
                }
            } catch (e) { console.error(e); }
        },

        async saveGroup() {
            const isEdit = !!this.groupForm.id;
            const method = isEdit ? 'PATCH' : 'POST';
            const url = isEdit ? `/dm/api/characters/${this.groupForm.id}` : '/dm/api/characters';
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
                } else {
                    const err = await response.json();
                    alert("Errore: " + (err.message || "Salvataggio gruppo fallito"));
                }
            } catch (e) {
                console.error("Errore salvataggio gruppo:", e);
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
                    return eval(formula.replace(/[^0-9+\-*\/()]/g, ''));
                } catch { return 0; }
            }

            let numDice = parseInt(parts[0]) || 1;
            let rest = parts[1];
            let dieSize = 0, modifier = 0;
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
            const countToAdd = qty !== null ? parseInt(qty) : (parseInt(char.qty) || 1);
            for (let i = 0; i < countToAdd; i++) {
                let init = 0;
                let stats = typeof char.stats === 'string' ? JSON.parse(char.stats) : (char.stats || {});
                let dexVal = (stats.attributes && stats.attributes.dex) ? stats.attributes.dex : 10;
                let dexMod = this.getStatModifier(dexVal);

                if (char.type === 'player') init = 0;
                else init = Math.floor(Math.random() * 20) + 1 + dexMod;

                let hp = this.resolveFormula(stats.hp_formula);

                this.combatants.push({
                    instanceId: Date.now() + Math.random(),
                    id: char.id,
                    name: char.name,
                    alias: countToAdd > 1 ? `${i + 1} - ${char.name}` : '',
                    type: char.type,
                    ac: stats.ac || 10,
                    maxHp: hp, hp: hp, tempHp: 0,
                    initiative: init,
                    statuses: [],
                    personalNotes: '',
                    showNotesInline: false,
                    stats: stats
                });
            }
            this.sortCombat();
            this.saveSession();
        },

        addGroupToCombat(group) {
            const stats = typeof group.stats === 'string' ? JSON.parse(group.stats) : (group.stats || {});
            const members = stats.members || [];
            members.forEach(m => {
                const char = [...this.templates, ...this.players].find(c => c.id == m.character_id);
                if (char) this.addToCombat(char, m.qty);
            });
        },

        sortCombat() {
            let activeId = null;
            if (this.combatants.length > 0 && this.combatants[this.currentTurnIndex]) {
                activeId = this.combatants[this.currentTurnIndex].instanceId;
            }
            this.combatants.sort((a, b) => {
                if (b.initiative !== a.initiative) return b.initiative - a.initiative;
                return this.getStatModifier(b.stats?.attributes?.dex || 10) - this.getStatModifier(a.stats?.attributes?.dex || 10);
            });
            if (activeId) {
                let newIndex = this.combatants.findIndex(c => c.instanceId === activeId);
                if (newIndex !== -1) this.currentTurnIndex = newIndex;
            }
        },

        startCombat() {
            this.sortCombat();
            this.round = 1;
            this.currentTurnIndex = 0;

            // Se il primo combatente è morto (non player), cerchiamo il primo valido
            if (this.combatants.length > 0) {
                let c = this.combatants[0];
                if (c.type !== 'player' && (c.hp === undefined || c.hp <= 0)) {
                    this.nextTurn(); // Usa la logica di skip già implementata
                    return; // nextTurn salva già la sessione
                }
            }

            this.saveSession();
        },

        nextTurn() {
            if (this.combatants.length === 0) return;

            let startIndex = this.currentTurnIndex;
            let foundNext = false;

            while (!foundNext) {
                this.currentTurnIndex++;
                if (this.currentTurnIndex >= this.combatants.length) {
                    this.currentTurnIndex = 0;
                    this.round++;
                }

                // Un combatante è valido se è un giocatore oppure ha HP > 0
                let c = this.combatants[this.currentTurnIndex];
                if (c.type === 'player' || (c.hp !== undefined && c.hp > 0)) {
                    foundNext = true;
                }

                // Sicurezza: se abbiamo fatto il giro completo e non abbiamo trovato nessuno vivo
                if (this.currentTurnIndex === startIndex) break;
            }

            this.saveSession();
        },

        removeDead() {
            if (!confirm('Rimuovere tutti i mostri morti?')) return;

            // Trovo l'ID di chi ha il turno ora
            let activeId = null;
            if (this.combatants[this.currentTurnIndex]) {
                activeId = this.combatants[this.currentTurnIndex].instanceId;
            }

            // Filtro: tengo i giocatori e chi ha HP > 0
            this.combatants = this.combatants.filter(c => c.type === 'player' || (c.hp !== undefined && c.hp > 0));

            // Riposiziono il currentTurnIndex
            if (activeId) {
                let newIndex = this.combatants.findIndex(c => c.instanceId === activeId);
                if (newIndex !== -1) {
                    this.currentTurnIndex = newIndex;
                } else {
                    // Se chi aveva il turno è stato rimosso, il turno passa al successivo (che ora è nello stesso indice o 0)
                    if (this.currentTurnIndex >= this.combatants.length) {
                        this.currentTurnIndex = 0;
                    }
                }
            } else {
                this.currentTurnIndex = 0;
            }

            this.saveSession();
        },

        resetCombat() {
            if (confirm('Resettare il combattimento?')) {
                this.combatants = []; this.round = 1; this.currentTurnIndex = 0;
                this.selectedCombatant = null; this.selectedStatBlock = '';
                this.saveSession();
            }
        },

        removeCombatant(index) {
            const isSelected = (this.selectedCombatant && this.selectedCombatant.instanceId === this.combatants[index].instanceId);
            if (index < this.currentTurnIndex) this.currentTurnIndex--;
            this.combatants.splice(index, 1);
            if (isSelected) this.selectedCombatant = null;
            this.saveSession();
        },

        modifyHp(combatant, amount) {
            if (amount < 0) {
                let damage = Math.abs(amount);
                if (combatant.tempHp > 0) {
                    if (combatant.tempHp >= damage) {
                        combatant.tempHp -= damage;
                        damage = 0;
                    } else {
                        damage -= combatant.tempHp;
                        combatant.tempHp = 0;
                    }
                }
                combatant.hp -= damage;
                if (combatant.hp < 0) combatant.hp = 0;
            } else {
                combatant.hp = (combatant.hp || 0) + amount;
            }
            this.saveSession();
        },

        addStatus(combatant) {
            let status = prompt("Stato (es. Intontito):");
            if (status) { combatant.statuses.push(status); this.saveSession(); }
        },

        async selectCombatant(combatant) {
            this.selectedCombatant = combatant;
            if (combatant.stats && combatant.stats.notes) {
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const response = await fetch('/dm/api/render-stat-block', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ content: combatant.stats.notes })
                });
                const data = await response.json();
                this.selectedStatBlock = data.html;
            } else { this.selectedStatBlock = ''; }
        },

        async saveSession() {
            if (!this.currentSession.id) return;

            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const data = {
                combatants: this.combatants,
                round: this.round,
                currentTurnIndex: this.currentTurnIndex,
                hideDead: this.hideDead
            };

            await fetch(`/dm/api/sessions/${this.currentSession.id}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ data: data })
            });
        }
    }
}
window.dmScreen = dmScreen;

@extends('layouts.app')

@section('include')
    <script src="//unpkg.com/alpinejs" defer></script>
@endsection

@section('script')
    <script>
        function dmManage() {
            return {
                activeTab: 'characters',
                characters: [],
                sessions: [],
                charFilter: '',
                importModalOpen: false,
                importLoading: false,
                importItems: [],
                importSubmitting: false,

                init() {
                    this.reloadData();
                },

                async reloadData() {
                    const response = await fetch('/dm/api/manage-data');
                    const data = await response.json();
                    this.characters = data.characters;
                    this.sessions = data.sessions;
                },

                get filteredCharacters() {
                    if (!this.charFilter) return this.characters;
                    const f = this.charFilter.toLowerCase();
                    return this.characters.filter(c =>
                        c.name.toLowerCase().includes(f) || c.type.toLowerCase().includes(f)
                    );
                },

                getStats(char) {
                    return typeof char.stats === 'string' ? JSON.parse(char.stats) : (char.stats || {});
                },

                getData(session) {
                    return typeof session.data === 'string' ? JSON.parse(session.data) : (session.data || {});
                },

                canEdit(char) {
                    return char.user_id === {{ Auth::id() }} || {{ Auth::user()->isAdmin() ? 'true' : 'false' }};
                },

                formatDate(dateStr) {
                    return new Date(dateStr).toLocaleString('it-IT');
                },

                async editChar(char) {
                    alert("Puoi modificare i dettagli dei personaggi direttamente dallo 'DM Screen' cliccando sull'icona della matita nella libreria.");
                },

                async deleteChar(id) {
                    if (!confirm('Eliminare definitivamente questo personaggio?')) return;
                    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    await fetch(`/dm/api/characters/${id}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': token }
                    });
                    this.reloadData();
                },

                async renameSession(s) {
                    const newName = prompt("Nuovo nome per la sessione:", s.name);
                    if (!newName || newName === s.name) return;
                    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    await fetch(`/dm/api/sessions/${s.id}`, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                        body: JSON.stringify({ name: newName, data: this.getData(s) })
                    });
                    this.reloadData();
                },

                async deleteSession(id) {
                    if (!confirm('Eliminare definitivamente questa sessione?')) return;
                    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    await fetch(`/dm/api/sessions/${id}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': token }
                    });
                    this.reloadData();
                },

                async openImportModal() {
                    this.importModalOpen = true;
                    this.importLoading = true;
                    this.importItems = [];
                    try {
                        const response = await fetch('/dm/api/materiale/stat-blocks');
                        const data = await response.json();
                        this.importItems = (data.stat_blocks || []).map(sb => ({
                            ...sb,
                            selected: !sb.error,
                            resolution: sb.conflict ? 'duplicate' : 'overwrite',
                        }));
                    } catch (e) {
                        alert('Errore durante la scansione della cartella stat-block in Materiali.');
                    }
                    this.importLoading = false;
                },

                closeImportModal() {
                    this.importModalOpen = false;
                },

                get importSelectedCount() {
                    return this.importItems.filter(i => i.selected).length;
                },

                async submitImport() {
                    const selected = this.importItems.filter(i => i.selected && !i.error);
                    if (selected.length === 0) return;

                    this.importSubmitting = true;
                    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    try {
                        const response = await fetch('/dm/api/materiale/stat-blocks/import', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                            body: JSON.stringify({
                                items: selected.map(i => ({ path: i.path, resolution: i.resolution }))
                            })
                        });
                        const data = await response.json();
                        if (data.errors && data.errors.length) {
                            alert('Alcuni file non sono stati importati:\n' + data.errors.map(e => `${e.path}: ${e.error}`).join('\n'));
                        }
                        this.importModalOpen = false;
                        this.reloadData();
                    } catch (e) {
                        alert('Errore durante l\'importazione.');
                    }
                    this.importSubmitting = false;
                }
            }
        }
    </script>
@endsection

@section('style')
    <style>
        .nav-pills .nav-link {
            color: #aaa;
            transition: all 0.2s;
        }

        .nav-pills .nav-link.active {
            background-color: #ffc107;
            color: #000;
            font-weight: bold;
        }

        .nav-pills .nav-link:hover:not(.active) {
            background-color: #444;
            color: #fff;
        }

        .table-dark {
            --bs-table-bg: transparent;
        }

        .table-secondary {
            --bs-table-bg: #444;
            color: #fff;
            border-bottom: 0;
        }

        tbody tr {
            transition: background 0.2s;
        }

        tbody tr:hover {
            background: rgba(255, 255, 255, 0.05) !important;
        }
    </style>
@endsection

@section('content')
    <div class="container" x-data="dmManage()">
        <div class="row mb-4">
            <div class="col">
                <h1 class="display-5">🛠️ Gestione Risorse Master</h1>
                <p class="text-muted">Gestisci i tuoi personaggi, gruppi, sessioni e mostri pubblici.</p>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col">
                <ul class="nav nav-pills bg-dark p-2 rounded shadow-sm" id="manageTabs">
                    <li class="nav-item">
                        <button class="nav-link px-4" :class="activeTab === 'characters' ? 'active' : ''"
                            @click="activeTab = 'characters'">👤 Personaggi & Mostri</button>
                    </li>
                    <li class="nav-item ms-2">
                        <button class="nav-link px-4" :class="activeTab === 'sessions' ? 'active' : ''"
                            @click="activeTab = 'sessions'">💾 Sessioni Salvate</button>
                    </li>
                </ul>
            </div>
        </div>

        <!-- TABS CONTENT -->
        <div class="tab-content border border-secondary rounded p-4 bg-dark bg-opacity-25 shadow-sm">

            <!-- CHARACTERS TAB -->
            <div x-show="activeTab === 'characters'">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3>Personaggi e Modelli</h3>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-warning" @click="openImportModal()">
                            <i class="bi bi-cloud-download"></i> Importa da Materiali
                        </button>
                        <div class="input-group" style="width: 320px;">
                            <span class="input-group-text bg-secondary border-0 text-white"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control bg-dark text-white border-0"
                                placeholder="Filtra per nome o tipo..." x-model="charFilter">
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle shadow-sm">
                        <thead class="table-secondary">
                            <tr>
                                <th>Nome</th>
                                <th>Tipo</th>
                                <th>Creatore</th>
                                <th>CA</th>
                                <th>HP</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="char in filteredCharacters" :key="char.id">
                                <tr>
                                    <td class="fw-bold" x-text="char.name"></td>
                                    <td>
                                        <span class="badge"
                                            :class="{'bg-danger': char.type==='template', 'bg-info': char.type==='player', 'bg-warning text-dark': char.type==='group'}"
                                            x-text="char.type === 'template' ? 'Mostro' : (char.type === 'player' ? 'Giocatore' : 'Gruppo')"></span>
                                    </td>
                                    <td class="small text-muted"
                                        x-text="char.user_id === {{ Auth::id() }} ? 'Tu' : (char.user ? char.user.name : 'Sistema')">
                                    </td>
                                    <td x-text="getStats(char).ac || '-'"></td>
                                    <td x-text="getStats(char).hp_formula || '-'"></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-info" @click="editChar(char)"
                                            x-show="canEdit(char)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger ms-1" @click="deleteChar(char.id)"
                                            x-show="canEdit(char)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SESSIONS TAB -->
            <div x-show="activeTab === 'sessions'">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3>Le tue Sessioni</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle">
                        <thead class="table-secondary">
                            <tr>
                                <th>Sistema</th>
                                <th>Nome Sessione</th>
                                <th>Ultimo Salvataggio</th>
                                <th>Info</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="s in sessions" :key="s.id">
                                <tr>
                                    <td>
                                        <span class="badge" :class="s.system === 'powerfail' ? 'bg-danger' : 'bg-primary'"
                                            x-text="s.system === 'powerfail' ? 'Powerfail' : 'D&D 5e'">
                                        </span>
                                    </td>
                                    <td class="fw-bold" x-text="s.name"></td>
                                    <td class="small" x-text="formatDate(s.updated_at)"></td>
                                    <td class="small">
                                        <template x-if="s.system === 'powerfail'">
                                            <span>
                                                <i class="bi bi-people"></i> <span
                                                    x-text="(getData(s).actors || []).length"></span> Attori
                                                <br>
                                                <i class="bi bi-film"></i> <span
                                                    x-text="getData(s).scene?.state || 'N/A'"></span>
                                            </span>
                                        </template>
                                        <template x-if="s.system !== 'powerfail'">
                                            <span>
                                                <i class="bi bi-arrow-repeat"></i> Round: <span
                                                    x-text="getData(s).round || '1'"></span>
                                                <br>
                                                <i class="bi bi-people"></i> <span
                                                    x-text="(getData(s).combatants || []).length"></span> Combattenti
                                            </span>
                                        </template>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-info" @click="renameSession(s)">
                                            <i class="bi bi-chat-left-text"></i> Rinomina
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger ms-1" @click="deleteSession(s.id)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MODALE IMPORT DA MATERIALI -->
    <div class="modal" tabindex="-1" style="display: none;" x-show="importModalOpen" x-cloak
        @keydown.escape.window="closeImportModal()">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content bg-dark text-white border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="bi bi-cloud-download me-2"></i>Importa stat-block da Materiali</h5>
                    <button type="button" class="btn-close btn-close-white" @click="closeImportModal()"></button>
                </div>
                <div class="modal-body">
                    <div x-show="importLoading" class="text-center py-4">
                        <div class="spinner-border text-warning"></div>
                        <p class="mt-2 text-muted">Scansione di <code>manuali/stat-block</code> in corso...</p>
                    </div>
                    <template x-if="!importLoading && importItems.length === 0">
                        <p class="text-muted">Nessuna stat-block trovata in <code>materiale/manuali/stat-block</code>.</p>
                    </template>
                    <template x-if="!importLoading && importItems.length > 0">
                        <table class="table table-dark table-sm align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 2rem;"></th>
                                    <th>Nome</th>
                                    <th>CA</th>
                                    <th>HP</th>
                                    <th style="width: 12rem;">Se già esistente</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in importItems" :key="item.path">
                                    <tr :class="item.error ? 'opacity-50' : ''">
                                        <td>
                                            <input type="checkbox" class="form-check-input" x-model="item.selected" :disabled="!!item.error">
                                        </td>
                                        <td>
                                            <span x-text="item.name"></span>
                                            <span class="badge bg-warning text-dark ms-1" x-show="item.conflict">già presente</span>
                                            <div class="small text-danger" x-show="item.error" x-text="item.error"></div>
                                        </td>
                                        <td x-text="item.ac || '-'"></td>
                                        <td x-text="item.hp_formula || '-'"></td>
                                        <td>
                                            <select class="form-select form-select-sm bg-dark text-white border-secondary"
                                                x-model="item.resolution" x-show="item.conflict">
                                                <option value="overwrite">Sovrascrivi</option>
                                                <option value="duplicate">Duplica (nuova copia)</option>
                                            </select>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </template>
                </div>
                <div class="modal-footer border-secondary">
                    <button class="btn btn-outline-secondary" @click="closeImportModal()">Annulla</button>
                    <button class="btn btn-warning" @click="submitImport()"
                        :disabled="importSelectedCount === 0 || importSubmitting">
                        <span x-show="importSubmitting" class="spinner-border spinner-border-sm me-1"></span>
                        Importa (<span x-text="importSelectedCount"></span>)
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop" x-show="importModalOpen" x-cloak></div>
@endsection
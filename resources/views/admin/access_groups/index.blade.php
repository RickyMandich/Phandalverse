@extends('layouts.app')

@section('title', 'Gruppi di Accesso')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><i class="bi bi-shield-lock"></i> Gruppi di Accesso al Vault</h3>
        <a href="{{ route('admin.access_groups.create') }}" class="btn btn-success">
            <i class="bi bi-plus-lg"></i> Nuovo Gruppo
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-info-circle"></i> Come funziona l'accesso ai contenuti
        </div>
        <div class="card-body small text-muted">
            <ul class="mb-0">
                <li><strong>Campagna:</strong> ciascun gruppo appartiene a una specifica campagna.</li>
                <li><strong>Nota intera riservata:</strong> inserisci <code>#access-gruppo</code> o <code>#access-gruppo1_gruppo2</code> in testa alla nota.</li>
                <li><strong>Blocco riservato parziale:</strong> racchiudi il testo tra <code>#startAccess-gruppo</code> (o <code>#startAccess-gruppo1_gruppo2</code> per più gruppi in OR) e <code>#endAccess</code>.</li>
                <li><strong>Gerarchia:</strong> un utente appartenente a un gruppo <em>figlio</em> eredita automaticamente l'accesso ai contenuti del gruppo <em>padre</em>.</li>
                <li><strong>Master:</strong> l'utente con ruolo Master vede sempre tutti i contenuti e le note.</li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Campagna</th>
                        <th>Nome</th>
                        <th>Tag Markdown</th>
                        <th>Colore</th>
                        <th>Gruppo Padre</th>
                        <th>Membri</th>
                        <th>Descrizione</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groups as $group)
                        <tr>
                            <td>
                                <span class="badge bg-dark border border-warning text-warning">
                                    <i class="bi bi-compass me-1"></i> {{ $group->campaign ? $group->campaign->display_name : 'Default' }}
                                </span>
                            </td>
                            <td>
                                <strong>{{ $group->name }}</strong>
                            </td>
                            <td>
                                <code>#access-{{ $group->slug }}</code>
                            </td>
                            <td>
                                @if($group->color)
                                    <span class="d-inline-flex align-items-center">
                                        <span class="d-inline-block rounded-circle me-1" style="width: 14px; height: 14px; background-color: {{ $group->color }}; border: 1px solid rgba(255,255,255,0.2);"></span>
                                        <code>{{ $group->color }}</code>
                                    </span>
                                @else
                                    <span class="text-muted fst-italic">Default</span>
                                @endif
                            </td>
                            <td>
                                @if($group->parent)
                                    <span class="badge" style="background-color: {{ $group->parent->color ?? '#6c757d' }};">
                                        <i class="bi bi-diagram-2"></i> {{ $group->parent->name }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ $group->users_count }} utenti</span>
                            </td>
                            <td>
                                <small class="text-muted">{{ Str::limit($group->description, 50) ?: '—' }}</small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('admin.access_groups.edit', $group) }}" class="btn btn-primary" title="Modifica">
                                        <i class="bi bi-pencil"></i> Modifica
                                    </a>
                                    <button type="button" class="btn btn-info" title="Copia in un'altra campagna"
                                            data-bs-toggle="modal" data-bs-target="#copyGroupModal"
                                            data-action="{{ route('admin.access_groups.copy', $group) }}"
                                            data-group-name="{{ $group->name }}"
                                            data-campaign-id="{{ $group->campaign_id }}"
                                            @disabled($campaigns->count() < 2)>
                                        <i class="bi bi-copy"></i> Copia
                                    </button>
                                    <form action="{{ route('admin.access_groups.delete', $group) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Sei sicuro di voler eliminare il gruppo {{ $group->name }}? I gruppi figli perderanno il genitore ma non verranno cancellati.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger" title="Elimina">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Nessun gruppo di accesso configurato.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modale unico per la copia di un gruppo in un'altra campagna (valorizzato via data-* dal bottone "Copia" della riga) --}}
    <div class="modal fade" id="copyGroupModal" tabindex="-1" aria-labelledby="copyGroupModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="" id="copyGroupForm" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="copyGroupModalLabel">
                        <i class="bi bi-copy"></i> Copia gruppo <span id="copyGroupName" class="fw-bold"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                </div>
                <div class="modal-body">
                    <label for="copyGroupTarget" class="form-label">Campagna di destinazione</label>
                    <select name="target_campaign_id" id="copyGroupTarget" class="form-select" required>
                        @foreach($campaigns as $campaign)
                            <option value="{{ $campaign->id }}">{{ $campaign->display_name }}</option>
                        @endforeach
                    </select>
                    <ul class="small text-muted mt-3 mb-0">
                        <li>Vengono copiati nome, slug, descrizione e colore.</li>
                        <li>I <strong>membri</strong> e i <strong>gruppi figli</strong> non vengono copiati.</li>
                        <li>Il gruppo padre viene ricollegato solo se nella campagna di destinazione esiste già un gruppo con lo stesso slug.</li>
                        <li>Se nella destinazione esiste già un gruppo con lo stesso slug la copia viene annullata.</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-info"><i class="bi bi-copy"></i> Copia</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('copyGroupModal');
        if (!modal) return;

        modal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (!button) return;

            var sourceCampaignId = button.getAttribute('data-campaign-id');
            document.getElementById('copyGroupForm').setAttribute('action', button.getAttribute('data-action'));
            document.getElementById('copyGroupName').textContent = button.getAttribute('data-group-name');

            // La campagna sorgente non è una destinazione valida: la nascondo e seleziono la prima disponibile
            var select = document.getElementById('copyGroupTarget');
            var firstAvailable = null;
            Array.prototype.forEach.call(select.options, function (option) {
                var isSource = option.value === sourceCampaignId;
                option.hidden = isSource;
                option.disabled = isSource;
                if (!isSource && firstAvailable === null) {
                    firstAvailable = option;
                }
            });
            if (firstAvailable) {
                select.value = firstAvailable.value;
            }
        });
    });
</script>
@endsection

@extends('layouts.app')

@section('title', 'Modifica Campagna - ' . $campaign->display_name)

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3><i class="bi bi-compass"></i> Modifica Campagna: {{ $campaign->display_name }}</h3>
                <a href="{{ route('admin.campaigns') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Torna alla lista
                </a>
            </div>

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.campaigns.update', $campaign) }}">
                        @csrf
                        @method('PATCH')

                        <div class="mb-3">
                            <label for="display_name" class="form-label fw-bold">Nome Visualizzato <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('display_name') is-invalid @enderror"
                                   id="display_name" name="display_name" value="{{ old('display_name', $campaign->display_name) }}"
                                   placeholder="Es: Phandalin" required>
                            <div class="form-text">Il nome pubblico della campagna visualizzato nella UI, nel selettore e nelle notifiche.</div>
                        </div>

                        <div class="mb-3">
                            <label for="folder_name" class="form-label fw-bold">Nome Cartella Vault / Branch <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('folder_name') is-invalid @enderror"
                                   id="folder_name" name="folder_name" value="{{ old('folder_name', $campaign->folder_name) }}"
                                   placeholder="Es: newCampaign" required>
                            <div class="form-text">Il nome esatto della sottocartella su disco (es. <code>Vault/{{ $campaign->folder_name }}/</code>).</div>
                        </div>

                        <div class="mb-3">
                            <label for="order" class="form-label fw-bold">Ordine di Visualizzazione <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('order') is-invalid @enderror"
                                   id="order" name="order" value="{{ old('order', $campaign->order) }}" required>
                            <div class="form-text">Numero d'ordine (es: 10, 20, 30...). I numeri più bassi hanno priorità.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Utenti Assegnati (Accesso)</label>
                            <div class="border rounded p-3 bg-dark-subtle" style="max-height: 200px; overflow-y: auto;">
                                @forelse($users as $u)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="users[]" value="{{ $u->id }}"
                                               id="user_{{ $u->id }}" {{ in_array($u->id, old('users', $campaignUserIds ?? [])) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="user_{{ $u->id }}">
                                            {{ $u->name }} ({{ $u->email }})
                                            @if($u->isMaster())
                                                <span class="badge bg-warning text-dark ms-1">Master</span>
                                            @endif
                                        </label>
                                    </div>
                                @empty
                                    <span class="text-muted">Nessun utente disponibile.</span>
                                @endforelse
                            </div>
                            <div class="form-text">I Master e gli Admin hanno sempre accesso a tutte le campagne indipendentemente da questa selezione.</div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.campaigns') }}" class="btn btn-secondary">Annulla</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> Salva Modifiche
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Modifica Utente')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3><i class="bi bi-pencil"></i> Modifica Utente: {{ $user->name }}</h3>
                <a href="{{ route('admin.users') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Torna alla lista
                </a>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <!-- Card Dati Utente -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-person"></i> Dati Utente
                </div>
                <div class="card-body">
                    @if($errors->profile->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->profile->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.users.update', $user) }}">
                        @csrf
                        @method('PATCH')

                        <div class="mb-3">
                            <label for="name" class="form-label">Nome</label>
                            <input type="text" class="form-control @error('name', 'profile') is-invalid @enderror"
                                   id="name" name="name" value="{{ old('name', $user->name) }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control @error('email', 'profile') is-invalid @enderror"
                                   id="email" name="email" value="{{ old('email', $user->email) }}" required>
                        </div>

                        <hr>
                        <h6>Ruoli e Permessi</h6>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="verified" name="verified" value="1"
                                       {{ old('verified', $user->email_verified_at) ? 'checked' : '' }}>
                                <label class="form-check-label" for="verified">
                                    <span class="badge bg-success">Email Verificata</span>
                                    <small class="text-muted d-block">L'account ha completato la verifica email</small>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="admin" name="admin" value="1"
                                       {{ old('admin', $user->admin) ? 'checked' : '' }}>
                                <label class="form-check-label" for="admin">
                                    <span class="badge bg-danger">Amministratore</span>
                                    <small class="text-muted d-block">Accesso completo al pannello admin</small>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="master" name="master" value="1"
                                       {{ old('master', $user->master) ? 'checked' : '' }}>
                                <label class="form-check-label" for="master">
                                    <span class="badge bg-warning">Master</span>
                                    <small class="text-muted d-block">Dungeon Master (Accesso completo e Note)</small>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="master_utils" name="master_utils" value="1"
                                       {{ old('master_utils', $user->master_utils) ? 'checked' : '' }}>
                                <label class="form-check-label" for="master_utils">
                                    <span class="badge bg-secondary">Master Utils</span>
                                    <small class="text-muted d-block">Accesso a Tracker e Sessioni (Senza Note)</small>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="showEmbedLink" name="showEmbedLink" value="1"
                                       {{ old('showEmbedLink', $user->showEmbedLink) ? 'checked' : '' }}>
                                <label class="form-check-label" for="showEmbedLink">
                                    <span class="badge bg-info">Mostra Link Embed</span>
                                    <small class="text-muted d-block">Visualizza i link per embed nelle note</small>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="collapseEmbed" name="collapseEmbed" value="1"
                                       {{ old('collapseEmbed', $user->collapseEmbed) ? 'checked' : '' }}>
                                <label class="form-check-label" for="collapseEmbed">
                                    <span class="badge bg-secondary">Comprimi Embed di Default</span>
                                    <small class="text-muted d-block">Il contenuto degli embed apparirà nascosto inizialmente</small>
                                </label>
                            </div>
                        </div>

                        <hr>
                        <h6><i class="bi bi-compass"></i> Campagne Assegnate (Accesso Vault)</h6>
                        <p class="text-muted small">Seleziona le campagne a cui l'utente può accedere. (I Master e gli Admin hanno sempre accesso a tutte).</p>

                        @if(isset($campaigns) && $campaigns->count() > 0)
                            <div class="row g-2 mb-3">
                                @foreach($campaigns as $camp)
                                    @php
                                        $isCampChecked = is_array(old('campaigns'))
                                            ? in_array($camp->id, old('campaigns'))
                                            : in_array($camp->id, $userCampaignIds ?? []);
                                    @endphp
                                    <div class="col-md-6">
                                        <div class="card p-2 h-100 bg-dark border-secondary">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="campaigns[]" 
                                                       value="{{ $camp->id }}" 
                                                       id="camp_{{ $camp->id }}"
                                                       {{ $isCampChecked ? 'checked' : '' }}>
                                                <label class="form-check-label d-flex align-items-center" for="camp_{{ $camp->id }}">
                                                    <span class="badge bg-dark border border-warning text-warning me-2">
                                                        {{ $camp->display_name }}
                                                    </span>
                                                    <small class="text-muted"><code>{{ $camp->folder_name }}</code></small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mb-3">
                                <label for="default_campaign_id" class="form-label fw-bold">Campagna Predefinita</label>
                                <select class="form-select" id="default_campaign_id" name="default_campaign_id">
                                    <option value="">-- Nessuna (Usa ordine predefinito) --</option>
                                    @foreach($campaigns as $camp)
                                        <option value="{{ $camp->id }}" {{ (string) old('default_campaign_id', $user->default_campaign_id) === (string) $camp->id ? 'selected' : '' }}>
                                            {{ $camp->display_name }} ({{ $camp->folder_name }})
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">La campagna predefinita aperta quando l'utente visita /vault.</small>
                            </div>
                        @else
                            <p class="text-muted fst-italic">Nessuna campagna configurata.</p>
                        @endif

                        <hr>
                        <h6><i class="bi bi-shield-lock"></i> Gruppi di Accesso al Vault</h6>
                        <p class="text-muted small">Seleziona i gruppi di appartenenza dell'utente per sbloccare le note e sezioni riservate.</p>

                        @if(isset($accessGroups) && $accessGroups->count() > 0)
                            <div class="row g-2 mb-3">
                                @foreach($accessGroups as $group)
                                    @php
                                        $isChecked = is_array(old('access_groups'))
                                            ? in_array($group->id, old('access_groups'))
                                            : in_array($group->id, $userGroupIds ?? []);
                                    @endphp
                                    <div class="col-md-6">
                                        <div class="card p-2 h-100 bg-dark border-secondary">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="access_groups[]" 
                                                       value="{{ $group->id }}" 
                                                       id="ag_{{ $group->id }}"
                                                       {{ $isChecked ? 'checked' : '' }}>
                                                <label class="form-check-label d-flex align-items-center" for="ag_{{ $group->id }}">
                                                    <span class="badge me-2" style="background-color: {{ $group->color ?? '#6c757d' }}; color: #fff;">
                                                        {{ $group->name }}
                                                    </span>
                                                    @if($group->campaign)
                                                        <span class="badge bg-dark border border-secondary text-muted me-1 small">
                                                            {{ $group->campaign->display_name }}
                                                        </span>
                                                    @endif
                                                    <small class="text-muted"><code>#access-{{ $group->slug }}</code></small>
                                                </label>
                                                @if($group->parent)
                                                    <small class="text-muted d-block mt-1 ps-4">
                                                        <i class="bi bi-arrow-return-right"></i> Figlio di: {{ $group->parent->name }}
                                                    </small>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted fst-italic">Nessun gruppo di accesso creato. Puoi crearli dalla sezione Gruppi di Accesso.</p>
                        @endif

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Salva Dati
                        </button>
                    </form>
                </div>
            </div>

            <!-- Card Cambia Password -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-key"></i> Cambia Password
                </div>
                <div class="card-body">
                    @if($errors->password->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->password->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.users.password', $user) }}" autocomplete="off">
                        @csrf
                        @method('PATCH')

                        <div class="mb-3">
                            <label for="password" class="form-label">Nuova Password</label>
                            <input type="password" class="form-control @error('password', 'password') is-invalid @enderror"
                                   id="password" name="password" autocomplete="new-password" required>
                            <div class="form-text">Minimo 8 caratteri</div>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Conferma Password</label>
                            <input type="password" class="form-control"
                                   id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
                        </div>

                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-key"></i> Cambia Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Card Elimina Utente -->
            @if($user->id !== Auth::id())
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <i class="bi bi-exclamation-triangle"></i> Zona Pericolosa
                </div>
                <div class="card-body">
                    <p class="mb-3">Eliminando questo utente verranno persi tutti i suoi dati.</p>
                    <form action="{{ route('admin.users.delete', $user) }}" method="POST"
                          onsubmit="return confirm('Sei sicuro di voler eliminare questo utente? Questa azione non può essere annullata.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Elimina Utente
                        </button>
                    </form>
                </div>
            </div>
            @endif

            <div class="text-muted mt-3">
                <small>
                    Creato: {{ $user->created_at->format('d/m/Y H:i') }} |
                    Ultima modifica: {{ $user->updated_at->format('d/m/Y H:i') }}
                </small>
            </div>
        </div>
    </div>
</div>
@endsection


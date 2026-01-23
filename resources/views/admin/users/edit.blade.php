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


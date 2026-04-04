@extends('layouts.app')

@section('title', 'Nuovo Utente')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-person-plus"></i> Crea Nuovo Utente</span>
                    <a href="{{ route('admin.users') }}" class="btn btn-sm btn-secondary">
                        <i class="bi bi-arrow-left"></i> Torna alla lista
                    </a>
                </div>

                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.users.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="name" class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" value="{{ old('name') }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                   id="email" name="email" value="{{ old('email') }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                   id="password" name="password" required>
                            <div class="form-text">Minimo 8 caratteri</div>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Conferma Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" 
                                   id="password_confirmation" name="password_confirmation" required>
                        </div>

                        <hr>

                        <h5>Ruoli e Permessi</h5>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="verified" name="verified" value="1"
                                       {{ old('verified') ? 'checked' : '' }}>
                                <label class="form-check-label" for="verified">
                                    <span class="badge bg-success">Email Verificata</span>
                                    <small class="text-muted d-block">Segna l'account come già verificato</small>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="admin" name="admin" value="1"
                                       {{ old('admin') ? 'checked' : '' }}>
                                <label class="form-check-label" for="admin">
                                    <span class="badge bg-danger">Amministratore</span>
                                    <small class="text-muted d-block">Accesso completo al pannello admin</small>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="master" name="master" value="1"
                                       {{ old('master') ? 'checked' : '' }}>
                                <label class="form-check-label" for="master">
                                    <span class="badge bg-warning">Master</span>
                                    <small class="text-muted d-block">Dungeon Master (Accesso completo e Note)</small>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="master_utils" name="master_utils" value="1"
                                       {{ old('master_utils') ? 'checked' : '' }}>
                                <label class="form-check-label" for="master_utils">
                                    <span class="badge bg-secondary">Master Utils</span>
                                    <small class="text-muted d-block">Accesso a Tracker e Sessioni (Senza Note)</small>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="showEmbedLink" name="showEmbedLink" value="1"
                                       {{ old('showEmbedLink') ? 'checked' : '' }}>
                                <label class="form-check-label" for="showEmbedLink">
                                    <span class="badge bg-info">Mostra Link Embed</span>
                                    <small class="text-muted d-block">Visualizza i link per embed nelle note</small>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="collapseEmbed" name="collapseEmbed" value="1"
                                       {{ old('collapseEmbed') ? 'checked' : '' }}>
                                <label class="form-check-label" for="collapseEmbed">
                                    <span class="badge bg-secondary">Comprimi Embed di Default</span>
                                    <small class="text-muted d-block">Il contenuto degli embed apparirà nascosto inizialmente</small>
                                </label>
                            </div>
                        </div>

                        <hr>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-check-lg"></i> Crea Utente
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


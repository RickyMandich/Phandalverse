@extends('layouts.app')

@section('title', 'Modifica Utente')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-pencil"></i> Modifica Utente: {{ $user->name }}</span>
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

                    <form method="POST" action="{{ route('admin.users.update', $user) }}">
                        @csrf
                        @method('PATCH')

                        <div class="mb-3">
                            <label for="name" class="form-label">Nome</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" value="{{ old('name', $user->name) }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                   id="email" name="email" value="{{ old('email', $user->email) }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Nuova Password</label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                   id="password" name="password">
                            <div class="form-text">Lascia vuoto per mantenere la password attuale</div>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Conferma Password</label>
                            <input type="password" class="form-control" 
                                   id="password_confirmation" name="password_confirmation">
                        </div>

                        <hr>

                        <h5>Ruoli e Permessi</h5>

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
                                    <small class="text-muted d-block">Ruolo Master (DM)</small>
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

                        <hr>

                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> Salva Modifiche
                            </button>
                            
                            @if($user->id !== Auth::id())
                            <button type="button" class="btn btn-danger" 
                                    onclick="if(confirm('Sei sicuro di voler eliminare questo utente?')) { document.getElementById('delete-form').submit(); }">
                                <i class="bi bi-trash"></i> Elimina Utente
                            </button>
                            @endif
                        </div>
                    </form>

                    @if($user->id !== Auth::id())
                    <form id="delete-form" action="{{ route('admin.users.delete', $user) }}" method="POST" class="d-none">
                        @csrf
                        @method('DELETE')
                    </form>
                    @endif
                </div>

                <div class="card-footer text-muted">
                    <small>
                        Creato: {{ $user->created_at->format('d/m/Y H:i') }} |
                        Ultima modifica: {{ $user->updated_at->format('d/m/Y H:i') }}
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


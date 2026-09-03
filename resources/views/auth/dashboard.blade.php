@extends('layouts.app')

@section('title', 'Il mio Profilo')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <!-- Card Info Utente -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-person-circle"></i> Il mio Profilo
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4 text-muted">Nome:</div>
                            <div class="col-md-8"><strong>{{ Auth::user()->name }}</strong></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 text-muted">Email:</div>
                            <div class="col-md-8"><strong>{{ Auth::user()->email }}</strong></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 text-muted">Ruoli:</div>
                            <div class="col-md-8">
                                @if(Auth::user()->isAdmin())
                                    <span class="badge bg-danger">Amministratore</span>
                                @endif
                                @if(Auth::user()->isMaster())
                                    <span class="badge bg-warning">Master</span>
                                @elseif(Auth::user()->master_utils)
                                    <span class="badge bg-info">Master Utils</span>
                                @endif
                                @if(!Auth::user()->isAdmin() && !Auth::user()->isMaster() && !Auth::user()->master_utils)
                                    <span class="badge bg-secondary">Utente</span>
                                @endif
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 text-muted">Membro dal:</div>
                            <div class="col-md-8">{{ Auth::user()->created_at->format('d/m/Y') }}</div>
                        </div>

                        @if(!Auth::user()->isMasterUtils())
                            <hr>
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h6>Strumenti da Master</h6>
                                    <p class="small text-muted mb-0">Richiedi l'accesso agli strumenti avanzati per i Dungeon
                                        Master.</p>
                                </div>
                                <div>
                                    @if(Auth::user()->master_request)
                                        <span class="badge bg-warning text-dark p-2">
                                            <i class="bi bi-clock-history"></i> Richiesta in attesa
                                        </span>
                                    @else
                                        <form action="{{ route('profile.request_master') }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-shield-lock"></i> Richiedi Accesso
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Card Modifica Profilo -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-pencil-square"></i> Modifica Profilo
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

                        <form method="POST" action="{{ route('profile.update') }}">
                            @csrf
                            @method('PATCH')

                            <div class="mb-3">
                                <label for="name" class="form-label">Nome</label>
                                <input type="text" class="form-control @error('name', 'profile') is-invalid @enderror"
                                    id="name" name="name" value="{{ old('name', Auth::user()->name) }}" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control @error('email', 'profile') is-invalid @enderror"
                                    id="email" name="email" value="{{ old('email', Auth::user()->email) }}" required>
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="showEmbedLink" name="showEmbedLink"
                                        value="1" {{ old('showEmbedLink', Auth::user()->showEmbedLink) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="showEmbedLink">
                                        Mostra link embed nelle note
                                    </label>
                                </div>
                            </div>

                            @if(isset($accessibleCampaigns) && $accessibleCampaigns->isNotEmpty())
                                <div class="mb-3">
                                    <label for="default_campaign_id" class="form-label">Campagna Predefinita</label>
                                    <select class="form-select @error('default_campaign_id', 'profile') is-invalid @enderror" id="default_campaign_id" name="default_campaign_id">
                                        <option value="">-- Nessuna (Usa ordine predefinito) --</option>
                                        @foreach($accessibleCampaigns as $camp)
                                            <option value="{{ $camp->id }}" {{ (string) old('default_campaign_id', Auth::user()->default_campaign_id) === (string) $camp->id ? 'selected' : '' }}>
                                                {{ $camp->display_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">La campagna che verrà aperta automaticamente all'accesso a /vault.</div>
                                </div>
                            @endif

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="collapseEmbed" name="collapseEmbed"
                                        value="1" {{ old('collapseEmbed', Auth::user()->collapseEmbed) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="collapseEmbed">
                                        Comprimi Embed di Default
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> Salva Modifiche
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Card Cambia Password -->
                <div class="card">
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

                        <form method="POST" action="{{ route('profile.password') }}">
                            @csrf
                            @method('PATCH')

                            <div class="mb-3">
                                <label for="current_password" class="form-label">Password Attuale</label>
                                <input type="password"
                                    class="form-control @error('current_password', 'password') is-invalid @enderror"
                                    id="current_password" name="current_password" required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Nuova Password</label>
                                <input type="password"
                                    class="form-control @error('password', 'password') is-invalid @enderror" id="password"
                                    name="password" required>
                                <div class="form-text">Minimo 8 caratteri</div>
                            </div>

                            <div class="mb-3">
                                <label for="password_confirmation" class="form-label">Conferma Nuova Password</label>
                                <input type="password" class="form-control" id="password_confirmation"
                                    name="password_confirmation" required>
                            </div>

                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-key"></i> Cambia Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
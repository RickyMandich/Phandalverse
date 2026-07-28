@extends('layouts.app')

@section('title', 'Gestione Utenti')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><i class="bi bi-people"></i> Gestione Utenti</h3>
            <a href="{{ route('admin.users.create') }}" class="btn btn-success">
                <i class="bi bi-plus-lg"></i> Nuovo Utente
            </a>
        </div>

        <!-- Ricerca -->
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.users') }}" class="row g-2">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" placeholder="Cerca per nome o email..."
                            value="{{ request('search') }}">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Cerca
                        </button>
                        @if(request('search'))
                            <a href="{{ route('admin.users') }}" class="btn btn-secondary">Reset</a>
                        @endif
                    </div>
                </form>
            </div>
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

        <!-- Tabella Utenti -->
        <div class="card">
            <div class="card-body table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Ruoli</th>
                            <th>Verificato</th>
                            <th>Mostra Embed Link</th>
                            <th>Collassa Embed</th>
                            <th>Registrato</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr>
                                <td>{{ $user->id }}</td>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    @if($user->admin)
                                        <span class="badge bg-danger">Admin</span>
                                    @endif
                                    @if($user->master)
                                        <span class="badge bg-warning">Master</span>
                                    @elseif($user->master_utils)
                                        <span class="badge bg-info">Master Utils</span>
                                    @endif
                                    @if(!$user->admin && !$user->master && !$user->master_utils)
                                        <span class="badge bg-secondary">Utente</span>
                                    @endif

                                    @if($user->master_request)
                                        <div class="mt-1">
                                            <span class="badge bg-primary animate-pulse">
                                                <i class="bi bi-star-fill"></i> Richiesta Master
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($user->email_verified_at)
                                        <span class="text-success" style="font-size: 1.2rem;"
                                            title="Verificato il {{ $user->email_verified_at->format('d/m/Y H:i') }}">
                                            <i class="bi bi-patch-check-fill"></i>
                                        </span>
                                    @else
                                        <span class="text-danger" style="font-size: 1.2rem;" title="Non verificato">
                                            <i class="bi bi-patch-exclamation-fill"></i>
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($user->showEmbedLink)
                                        <span class="text-success" style="font-size: 1.2rem;" title="Mostra link embed">
                                            <i class="bi bi-check-circle-fill"></i>
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($user->collapseEmbed)
                                        <span class="text-secondary" style="font-size: 1.2rem;" title="Comprimi Embed di Default">
                                            <i class="bi bi-check-circle-fill"></i>
                                        </span>
                                    @endif
                                </td>
                                <td>{{ $user->created_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary">
                                            <i class="bi bi-pencil"></i> Modifica
                                        </a>

                                        @if($user->master_request)
                                            <form action="{{ route('admin.users.approve_master', $user) }}" method="POST"
                                                class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-success" title="Approva Richiesta Master">
                                                    <i class="bi bi-person-check"></i>
                                                </button>
                                            </form>
                                            <form action="{{ route('admin.users.deny_master', $user) }}" method="POST"
                                                class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-warning" title="Nega Richiesta Master">
                                                    <i class="bi bi-person-x"></i>
                                                </button>
                                            </form>
                                        @endif

                                        @if($user->id !== Auth::id())
                                            <form action="{{ route('admin.users.delete', $user) }}" method="POST" class="d-inline"
                                                onsubmit="return confirm('Sei sicuro di voler eliminare questo utente?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center">Nessun utente trovato</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $users->links() }}
            </div>
        </div>
    </div>
@endsection
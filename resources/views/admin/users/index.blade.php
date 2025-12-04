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
                    <input type="text" name="search" class="form-control" 
                           placeholder="Cerca per nome o email..." 
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
                        <th>mostra Embed link</th>
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
                            @endif
                            @if(!$user->admin && !$user->master)
                                <span class="badge bg-secondary">Utente</span>
                            @endif
                        </td>
                        <td>
                            @if($user->showEmbedLink)
                                <span class="text-success" style="font-size: 2rem;" title="Mostra link embed">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-circle" viewBox="0 0 16 16">
                                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                                        <path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.05-1.05z"/>
                                    </svg>
                                </span>
                            @endif
                        </td>
                        <td>{{ $user->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-primary">
                                <i class="bi bi-pencil"></i> Modifica
                            </a>
                            @if($user->id !== Auth::id())
                            <form action="{{ route('admin.users.delete', $user) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('Sei sicuro di voler eliminare questo utente?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="bi bi-trash"></i> Elimina
                                </button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center">Nessun utente trovato</td>
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


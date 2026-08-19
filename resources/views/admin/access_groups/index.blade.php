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
                <li><strong>Nota intera riservata:</strong> inserisci <code>#access:slug-gruppo</code> o <code>#access:gruppo1|gruppo2</code> in testa alla nota.</li>
                <li><strong>Blocco riservato parziale:</strong> racchiudi il testo tra <code>#startAccess:slug-gruppo</code> e <code>#endAccess</code> (o con <code>|</code> per più gruppi in OR).</li>
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
                        <th>Nome</th>
                        <th>Slug Markdown</th>
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
                                <strong>{{ $group->name }}</strong>
                            </td>
                            <td>
                                <code>#access:{{ $group->slug }}</code>
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
                            <td colspan="7" class="text-center text-muted py-4">Nessun gruppo di accesso configurato.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

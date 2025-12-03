@extends('layouts.app')

@section('title', 'Errori di Sistema')

@section('content')
<div class="container-fluid">
    <h3><i class="bi bi-bug"></i> Errori di Sistema</h3>

    <!-- Filtri -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.errors') }}" class="row g-2">
                <div class="col-auto">
                    <select name="status" class="form-select">
                        <option value="all" {{ request('status') == 'all' ? 'selected' : '' }}>Tutti</option>
                        <option value="new" {{ request('status') == 'new' ? 'selected' : '' }}>Nuovi</option>
                        <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In Lavorazione</option>
                        <option value="resolved" {{ request('status') == 'resolved' ? 'selected' : '' }}>Risolti</option>
                        <option value="ignored" {{ request('status') == 'ignored' ? 'selected' : '' }}>Ignorati</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Filtra</button>
                </div>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <!-- Tabella Errori -->
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tipo</th>
                        <th>Messaggio</th>
                        <th>Stato</th>
                        <th>Data</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($errors as $error)
                    <tr>
                        <td>{{ $error->id }}</td>
                        <td><code>{{ $error->short_class_name }}</code></td>
                        <td class="text-truncate" style="max-width: 300px;">{{ $error->message }}</td>
                        <td>
                            @switch($error->status)
                                @case('new')
                                    <span class="badge bg-danger">Nuovo</span>
                                    @break
                                @case('in_progress')
                                    <span class="badge bg-warning">In Lavorazione</span>
                                    @break
                                @case('resolved')
                                    <span class="badge bg-success">Risolto</span>
                                    @break
                                @case('ignored')
                                    <span class="badge bg-secondary">Ignorato</span>
                                    @break
                            @endswitch
                        </td>
                        <td>{{ $error->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            <a href="{{ route('admin.errors.show', $error) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center">Nessun errore trovato</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $errors->links() }}
        </div>
    </div>
</div>
@endsection


@extends('layouts.app')

@section('title', 'Segnalazioni')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>📢 Segnalazioni</h2>
    </div>

    {{-- Filtri --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Stato</label>
                    <select name="status" class="form-select">
                        <option value="all" {{ request('status') === 'all' ? 'selected' : '' }}>Tutti</option>
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}" {{ request('status') === $key || (!request('status') && $key === 'pending') ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Categoria</label>
                    <select name="category" class="form-select">
                        <option value="">Tutte</option>
                        @foreach($categories as $key => $label)
                            <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filtra</button>
                    <a href="{{ route('admin.reports') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Lista segnalazioni --}}
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Categoria</th>
                        <th>Descrizione</th>
                        <th>Utente</th>
                        <th>Stato</th>
                        <th>Data</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $report)
                        <tr>
                            <td>{{ $report->id }}</td>
                            <td>
                                @if($report->category === 'logic')
                                    🧠
                                @elseif($report->category === 'display')
                                    👁️
                                @elseif($report->category === 'content')
                                    📝
                                @else
                                    ❓
                                @endif
                                {{ $report->category_label }}
                            </td>
                            <td>
                                {{ Str::limit($report->description, 50) }}
                                @if($report->has_images)
                                    <i class="bi bi-camera text-muted ms-1" title="Contiene immagini"></i>
                                @endif
                            </td>
                            <td>{{ $report->user?->name ?? $report->email }}</td>
                            <td>
                                @php
                                    $statusColors = [
                                        'pending' => 'warning',
                                        'in_progress' => 'info',
                                        'resolved' => 'success',
                                        'rejected' => 'danger',
                                    ];
                                @endphp
                                <span class="badge bg-{{ $statusColors[$report->status] ?? 'secondary' }}">
                                    {{ $report->status_label }}
                                </span>
                            </td>
                            <td>{{ $report->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="{{ route('admin.reports.show', $report) }}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-eye"></i> Vedi
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                Nessuna segnalazione trovata.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $reports->links() }}
    </div>
@endsection
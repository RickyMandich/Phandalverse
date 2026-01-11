@extends('layouts.app')

@section('title', 'Segnalazione #' . $report->id)

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    @if($report->category === 'logic')
                        🧠
                    @elseif($report->category === 'display')
                        👁️
                    @else
                        📝
                    @endif
                    {{ $report->category_label }}
                </h5>
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
            </div>
            <div class="card-body">
                <h6 class="text-muted mb-2">Descrizione</h6>
                <p class="mb-4" style="white-space: pre-wrap;">{{ $report->description }}</p>

                @if($report->page_urls)
                <h6 class="text-muted mb-2">Pagine coinvolte</h6>
                <ul class="mb-4">
                    @foreach($report->page_urls as $url)
                    <li><a href="{{ $url }}" target="_blank">{{ $url }}</a></li>
                    @endforeach
                </ul>
                @endif

                <div class="row text-muted small">
                    <div class="col-md-6">
                        <strong>Segnalato da:</strong> {{ $report->user?->name ?? $report->email }}
                    </div>
                    <div class="col-md-6">
                        <strong>Data:</strong> {{ $report->created_at->format('d/m/Y H:i') }}
                    </div>
                </div>
            </div>
        </div>

        @if($report->admin_notes)
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">📝 Note Admin</h6>
            </div>
            <div class="card-body">
                <p style="white-space: pre-wrap;">{{ $report->admin_notes }}</p>
            </div>
        </div>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Gestisci segnalazione</h6>
            </div>
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <form action="{{ route('admin.reports.update', $report) }}" method="POST">
                    @csrf
                    @method('PATCH')

                    <div class="mb-3">
                        <label class="form-label">Stato</label>
                        <select name="status" class="form-select">
                            @foreach($statuses as $key => $label)
                                <option value="{{ $key }}" {{ $report->status === $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Note Admin</label>
                        <textarea name="admin_notes" class="form-control" rows="4">{{ $report->admin_notes }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        💾 Salva modifiche
                    </button>
                </form>
            </div>
        </div>

        <a href="{{ route('admin.reports') }}" class="btn btn-secondary w-100 mt-3">
            <i class="bi bi-arrow-left"></i> Torna alla lista
        </a>
    </div>
</div>
@endsection


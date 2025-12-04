@extends('layouts.app')

@section('title', 'Dettaglio Errore #' . $error->id)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><i class="bi bi-bug"></i> Errore #{{ $error->id }}</h3>
        <a href="{{ route('admin.errors') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Torna alla lista
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <!-- Informazioni principali -->
    <div class="card mb-3">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> {{ $error->short_class_name }}</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <strong>Messaggio:</strong>
                    <p class="text-danger">{{ $error->message }}</p>
                </div>
                <div class="col-md-6">
                    <strong>File:</strong>
                    <p><code>{{ $error->file }}:{{ $error->line }}</code></p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <strong>URL:</strong>
                    <p><code>{{ $error->request_url ?? 'N/A' }}</code></p>
                </div>
                <div class="col-md-4">
                    <strong>Metodo:</strong>
                    <p><span class="badge bg-primary">{{ $error->request_method ?? 'N/A' }}</span></p>
                </div>
                <div class="col-md-4">
                    <strong>Data:</strong>
                    <p>{{ $error->created_at->format('d/m/Y H:i:s') }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Codice Sorgente -->
    @php
        $codeSnippet = $error->getCodeSnippet(5);
    @endphp
    @if($codeSnippet)
    <div class="card mb-3">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="bi bi-code-slash"></i> Codice Sorgente
                <small class="text-muted ms-2">{{ $error->relative_file }}</small>
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="overflow-auto">
                <table class="table table-sm table-dark mb-0 font-monospace" style="font-size: 0.85rem;">
                    <tbody>
                        @foreach($codeSnippet['lines'] as $lineNum => $code)
                            <tr class="{{ $lineNum == $codeSnippet['error_line'] ? 'bg-danger' : '' }}">
                                <td class="text-end pe-3 text-muted border-end" style="width: 50px; user-select: none;">
                                    {{ $lineNum }}
                                </td>
                                <td class="ps-3">
                                    <pre class="mb-0" style="white-space: pre-wrap; word-break: break-word;">{{ $code }}</pre>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- Stack Trace -->
    <div class="card mb-3">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-list"></i> Stack Trace</h6>
        </div>
        <div class="card-body">
            <div class="overflow-auto" style="max-height: 400px;">
                <pre class="bg-dark text-light p-3 small">{{ $error->trace }}</pre>
            </div>
        </div>
    </div>

    <!-- Gestione Errore -->
    <div class="card mb-3">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-gear"></i> Gestione Errore</h6>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.errors.update', $error) }}">
                @csrf
                @method('PATCH')
                
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Stato</label>
                        <select name="status" class="form-select">
                            <option value="new" {{ $error->status == 'new' ? 'selected' : '' }}>Nuovo</option>
                            <option value="in_progress" {{ $error->status == 'in_progress' ? 'selected' : '' }}>In Lavorazione</option>
                            <option value="resolved" {{ $error->status == 'resolved' ? 'selected' : '' }}>Risolto</option>
                            <option value="ignored" {{ $error->status == 'ignored' ? 'selected' : '' }}>Ignorato</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Note Admin</label>
                        <textarea name="admin_notes" class="form-control" rows="2">{{ $error->admin_notes }}</textarea>
                    </div>
                </div>
                
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Aggiorna</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Azioni Rapide -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-lightning"></i> Azioni Rapide</h6>
        </div>
        <div class="card-body">
            <a href="{{ route('admin.errors.quick-action', [$error, 'resolved']) }}" class="btn btn-success me-2">
                <i class="bi bi-check-circle"></i> Segna come Risolto
            </a>
            <a href="{{ route('admin.errors.quick-action', [$error, 'ignored']) }}" class="btn btn-secondary me-2">
                <i class="bi bi-x-circle"></i> Segna come Ignorato
            </a>
            <a href="{{ route('admin.errors.quick-action', [$error, 'in_progress']) }}" class="btn btn-warning">
                <i class="bi bi-clock"></i> Segna In Lavorazione
            </a>
        </div>
    </div>
</div>
@endsection


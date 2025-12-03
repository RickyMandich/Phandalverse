@extends('layouts.app')

@section('title', 'Log Laravel - Sessioni')

@section('content')
<div class="container-fluid">
    <h3><i class="fas fa-file-alt"></i> {{ $fileName }} - Sessioni di Esecuzione</h3>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            @foreach($breadcrumbs as $crumb)
                @if($loop->last)
                    <li class="breadcrumb-item active">{{ $crumb['name'] }}</li>
                @else
                    <li class="breadcrumb-item">
                        <a href="{{ route('admin.logs', ['path' => $crumb['path']]) }}">{{ $crumb['name'] }}</a>
                    </li>
                @endif
            @endforeach
            <li class="breadcrumb-item active">{{ $fileName }}</li>
        </ol>
    </nav>

    <div class="alert alert-info mb-3">
        <i class="fas fa-info-circle"></i>
        <strong>Visualizzazione per sessioni:</strong> Ogni card rappresenta una singola esecuzione dell'applicazione.
        Le sessioni sono ordinate dalla più recente alla più vecchia.
    </div>

    <div class="row">
        @forelse($sessions as $index => $session)
            <div class="col-12 mb-3">
                <div class="card {{ $session['status'] === 'crashed' ? 'border-danger' : ($session['has_errors'] ? 'border-warning' : 'border-success') }}">
                    <div class="card-header d-flex justify-content-between align-items-center 
                        {{ $session['status'] === 'crashed' ? 'bg-danger text-white' : ($session['has_errors'] ? 'bg-warning text-dark' : 'bg-success text-white') }}">
                        <div>
                            @if($session['status'] === 'crashed')
                                <i class="fas fa-skull-crossbones"></i> <strong>CRASH</strong>
                            @elseif($session['status'] === 'incomplete')
                                <i class="fas fa-hourglass-half"></i> <strong>In corso...</strong>
                            @elseif($session['status'] === 'legacy')
                                <i class="fas fa-history"></i> <strong>Legacy</strong>
                            @elseif($session['has_errors'])
                                <i class="fas fa-exclamation-triangle"></i> <strong>Con errori</strong>
                            @else
                                <i class="fas fa-check-circle"></i> <strong>OK</strong>
                            @endif
                            
                            <span class="ms-2">{{ $session['request'] }}</span>
                        </div>
                        <div class="text-end small">
                            @if($session['start_time'])
                                <span class="me-3"><i class="fas fa-clock"></i> {{ $session['start_time'] }}</span>
                            @endif
                            <span class="badge bg-light text-dark">
                                {{ strlen($session['content'] ?? '') }} bytes
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted">
                                    ID: <code>{{ Str::limit($session['id'], 8) }}</code>
                                    @if($session['end_time'])
                                        | Fine: {{ $session['end_time'] }}
                                    @endif
                                </small>
                            </div>
                            <a href="{{ route('admin.logs', ['path' => $currentPath, 'session' => $index]) }}" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i> Visualizza Log
                            </a>
                        </div>

                        @if($session['has_errors'] || $session['status'] === 'crashed')
                            <div class="mt-2">
                                <small class="text-danger">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Questa sessione contiene errori. Clicca per vedere i dettagli.
                                </small>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-secondary">
                    <i class="fas fa-inbox"></i> Nessuna sessione di log trovata.
                </div>
            </div>
        @endforelse
    </div>

    <!-- Legenda -->
    <div class="card mt-4">
        <div class="card-header">
            <i class="fas fa-question-circle"></i> Legenda Stati
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <span class="badge bg-success">OK</span>
                    Esecuzione completata senza errori
                </div>
                <div class="col-md-3">
                    <span class="badge bg-warning text-dark">Con errori</span>
                    Completata ma con errori loggati
                </div>
                <div class="col-md-3">
                    <span class="badge bg-danger">CRASH</span>
                    Terminata con errore fatale
                </div>
                <div class="col-md-3">
                    <span class="badge bg-secondary">Legacy</span>
                    Log precedenti al sistema di tracciamento
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


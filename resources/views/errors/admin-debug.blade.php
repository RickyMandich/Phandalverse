@extends('layouts.app')

@section('title', 'Errore - Debug Admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="alert alert-danger mb-3">
                <h4><i class="fas fa-exclamation-triangle"></i> Errore Admin Debug</h4>
                <p class="mb-0">Stai visualizzando questa pagina perché sei un amministratore. Gli utenti normali vedono una pagina di errore semplificata.</p>
            </div>

            <!-- Informazioni principali dell'errore -->
            <div class="card mb-3">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-bug"></i> {{ get_class($exception) }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Messaggio:</strong>
                            <p class="text-danger">{{ $message }}</p>
                        </div>
                        <div class="col-md-6">
                            <strong>File:</strong>
                            <p><code class="text-muted">{{ $file }}:{{ $line }}</code></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stack Trace -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-list"></i> Stack Trace</h6>
                </div>
                <div class="card-body">
                    <div class="overflow-auto" style="max-height: 400px;">
                        <pre class="bg-dark text-light p-3 small">{{ $trace }}</pre>
                    </div>
                </div>
            </div>

            <!-- Informazioni Request -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-globe"></i> Informazioni Request</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <strong>URL:</strong>
                            <p><code class="text-muted">{{ $request->fullUrl() }}</code></p>
                        </div>
                        <div class="col-md-4">
                            <strong>Metodo:</strong>
                            <p><span class="badge bg-primary">{{ $request->method() }}</span></p>
                        </div>
                        <div class="col-md-4">
                            <strong>IP:</strong>
                            <p><code class="text-muted">{{ $request->ip() }}</code></p>
                        </div>
                    </div>

                    @if($request->all())
                    <div class="mt-3">
                        <strong>Parametri:</strong>
                        <div class="overflow-auto mt-2" style="max-height: 200px;">
                            <pre class="p-2">{{ json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Headers -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-tags"></i> Headers</h6>
                </div>
                <div class="card-body">
                    <div class="overflow-auto" style="max-height: 300px;">
                        <pre class="p-2 small">{{ json_encode($request->headers->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

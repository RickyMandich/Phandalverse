@extends('layouts.app')

@section('title', 'Test Invio Mail')

@section('content')
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
                        <h5 class="mb-0"><i class="bi bi-envelope-check"></i> Stato Invio Mail di Test</h5>
                        <a href="{{ route('admin.logs') }}" class="btn btn-sm shadow-sm">
                            <i class="bi bi-file-text"></i> Vedi Log
                        </a>
                    </div>
                    <div class="card-body p-4">
                        @if(isset($success))
                            <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
                                <i class="bi bi-check-circle-fill me-2 fs-4"></i>
                                <div>{{ $success }}</div>
                            </div>
                        @endif

                        @if(isset($error))
                            <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2 fs-4"></i>
                                <div>{{ $error }}</div>
                            </div>
                        @endif

                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-muted small">DESTINATARIO</label>
                                <div class="p-2 border rounded">
                                    <i class="bi bi-person"></i> {{ $email }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-muted small">MAILER IN USO</label>
                                <div class="p-2 border rounded">
                                    <i class="bi bi-gear"></i>
                                    {{ config('mail.mailers.' . config('mail.default') . '.transport', config('mail.default')) }}
                                </div>
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded border mb-4">
                            <h6 class="fw-bold mb-2">Dettagli della Coda:</h6>
                            <p class="small text-muted mb-0">
                                La mail è stata inserita nella tabella <code>jobs</code> e il processore asincrono "Fire and
                                Forget" è stato attivato.
                                <br><br>
                                Ricorda che su Altervista l'invio rispetta un limite di <strong>1 mail al secondo</strong>
                                per evitare blocchi dell'account.
                            </p>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                            <a href="{{ route('admin.test-mail') }}" class="btn btn-primary px-4">
                                <i class="bi bi-send-plus"></i> Invia un altro test
                            </a>
                            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary px-4">
                                Torna alla Dashboard
                            </a>
                        </div>
                    </div>
                    <div class="card-footer bg-white text-center py-3">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> Se la mail non arriva, controlla
                            <code>storage/logs/mail/send_...log</code>.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
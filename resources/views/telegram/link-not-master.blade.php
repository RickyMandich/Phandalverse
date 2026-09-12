@extends('layouts.app')

@section('title', 'Solo un Master')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-warning">
                <div class="card-body text-center py-5">
                    <div class="display-1 mb-3">🔒</div>
                    <h3 class="card-title">Solo un Master può completare questa operazione</h3>
                    <p class="card-text text-muted">
                        Il collegamento di un gruppo Telegram a una campagna può essere effettuato solo da un
                        utente con ruolo Master. Se pensi di doverlo essere, contatta un amministratore.
                    </p>
                    <a href="{{ route('dashboard') }}" class="btn btn-primary mt-3">
                        <i class="bi bi-house"></i> Torna al sito
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

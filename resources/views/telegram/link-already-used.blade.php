@extends('layouts.app')

@section('title', 'Account già collegato')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-warning">
                <div class="card-body text-center py-5">
                    <div class="display-1 mb-3">⚠️</div>
                    <h3 class="card-title">Questo account Telegram è già collegato</h3>
                    <p class="card-text text-muted">
                        L'account Telegram che hai usato per generare questo link risulta già collegato a un altro
                        utente del sito. Contatta un amministratore se ritieni sia un errore.
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

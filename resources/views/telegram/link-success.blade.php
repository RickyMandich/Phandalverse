@extends('layouts.app')

@section('title', 'Account collegato')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-success">
                <div class="card-body text-center py-5">
                    <div class="display-1 mb-3">✅</div>
                    <h3 class="card-title">Account collegato con successo!</h3>
                    <p class="card-text text-muted">
                        Il tuo account Telegram è ora collegato a <strong>{{ $user->name }}</strong>.
                        Puoi tornare su Telegram e usare <code>/subscribe</code> per attivare le notifiche.
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

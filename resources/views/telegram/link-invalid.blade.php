@extends('layouts.app')

@section('title', 'Link non valido')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-danger">
                <div class="card-body text-center py-5">
                    <div class="display-1 mb-3">⌛</div>
                    <h3 class="card-title">Link scaduto o non valido</h3>
                    <p class="card-text text-muted">
                        Questo link di collegamento non è più valido: potrebbe essere già stato usato oppure
                        aver superato l'ora di validità.
                    </p>
                    <p class="card-text">
                        Torna su Telegram ed esegui di nuovo <code>/link</code> per generarne uno nuovo.
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

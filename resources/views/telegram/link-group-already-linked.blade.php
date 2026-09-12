@extends('layouts.app')

@section('title', 'Campagna già collegata')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-warning">
                <div class="card-body text-center py-5">
                    <div class="display-1 mb-3">⚠️</div>
                    <h3 class="card-title">Campagna già collegata a un altro gruppo</h3>
                    <p class="card-text text-muted">
                        La campagna <strong>{{ $campaign->display_name }}</strong> è già collegata a un altro
                        gruppo Telegram. Per collegarla a questo gruppo, scollegala prima dall'altro gruppo
                        (con <code>/unlink</code> in quel gruppo, oppure dal pannello Admin &rarr; Campagne).
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

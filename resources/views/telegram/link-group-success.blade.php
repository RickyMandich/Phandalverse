@extends('layouts.app')

@section('title', 'Gruppo collegato')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-success">
                <div class="card-body text-center py-5">
                    <div class="display-1 mb-3">✅</div>
                    <h3 class="card-title">Gruppo collegato con successo!</h3>
                    <p class="card-text text-muted">
                        Il gruppo Telegram è ora collegato alla campagna <strong>{{ $campaign->display_name }}</strong>.
                        I membri del gruppo possono usare <code>/subscribe</code> per attivare le notifiche.
                    </p>
                    <a href="{{ route('admin.campaigns') }}" class="btn btn-primary mt-3">
                        <i class="bi bi-compass"></i> Vai a Gestione Campagne
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

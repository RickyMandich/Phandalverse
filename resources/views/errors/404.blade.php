@extends('errors.maintanence')
@section('code', '404')
@section('message')
    Questa Nota non esiste
    <div class="mt-4 text-center">
        <a href="{{ route('vault.index') }}" class="btn btn-primary btn-lg">
            <i class="bi bi-folder2-open"></i> {{ __('VaultWelcome') }}
        </a>
    </div>
@endsection
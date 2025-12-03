@extends('layouts.app')
@section('content')
<h1>
    Benvenuto in questo sito
</h1>

<div class="mt-4">
    <a href="{{ route('vault.show') }}" class="btn btn-primary btn-lg">
        <i class="bi bi-folder2-open"></i> Esplora il Vault
    </a>
</div>
@endsection
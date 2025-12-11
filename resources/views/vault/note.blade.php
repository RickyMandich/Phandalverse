@extends('layouts.app')
@section('title', $title)
@section('style')
    .wikilink {
        @extend .text-muted;
        @extend .text-uppercase;
    }
@endsection
@section('content')
<div class="vault-layout d-flex">
    @include('vault._sidebar', ['tree' => $tree ?? []])

    <main class="vault-main flex-grow-1 p-4">
        <h1>{{ $title }}</h1>
        <div class="vault-note">
            {!! $html !!}
        </div>
    </main>
</div>
@endsection
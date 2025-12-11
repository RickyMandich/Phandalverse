@extends('layouts.app')
@section('title', $title)
@section('style')
<style>
    /* Stile per blocchi master resi visibili solo a chi li può vedere */
    .master-block {
        /* background-color: #f3e8ff; */
        border: 4px solid #8a2be2;
        border-radius: 4px;
    }

    .master-block .master-block-content {
        color: inherit;
    }

    /* Piccola etichetta opzionale a lato (non invasiva) */
    /* .master-block::before {
        content: "MASTER";
        display: inline-block;
        font-size: 0.75rem;
        font-weight: 600;
        color: #8a2be2;
        margin-right: 0.6rem;
        vertical-align: middle;
        opacity: 0.9;
    } */
</style>
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
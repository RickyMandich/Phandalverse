@extends('layouts.app')
@section('title', $title)
@section('content')
    <div class="d-flex gap-0 h-100 w-100">
        @include('vault._sidebar', ['tree' => $tree ?? [], 'path' => $path ?? []])

        <main class="flex-grow-1 p-4" style="min-width: 0;">
            <h1 class="display-5 mb-4 border-bottom pb-2 text-warning master-block">{{ $title }}</h1>
            <div class="vault-note typography">
                {!! $html !!}
            </div>
        </main>
    </div>
@endsection
@extends('layouts.app')
@section('title', $title)
@section('content')
    @php
        \App\Services\CustomLogger::note($note, "-------------------inizio view nota-------------------");
        \App\Services\CustomLogger::note($note, print_r($path, true));
    @endphp
    <div class="d-flex gap-0 h-100 w-100">
        @include('vault._sidebar', ['tree' => $tree ?? [], 'path' => $path ?? [], 'masterFile' => $masterFile ?? false, 'note' => $note ?? null])

        <section class="flex-grow-1 p-4" style="min-width: 0;">
            <div class="title-container">
                <h1 class="title">{{ $title }}</h1>
                @if ($masterFile)
                    <div class="master-block tag">Master</div>
                @endif
            </div>
            <div class="vault-note typography">
                {!! $html !!}
            </div>
        </section>
    </div>
    @php
        \App\Services\CustomLogger::note($note, "-------------------fine view nota-------------------");
    @endphp
@endsection
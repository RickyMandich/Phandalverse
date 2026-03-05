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
                <h1 class="display-5 mb-4 border-bottom pb-2 text-warning fw-bold">{{ $title }}</h1>
                <div class="d-flex align-items-center gap-2 mb-4">
                    @if ($masterFile)
                        <div class="master-block tag">Master</div>
                    @endif
                    @if (Auth::check() && Auth::isMaster())
                        <a href="{{ route('vault.raw', ['note' => $note]) }}" class="btn btn-sm btn-outline-warning"
                            title="Scarica Markdown">
                            <i class="bi bi-download"></i> Scarica MD
                        </a>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="switchCheckMaster" checked>
                            <label class="form-check-label custom-text-test" for="switchCheckMaster">Mostra contenuto da
                                Master</label>
                        </div>
                    @endif
                </div>
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
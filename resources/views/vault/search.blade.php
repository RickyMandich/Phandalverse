@extends('layouts.app')

@section('title', 'Ricerca: ' . ($query ?? '...'))

@section('content-class', 'container-fluid px-0')

@section('content')
    <div class="d-flex gap-0 h-100 w-100">
        @include('vault._sidebar', ['tree' => $tree, 'note' => $note, 'campaign' => $campaign, 'accessibleCampaigns' => $accessibleCampaigns])

        <main class="flex-grow-1 p-4" style="min-width: 0;">
            <div class="search-results-page">
                <h1 class="display-5 mb-4 border-bottom pb-2 text-warning">
                    <i class="bi bi-search"></i> Risultati della ricerca: <span class="text-white">"{{ $query }}"</span>
                </h1>

                @if(empty($resultsByCampaign))
                    <div class="alert alert-info bg-dark border-secondary text-white">
                        <i class="bi bi-info-circle"></i> Nessuna nota trovata per questa ricerca.
                    </div>
                @else
                    <div class="mb-3 text-muted">
                        Trovate {{ $totalResults }} note
                    </div>

                    @foreach($resultsByCampaign as $group)
                        @if(count($resultsByCampaign) > 1)
                            <h4 class="text-info mt-4 mb-2 border-bottom border-secondary pb-1">
                                <i class="bi bi-collection"></i> {{ $group['campaign']->display_name }}
                            </h4>
                        @endif

                        <div class="list-group list-group-flush mb-3">
                            @foreach($group['results'] as $result)
                                <a href="{{ route('vault.show', ['campaign' => $group['campaign']->folder_name, 'note' => $result['url']]) }}"
                                    class="list-group-item list-group-item-action bg-dark border-secondary text-white mb-2 rounded border">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <h5 class="mb-1 text-warning d-flex align-items-center">
                                            @if(!empty($result['is_pdf']))
                                                <i class="bi bi-file-earmark-pdf-fill text-danger me-2" title="Documento PDF"></i>
                                            @else
                                                <i class="bi bi-file-earmark-text text-secondary me-2" title="Nota Markdown"></i>
                                            @endif
                                            @php
                                                $highlighted = preg_replace('/(' . preg_quote($query, '/') . ')/i', '<span class="bg-warning text-dark px-1">$1</span>', e($result['original']));
                                            @endphp
                                            {!! $highlighted !!}
                                        </h5>
                                        <small class="text-info">
                                            @if(!empty($result['is_pdf']))
                                                <i class="bi bi-file-earmark-pdf"></i> Visualizza PDF
                                            @else
                                                <i class="bi bi-file-text"></i> Visualizza Nota
                                            @endif
                                        </small>
                                    </div>
                                    <p class="mb-1 small text-muted">
                                        <i class="bi bi-folder"></i>
                                        @if($result['directory'])
                                            {{ $result['directory'] }}
                                        @else
                                            <span class="font-italic">Radice del Vault</span>
                                        @endif
                                    </p>
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                @endif
            </div>
        </main>
    </div>
@endsection
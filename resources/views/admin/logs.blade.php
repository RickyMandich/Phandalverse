@extends('layouts.app')

@section('title', 'Log di Sistema')

@section('content')
<div class="container-fluid">
    <h3><i class="bi bi-file-text"></i> Log di Sistema</h3>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            @foreach($breadcrumbs as $crumb)
                @if($loop->last)
                    <li class="breadcrumb-item active">{{ $crumb['name'] }}</li>
                @else
                    <li class="breadcrumb-item">
                        <a href="{{ route('admin.logs', ['path' => $crumb['path']]) }}">{{ $crumb['name'] }}</a>
                    </li>
                @endif
            @endforeach
        </ol>
    </nav>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if($isFile)
        <!-- Visualizzazione File -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-file-code"></i> {{ $fileName }}</span>

                @isset($sessionInfo)
                    {{-- Pulsante per tornare alla lista sessioni --}}
                    <a href="{{ route('admin.logs', ['path' => $currentPath]) }}" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Torna alle Sessioni
                    </a>
                @endisset
            </div>

            @isset($sessionInfo)
                {{-- Info sessione --}}
                <div class="card-body border-bottom py-2 bg-light">
                    <div class="row small">
                        <div class="col-md-3">
                            <strong>Stato:</strong>
                            @if($sessionInfo['status'] === 'crashed')
                                <span class="badge bg-danger">CRASH</span>
                            @elseif($sessionInfo['status'] === 'incomplete')
                                <span class="badge bg-warning text-dark">In corso</span>
                            @elseif($sessionInfo['has_errors'])
                                <span class="badge bg-warning text-dark">Con errori</span>
                            @else
                                <span class="badge bg-success">Completata</span>
                            @endif
                        </div>
                        <div class="col-md-3">
                            <strong>Request:</strong> {{ $sessionInfo['request'] }}
                        </div>
                        <div class="col-md-3">
                            <strong>Inizio:</strong> {{ $sessionInfo['start_time'] ?? 'N/A' }}
                        </div>
                        <div class="col-md-3">
                            <strong>Fine:</strong> {{ $sessionInfo['end_time'] ?? 'N/A' }}
                        </div>
                    </div>
                </div>
            @endisset

            <div class="card-body">
                <pre class="bg-dark text-light p-3" style="max-height: 600px; overflow: auto;">{{ $fileContent }}</pre>
            </div>
        </div>
    @else
        <!-- Lista Directory -->
        <div class="card">
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Dimensione</th>
                            <th>Modificato</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contents as $item)
                        <tr>
                            <td>
                                <a href="{{ route('admin.logs', ['path' => $item['path']]) }}">
                                    @if($item['type'] === 'directory')
                                        <i class="bi bi-folder-fill text-warning"></i>
                                    @else
                                        <i class="bi bi-file-text text-secondary"></i>
                                    @endif
                                    {{ $item['name'] }}
                                </a>
                            </td>
                            <td>{{ $item['size'] ? number_format($item['size'] / 1024, 2) . ' KB' : '-' }}</td>
                            <td>{{ date('d/m/Y H:i', $item['modified']) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center">Nessun file trovato</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection


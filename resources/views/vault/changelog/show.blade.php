@extends('layouts.app')

@section('title', "Versione {$changelog['version']} - Vault")

@section('content-class', 'container-fluid px-0')

@section('style')
    <style>
        .changelog-container {
            background: #121212;
            min-height: calc(100vh - 56px);
            color: #e0e0e0;
        }

        .breadcrumb-custom {
            margin-bottom: 2rem;
            font-size: 0.9rem;
        }

        .breadcrumb-custom a {
            color: #6366f1;
            text-decoration: none;
        }

        .version-header {
            margin-bottom: 3rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .version-header h1 {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            margin-bottom: 1rem;
        }

        .commit-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
        }

        .commit-message {
            font-family: 'Inter', sans-serif;
            color: #fff;
            font-weight: 500;
            margin-bottom: 0.5rem;
            display: block;
        }

        .commit-hash {
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
            font-size: 0.8rem;
            color: #6366f1;
        }

        .change-item {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            margin-bottom: 2rem;
            overflow: hidden;
            transition: border-color 0.3s;
        }

        .change-item:hover {
            border-color: rgba(255, 255, 255, 0.1);
        }

        .change-header {
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.02);
            cursor: pointer;
        }

        .change-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .badge-status {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.25rem 0.6rem;
            border-radius: 4px;
            font-weight: 700;
        }

        .status-added {
            background: rgba(34, 197, 94, 0.1);
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        .status-modified {
            background: rgba(59, 130, 246, 0.1);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .status-deleted {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .status-renamed {
            background: rgba(168, 85, 247, 0.1);
            color: #c084fc;
            border: 1px solid rgba(168, 85, 247, 0.2);
        }

        .file-name {
            font-weight: 600;
            font-size: 1.1rem;
            color: #eee;
        }

        .file-path {
            font-size: 0.8rem;
            color: #666;
            margin-left: 0.5rem;
        }

        .diff-container {
            background: #0d0d0d;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            padding: 0;
            overflow-x: auto;
        }

        .diff-content {
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
            font-size: 0.85rem;
            line-height: 1.5;
            padding: 1rem 0;
            margin: 0;
            white-space: pre;
        }

        .diff-line {
            display: block;
            padding: 0 1.5rem;
            min-height: 1.5em;
        }

        .line-added {
            background: rgba(34, 197, 94, 0.15);
            color: #4ade80;
            border-left: 3px solid #22c55e;
        }

        .line-removed {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border-left: 3px solid #ef4444;
        }

        .line-info {
            background: rgba(59, 130, 246, 0.05);
            color: #60a5fa;
            opacity: 0.8;
            font-style: italic;
        }

        .line-context {
            opacity: 0.6;
        }

        .view-file-link {
            font-size: 0.8rem;
            color: #6366f1;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s;
        }

        .view-file-link:hover {
            color: #818cf8;
            text-decoration: underline;
        }
    </style>
@endsection

@section('content')
    <div class="d-flex gap-0 h-100 w-100 changelog-container">
        @include('vault._sidebar', ['tree' => $tree, 'note' => $note])

        <section class="flex-grow-1 p-4 p-md-5" style="min-width: 0; overflow-y: auto;">
            <div class="container-narrow mx-auto" style="max-width: 1000px;">
                <nav class="breadcrumb-custom">
                    <a href="{{ route('vault.changelog.index') }}"><i class="bi bi-chevron-left"></i> Torna alla
                        cronologia</a>
                </nav>

                <header class="version-header">
                    <span class="text-secondary small text-uppercase fw-bold mb-2 d-block">Dettagli Versione</span>
                    <h1 class="display-4 fw-bold">{{ $changelog['version'] }}</h1>
                    <div class="d-flex align-items-center gap-4">
                        <span class="text-secondary"><i
                                class="bi bi-calendar3 me-2"></i>{{ date('d F Y, H:i', strtotime($changelog['date'])) }}</span>
                        <span class="text-secondary"><i
                                class="bi bi-file-earmark-diff me-2"></i>{{ count($changelog['changes']) }} file
                            modificati</span>
                    </div>
                </header>

                <div class="commit-box">
                    <span class="text-secondary small text-uppercase fw-bold mb-2 d-block">Messaggio di Commit</span>
                    <span class="commit-message">{{ $changelog['commit_message'] }}</span>
                    <span class="commit-hash">{{ $changelog['commit_hash'] }}</span>
                </div>

                <div class="changes-list">
                    <h3 class="mb-4 fw-bold">Modifiche</h3>
                    @foreach ($changelog['changes'] as $index => $change)
                        <div class="change-item">
                            <div class="change-header" data-bs-toggle="collapse" data-bs-target="#diff-{{ $index }}">
                                <div class="change-title">
                                    <span
                                        class="badge-status status-{{ strtolower($change['status']) }}">{{ $change['status'] }}</span>
                                    <div>
                                        <span class="file-name">{{ $change['display_name'] }}</span>
                                        <span class="file-path">{{ $change['file'] }}</span>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    @if($change['status'] !== 'Deleted')
                                        @php
                                            $slug = str_replace('.md', '', \App\Http\Controllers\VaultController::pathToCamelCase($change['file']));
                                        @endphp
                                        <a href="/vault/{{ $slug }}" class="view-file-link" onclick="event.stopPropagation()">
                                            <i class="bi bi-eye"></i> Visualizza nota
                                        </a>
                                    @endif
                                    <i class="bi bi-chevron-down text-secondary"></i>
                                </div>
                            </div>
                            <div id="diff-{{ $index }}" class="collapse show">
                                <div class="diff-container">
                                    <pre
                                        class="diff-content">@foreach(explode("\n", $change['diff']) as $line)@if(str_starts_with($line, '+') && !str_starts_with($line, '+++'))<span class="diff-line line-added">{{ $line }}</span>@elseif(str_starts_with($line, '-') && !str_starts_with($line, '---'))<span class="diff-line line-removed">{{ $line }}</span>@elseif(str_starts_with($line, '@@'))<span class="diff-line line-info">{{ $line }}</span>@else<span class="diff-line line-context">{{ $line }}</span>@endif @endforeach</pre>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
@endsection
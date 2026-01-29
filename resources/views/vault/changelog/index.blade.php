@extends('layouts.app')

@section('title', 'Cronologia Versioni - Vault')

@section('content-class', 'container-fluid px-0')

@section('style')
    <style>
        .changelog-container {
            background: #121212;
            min-height: calc(100vh - 56px);
            color: #e0e0e0;
        }

        .version-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .version-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, #6366f1, #a855f7);
            opacity: 0.7;
        }

        .version-card:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: translateY(-2px);
            border-color: rgba(99, 102, 241, 0.3);
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
        }

        .version-info h3 {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            margin-bottom: 0.25rem;
            background: linear-gradient(to right, #fff, #999);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .version-meta {
            font-size: 0.85rem;
            color: #888;
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }

        .version-meta i {
            margin-right: 0.4rem;
            color: #6366f1;
        }

        .changes-badge {
            background: rgba(99, 102, 241, 0.1);
            color: #818cf8;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid rgba(99, 102, 241, 0.2);
        }

        .view-details-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
            text-decoration: none;
        }

        .view-details-btn:hover {
            background: #6366f1;
            color: #fff;
            border-color: #6366f1;
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.4);
        }

        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            display: block;
            opacity: 0.3;
        }
    </style>
@endsection

@section('content')
    <div class="d-flex gap-0 h-100 w-100 changelog-container">
        @include('vault._sidebar', ['tree' => $tree, 'note' => $note])

        <section class="flex-grow-1 p-4 p-md-5" style="min-width: 0; overflow-y: auto;">
            <div class="container-narrow mx-auto" style="max-width: 900px;">
                <header class="mb-5">
                    <h1 class="display-5 fw-bold mb-2">Cronologia Versioni</h1>
                    <p class="text-secondary">Tracciamento automatico delle modifiche apportate al Vault.</p>
                </header>

                @if (empty($versions))
                    <div class="empty-state">
                        <i class="bi bi-clock-history"></i>
                        <h3>Nessuna versione trovata</h3>
                        <p>Il sistema di changelog non ha ancora registrato alcuna versione.</p>
                    </div>
                @else
                    <div class="version-list">
                        @foreach ($versions as $v)
                            <div class="version-card">
                                <div class="version-info">
                                    <h3>{{ $v['version'] }}</h3>
                                    <div class="version-meta">
                                        <span><i class="bi bi-calendar3"></i> {{ date('d M Y, H:i', strtotime($v['date'])) }}</span>
                                        <span><i class="bi bi-hash"></i> {{ substr($v['commit_hash'], 0, 7) }}</span>
                                        <span class="changes-badge">{{ $v['changes_count'] }} modifiche</span>
                                    </div>
                                </div>
                                <div class="version-actions">
                                    @php
                                        $versionParam = str_replace([' ', '.'], '_', $v['version']);
                                    @endphp
                                    <a href="{{ route('vault.changelog.show', ['version' => $versionParam]) }}"
                                        class="view-details-btn">
                                        Dettagli <i class="bi bi-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection
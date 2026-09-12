@extends('layouts.app')

@section('title', 'Iscrizioni Telegram')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><i class="bi bi-telegram"></i> Iscrizioni Telegram</h3>
        <a href="{{ route('admin.campaigns') }}" class="btn btn-secondary">
            <i class="bi bi-compass"></i> Gestione Campagne
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Riepilogo -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h2 class="mb-0">{{ $totalSubscribers }}</h2>
                    <small class="text-muted">Iscrizioni totali</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h2 class="mb-0 text-success">{{ $linkedCount }}</h2>
                    <small class="text-muted">Collegate (utente o gruppo)</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h2 class="mb-0 text-secondary">{{ $totalSubscribers - $linkedCount }}</h2>
                    <small class="text-muted">Non collegate</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtro -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.telegram.index') }}" class="row g-2">
                <div class="col-auto">
                    <select name="campaign_id" class="form-select">
                        <option value="">Tutte le campagne</option>
                        @foreach($campaigns as $campaign)
                            <option value="{{ $campaign->id }}" {{ (string) request('campaign_id') === (string) $campaign->id ? 'selected' : '' }}>
                                {{ $campaign->display_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Filtra</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Campagna</th>
                        <th>Chat</th>
                        <th>Username Telegram</th>
                        <th>Collegamento</th>
                        <th>Data iscrizione</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subscribers as $subscriber)
                        @php
                            $isGroupChat = str_starts_with((string) $subscriber->chat_id, '-');
                            $linkedUser = $subscriber->telegram_user_id ? ($linkedUsers[$subscriber->telegram_user_id] ?? null) : null;
                        @endphp
                        <tr>
                            <td>{{ $subscriber->campaign?->display_name ?? '—' }}</td>
                            <td>
                                <code>{{ $subscriber->chat_id }}</code>
                                @if($subscriber->thread_id)
                                    <br><small class="text-muted">Topic {{ $subscriber->thread_id }}</small>
                                @endif
                                <br>
                                <span class="badge {{ $isGroupChat ? 'bg-info text-dark' : 'bg-secondary' }}">
                                    {{ $isGroupChat ? 'Gruppo' : 'Chat privata' }}
                                </span>
                            </td>
                            <td>{{ $subscriber->username ?? '—' }}</td>
                            <td>
                                @if($isGroupChat)
                                    <span class="badge bg-success">
                                        <i class="bi bi-people"></i> Gruppo → {{ $subscriber->campaign?->display_name ?? 'campagna eliminata' }}
                                    </span>
                                @elseif($linkedUser)
                                    <span class="badge bg-success">
                                        <i class="bi bi-person-check"></i> {{ $linkedUser->name }}
                                    </span>
                                @else
                                    <span class="badge bg-secondary">Non collegato</span>
                                @endif
                            </td>
                            <td>{{ $subscriber->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <form action="{{ route('admin.telegram.delete', $subscriber) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Eliminare questa iscrizione?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" title="Elimina">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Nessuna iscrizione trovata.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($subscribers->hasPages())
                <div class="mt-3">
                    {{ $subscribers->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

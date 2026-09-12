@extends('layouts.app')

@section('title', 'Gestione Campagne')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><i class="bi bi-compass"></i> Gestione Campagne</h3>
        <a href="{{ route('admin.campaigns.create') }}" class="btn btn-success">
            <i class="bi bi-plus-lg"></i> Nuova Campagna
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-info-circle"></i> Architettura Multi-Campagna
        </div>
        <div class="card-body small text-muted">
            <ul class="mb-0">
                <li><strong>Cartella Vault / Branch:</strong> definisce la sottocartella su disco (es. <code>Vault/newCampaign/</code> o <code>Vault/secondCampaign/</code>).</li>
                <li><strong>Ordine:</strong> le campagne con ordine numerico più basso hanno priorità di visualizzazione e selezione iniziale.</li>
                <li><strong>Accesso Giocatori:</strong> gli utenti standard possono accedere al Vault di una campagna solo se assegnati ad essa (i Master e Amministratori hanno accesso universale a tutte le campagne).</li>
                <li><strong>Iscrizioni Telegram:</strong> le notifiche di aggiornamento sono isolate per ciascuna campagna.</li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Ordine</th>
                        <th>Nome Visualizzato</th>
                        <th>Cartella Vault (Branch)</th>
                        <th>URL Vault</th>
                        <th>Giocatori Assegnati</th>
                        <th>Gruppi di Accesso</th>
                        <th>Iscritti Telegram</th>
                        <th>Gruppo Telegram</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($campaigns as $campaign)
                        <tr>
                            <td>
                                <span class="badge bg-dark border border-secondary">{{ $campaign->order }}</span>
                            </td>
                            <td>
                                <strong>{{ $campaign->display_name }}</strong>
                            </td>
                            <td>
                                <code>{{ $campaign->folder_name }}</code>
                            </td>
                            <td>
                                <a href="{{ route('vault.show', ['campaign' => $campaign->folder_name]) }}" target="_blank" class="text-info text-decoration-none">
                                    <i class="bi bi-box-arrow-up-right"></i> /vault/{{ $campaign->folder_name }}
                                </a>
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ $campaign->users_count }} utenti</span>
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ $campaign->access_groups_count }} gruppi</span>
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ $campaign->telegram_subscribers_count }} iscritti</span>
                            </td>
                            <td>
                                @if($campaign->telegram_chat_id)
                                    <span class="badge bg-success" title="{{ $campaign->telegram_chat_id }}">
                                        <i class="bi bi-telegram"></i> Collegato
                                    </span>
                                    <form action="{{ route('admin.campaigns.telegram-group.delete', $campaign) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Scollegare il gruppo Telegram da {{ $campaign->display_name }}? Verrà eliminata anche la relativa iscrizione.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Scollega gruppo">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('admin.campaigns.edit', $campaign) }}" class="btn btn-primary" title="Modifica">
                                        <i class="bi bi-pencil"></i> Modifica
                                    </a>
                                    <form action="{{ route('admin.campaigns.delete', $campaign) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Sei sicuro di voler eliminare la campagna {{ $campaign->display_name }}? I gruppi e le iscrizioni associate verranno eliminati.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger" title="Elimina">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">Nessuna campagna configurata.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($campaigns->hasPages())
                <div class="mt-3">
                    {{ $campaigns->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

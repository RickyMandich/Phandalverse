@extends('layouts.app')

@section('title', 'Statistiche Accessi')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><i class="bi bi-graph-up"></i> Statistiche Accessi</h3>

            <div class="btn-group">
                <a href="{{ route('admin.statistics.export.csv', request()->all()) }}" class="btn btn-outline-success">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
                </a>
                <a href="{{ route('admin.statistics.export.json', request()->all()) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-file-code"></i> Export JSON
                </a>
            </div>
        </div>

        <!-- Filtri -->
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.statistics') }}" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Data Inizio</label>
                        <input type="datetime-local" class="form-control" id="start_date" name="start_date"
                            value="{{ request('start_date', \Carbon\Carbon::parse($startDate)->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">Data Fine</label>
                        <input type="datetime-local" class="form-control" id="end_date" name="end_date"
                            value="{{ request('end_date', \Carbon\Carbon::parse($endDate)->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="user_id" class="form-label">Utente</label>
                        <select class="form-select" id="user_id" name="user_id">
                            <option value="">Tutti</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-filter"></i> Filtra
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title">Visite Totali</h5>
                        <h2 class="display-4">{{ number_format($totalVisits) }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title">Utenti Unici</h5>
                        <h2 class="display-4">{{ number_format($uniqueVisitors) }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title">IP Unici</h5>
                        <h2 class="display-4">{{ number_format($uniqueIPs) }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title">Tempo Medio Risposta</h5>
                        <h2 class="display-4">{{ number_format($avgResponseTime, 2) }} ms</h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabella Dati Raggruppati -->
        <div class="card">
            <div class="card-header">
                Riepilogo per Utente / IP
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Utente</th>
                            <th>Indirizzo IP</th>
                            <th class="text-center">Richieste</th>
                            <th>Ultima Attività</th>
                            <th>URL Più Visitato (campione)</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stats as $stat)
                            <tr>
                                <td>
                                    @if($stat->user)
                                        <span class="badge bg-primary">{{ $stat->user->name }}</span>
                                        <br><small class="text-muted">{{ $stat->user->email }}</small>
                                    @else
                                        <span class="badge bg-secondary">Ospite</span>
                                    @endif
                                </td>
                                <td><code>{{ $stat->ip_address }}</code></td>
                                <td class="text-center">
                                    <span class="badge rounded-pill bg-dark fs-6">{{ $stat->request_count }}</span>
                                </td>
                                <td>{{ \Carbon\Carbon::parse($stat->last_activity)->format('d/m/Y H:i:s') }}</td>
                                <td class="text-truncate" style="max-width: 200px;" title="{{ $stat->sample_url }}">
                                    {{ Str::limit($stat->sample_url, 50) }}
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info text-white view-details-btn" data-bs-toggle="modal"
                                        data-bs-target="#detailsModal" data-user-id="{{ $stat->user_id ?? 'guest' }}"
                                        data-ip="{{ $stat->ip_address }}"
                                        data-username="{{ $stat->user ? $stat->user->name : 'Ospite' }}">
                                        <i class="bi bi-eye"></i> Dettagli
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <p class="lead text-muted mb-0">Nessuna statistica trovata per il periodo selezionato</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $stats->links() }}
            </div>
        </div>
    </div>

    <!-- Modal Dettagli -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Dettagli Accessi: <span id="modalTitleUser"></span> (<span
                            id="modalTitleIp"></span>)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped" id="detailsTable">
                            <thead>
                                <tr>
                                    <th>Data/Ora</th>
                                    <th>Metodo</th>
                                    <th>Status</th>
                                    <th>Tempo (ms)</th>
                                    <th>URL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="5" class="text-center">Caricamento...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('detailsModal');
            const detailsTableBody = document.querySelector('#detailsTable tbody');

            modal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const userId = button.getAttribute('data-user-id');
                const ip = button.getAttribute('data-ip');
                const username = button.getAttribute('data-username');

                document.getElementById('modalTitleUser').textContent = username;
                document.getElementById('modalTitleIp').textContent = ip;

                // Show loading state
                detailsTableBody.innerHTML = '<tr><td colspan="5" class="text-center"><div class="spinner-border text-primary" role="status"></div><br>Caricamento dati...</td></tr>';

                // Fetch details
                const startDate = document.getElementById('start_date').value;
                const endDate = document.getElementById('end_date').value;

                const params = new URLSearchParams({
                    user_id: userId,
                    ip_address: ip,
                    start_date: startDate,
                    end_date: endDate
                });

                fetch(`{{ route('admin.statistics.details') }}?${params.toString()}`)
                    .then(response => response.json())
                    .then(data => {
                        detailsTableBody.innerHTML = '';

                        if (data.length === 0) {
                            detailsTableBody.innerHTML = '<tr><td colspan="5" class="text-center">Nessun dettaglio trovato</td></tr>';
                            return;
                        }

                        data.forEach(item => {
                            const date = new Date(item.created_at).toLocaleString('it-IT');

                            let methodClass = 'bg-secondary';
                            if (item.http_method === 'GET') methodClass = 'bg-success';
                            else if (item.http_method === 'POST') methodClass = 'bg-primary';
                            else if (item.http_method === 'DELETE') methodClass = 'bg-danger';
                            else if (item.http_method === 'PUT' || item.http_method === 'PATCH') methodClass = 'bg-warning';

                            let statusClass = 'text-success';
                            if (item.response_status >= 400 && item.response_status < 500) statusClass = 'text-warning';
                            else if (item.response_status >= 500) statusClass = 'text-danger';

                            const row = `
                                <tr>
                                    <td>${date}</td>
                                    <td><span class="badge ${methodClass}">${item.http_method}</span></td>
                                    <td><span class="${statusClass} fw-bold">${item.response_status}</span></td>
                                    <td>${item.response_time}</td>
                                    <td class="text-break"><small>${item.url}</small></td>
                                </tr>
                            `;
                            detailsTableBody.innerHTML += row;
                        });
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        detailsTableBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Errore nel caricamento dei dati</td></tr>';
                    });
            });
        });
    </script>
@endsection
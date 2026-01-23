@extends('layouts.app')

@section('title', 'Accedi al DB')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><i class="bi bi-database"></i> Accedi al DB</h3>
            <span class="badge bg-danger">Attenzione: Le query vengono eseguite direttamente!</span>
        </div>

        @if(isset($success))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i> {{ $success }}
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
        @endif

        @if(isset($error))
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i> <strong>Errore SQL:</strong><br>
                <code>{{ $error }}</code>
            </div>
        @endif

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-dark text-white py-3">
                <h6 class="mb-0"><i class="bi bi-terminal"></i> Esegui Query SQL</h6>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.database.query') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <textarea name="query" id="query" rows="5" class="form-control font-monospace"
                            placeholder="SELECT * FROM users LIMIT 10;">{{ $query ?? '' }}</textarea>
                    </div>
                    <div class="d-flex justify-content-between">
                        <div>
                            <button type="button" class="btn btn-outline-secondary btn-sm me-2"
                                onclick="setQuery('SELECT * FROM users LIMIT 10;')">Esempio SELECT</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                onclick="setQuery('SHOW TABLES;')">Mostra Tabelle</button>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-play-fill"></i> Esegui Query
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @if(isset($results) && is_array($results))
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-table"></i> Risultati ({{ $affectedRows }} righe)</h6>
                    <button class="btn btn-sm btn-outline-success" onclick="exportTableToCSV('query_results.csv')">
                        <i class="bi bi-download"></i> Scarica CSV
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0" id="resultsTable">
                            <thead class="table-light">
                                <tr>
                                    @if(count($results) > 0)
                                        @foreach(get_object_vars($results[0]) as $key => $value)
                                            <th>{{ $key }}</th>
                                        @endforeach
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($results as $row)
                                    <tr>
                                        @foreach(get_object_vars($row) as $value)
                                            <td>
                                                @if(is_null($value))
                                                    <em class="text-muted">NULL</em>
                                                @elseif(is_bool($value))
                                                    {{ $value ? 'TRUE' : 'FALSE' }}
                                                @else
                                                    {{ $value }}
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center py-4">Nessun risultato trovato</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @elseif(isset($affectedRows) && !$isSelect)
            <div class="alert alert-info border-0 shadow-sm">
                <i class="bi bi-info-circle me-2"></i> Query eseguita correttamente. Righe interessate:
                <strong>{{ $affectedRows }}</strong>
            </div>
        @endif
    </div>

    <script>
        function setQuery(q) {
            document.getElementById('query').value = q;
        }

        function exportTableToCSV(filename) {
            var csv = [];
            var rows = document.querySelectorAll("#resultsTable tr");

            for (var i = 0; i < rows.length; i++) {
                var row = [], cols = rows[i].querySelectorAll("td, th");

                for (var j = 0; j < cols.length; j++)
                    row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');

                csv.push(row.join(","));
            }

            // Download CSV file
            var csvFile = new Blob([csv.join("\n")], { type: "text/csv" });
            var downloadLink = document.createElement("a");
            downloadLink.download = filename;
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = "none";
            document.body.appendChild(downloadLink);
            downloadLink.click();
        }
    </script>

    <style>
        .font-monospace {
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
        }

        .table-responsive {
            max-height: 600px;
        }

        #resultsTable thead {
            position: sticky;
            top: 0;
            z-index: 1;
        }
    </style>
@endsection
@extends('layouts.app')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center py-5">
            <div class="col-md-6 col-lg-5">
                <div class="card bg-dark text-white border-secondary shadow-lg overflow-hidden">
                    <div class="card-header bg-warning text-dark py-3 border-0">
                        <h3 class="mb-0 text-center fw-bold"><i class="bi bi-shield-lock me-2"></i> Accesso Giocatori</h3>
                    </div>
                    <div class="card-body p-4 p-lg-5">
                        <p class="text-white-50 text-center mb-4">
                            Inserisci il codice fornito dal tuo Dungeon Master per seguire l'iniziativa e gli stati del
                            combattimento in tempo reale.
                        </p>

                        <form
                            onsubmit="event.preventDefault(); window.location.href='/dm/player/' + document.getElementById('shareCode').value.toUpperCase();">
                            <div class="mb-4 text-center">
                                <label for="shareCode" class="form-label small text-uppercase fw-bold text-muted">Codice
                                    Sessione</label>
                                <input type="text" id="shareCode"
                                    class="form-control form-control-lg bg-secondary text-white border-0 text-center fw-bold fs-2 py-3"
                                    placeholder="XXX-XXX" maxlength="7" required autocomplete="off"
                                    style="letter-spacing: 5px; text-transform: uppercase;">
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-warning btn-lg fw-bold py-3 shadow">
                                    ENTRA NEL COMBATTIMENTO <i class="bi bi-chevron-right ms-2"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer bg-dark border-secondary text-center py-3 small">
                        <span class="text-muted small">Non hai un codice? Chiedi al tuo DM di copiarlo dal DM Screen.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        #shareCode::placeholder {
            color: rgba(255, 255, 255, 0.2);
            letter-spacing: normal;
        }

        #shareCode:focus {
            box-shadow: 0 0 15px rgba(255, 193, 7, 0.3);
            background-color: #444 !important;
        }
    </style>

    <script>
        // Auto-inserimento trattino
        document.getElementById('shareCode').addEventListener('input', function (e) {
            var value = e.target.value.replace(/[^A-Za-z0-9]/g, '');
            if (value.length > 3) {
                value = value.substring(0, 3) + '-' + value.substring(3, 6);
            }
            e.target.value = value.toUpperCase();
        });
    </script>
@endsection
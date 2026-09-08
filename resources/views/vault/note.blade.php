@extends('layouts.app')
@section('title', $title)

@section('include')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
@endsection

@section('style')
    <style>
        .access-filter-btn {
            border: 1px solid var(--btn-color);
            color: var(--btn-color);
            background: transparent;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .access-filter-btn.active {
            background: var(--btn-color);
            color: #111;
            font-weight: 600;
        }
        .access-filter-btn:not(.active) {
            opacity: 0.5;
            text-decoration: line-through;
        }

        @media print {
            #vault-sidebar,
            nav.navbar,
            footer,
            .modal,
            #sidebar-toggle,
            .btn,
            .access-filter-btn {
                display: none !important;
            }
            body, #app, section {
                background: #fff !important;
                color: #000 !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .vault-note {
                color: #000 !important;
            }
            .text-warning {
                color: #b45309 !important;
            }
        }
    </style>
@endsection

@section('content')
    @php
        \App\Services\CustomLogger::note($note, "-------------------inizio view nota-------------------");
        \App\Services\CustomLogger::note($note, print_r($path, true));
        $availableAccessLevels = $availableAccessLevels ?? [];
        $isPdf = $isPdf ?? false;
    @endphp
    <div class="d-flex gap-0 h-100 w-100">
        @auth
            @include('vault._sidebar', [
                'tree' => $tree ?? [],
                'path' => $path ?? [],
                'masterFile' => $masterFile ?? false,
                'note' => $note ?? null,
                'campaign' => $campaign,
                'accessibleCampaigns' => $accessibleCampaigns
            ])
        @endauth

        <section class="flex-grow-1 p-4 {{ Auth::check() ? '' : 'mx-auto' }}" style="min-width: 0; {{ Auth::check() ? '' : 'max-width: 960px;' }}">
            <div class="title-container">
                <h1 class="display-5 mb-4 border-bottom pb-2 text-warning fw-bold">{{ $title }}</h1>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-4">
                    @if ($masterFile)
                        <div class="master-block">Master</div>
                    @endif
                    @if (!empty($accessBadges))
                        @foreach ($accessBadges as $badge)
                            <span class="badge" style="background-color: {{ $badge['color'] ?: '#6c757d' }}; color: #fff; font-size: 0.9rem;">{{ $badge['name'] }}</span>
                        @endforeach
                    @endif

                    @if (!$isPdf && Auth::check() && Auth::user()->isMaster())
                        <a href="{{ route('vault.raw', ['campaign' => $campaign->folder_name, 'note' => $note]) }}" class="btn btn-sm btn-outline-warning"
                            title="Scarica Markdown">
                            <i class="bi bi-download"></i> Scarica MD
                        </a>
                    @endif

                    @if ($isPdf)
                        <a href="{{ $pdfDownloadUrl }}" class="btn btn-sm btn-warning" title="Scarica il file PDF originale" download>
                            <i class="bi bi-download me-1"></i> Scarica PDF
                        </a>
                        <a href="{{ $pdfUrl }}" target="_blank" class="btn btn-sm btn-outline-light" title="Apri PDF a schermo intero in una nuova scheda">
                            <i class="bi bi-box-arrow-up-right me-1"></i> A schermo intero
                        </a>
                    @endif

                    <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#shareModal" title="{{ $isPdf ? 'Condividi o Scarica PDF' : 'Condividi o Esporta in PDF' }}">
                        <i class="bi bi-share me-1"></i> Condividi
                    </button>

                    @if (!$isPdf && !empty($availableAccessLevels))
                        <div class="d-inline-flex align-items-center gap-1 bg-dark-subtle border border-secondary rounded-pill px-2 py-1 ms-auto flex-wrap">
                            <span class="small text-secondary me-1"><i class="bi bi-funnel"></i> Mostra:</span>
                            @foreach($availableAccessLevels as $lvl)
                                <button type="button"
                                    class="btn btn-xs rounded-pill px-2 py-0 access-filter-btn active"
                                    data-level-id="{{ $lvl['id'] }}"
                                    data-level-type="{{ $lvl['type'] }}"
                                    data-level-slug="{{ $lvl['slug'] }}"
                                    style="--btn-color: {{ $lvl['color'] }}; font-size: 0.8rem;"
                                    title="Mostra/Nascondi {{ $lvl['name'] }}">
                                    @if($lvl['type'] === 'master')
                                        <i class="bi bi-shield-lock me-1"></i>
                                    @else
                                        <span class="badge-dot me-1" style="display:inline-block; width:7px; height:7px; border-radius:50%; background:{{ $lvl['color'] }};"></span>
                                    @endif
                                    {{ $lvl['name'] }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            @if ($isPdf)
                <div class="vault-pdf-container mb-4">
                    <div class="card bg-dark border-secondary shadow-lg">
                        <div class="card-header bg-black bg-opacity-25 border-secondary d-flex justify-content-between align-items-center py-2 px-3">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-file-earmark-pdf-fill text-danger fs-5"></i>
                                <span class="fw-semibold text-white">{{ $title }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <a href="{{ $pdfUrl }}" target="_blank" class="btn btn-sm btn-outline-light" title="Apri in nuova scheda">
                                    <i class="bi bi-box-arrow-up-right me-1"></i> A tutto schermo
                                </a>
                                <a href="{{ $pdfDownloadUrl }}" class="btn btn-sm btn-warning" download title="Scarica file PDF">
                                    <i class="bi bi-download me-1"></i> Scarica PDF
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-0" style="height: calc(100vh - 240px); min-height: 650px;">
                            <iframe src="{{ $pdfUrl }}#toolbar=1" class="w-100 h-100 rounded-bottom" style="border: none;" type="application/pdf">
                                <div class="p-4 text-center text-white">
                                    <i class="bi bi-file-earmark-pdf-fill text-danger display-3 mb-3 d-block"></i>
                                    <h5>Visualizzatore PDF integrato non supportato dal browser</h5>
                                    <p class="text-secondary">Puoi visualizzare o scaricare il documento direttamente con i pulsanti sottostanti:</p>
                                    <div class="d-flex justify-content-center gap-2 mt-3">
                                        <a href="{{ $pdfUrl }}" target="_blank" class="btn btn-outline-info">
                                            <i class="bi bi-box-arrow-up-right me-1"></i> Apri nel browser
                                        </a>
                                        <a href="{{ $pdfDownloadUrl }}" class="btn btn-warning" download>
                                            <i class="bi bi-download me-1"></i> Scarica PDF
                                        </a>
                                    </div>
                                </div>
                            </iframe>
                        </div>
                    </div>
                </div>
            @else
                <div class="vault-note typography">
                    {!! $html !!}
                </div>
            @endif
        </section>
    </div>

    <!-- Modal Condividi & Scarica PDF -->
    <div class="modal fade" id="shareModal" tabindex="-1" aria-labelledby="shareModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white border-secondary shadow-lg">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-warning fw-bold" id="shareModalLabel">
                        <i class="bi bi-share me-2"></i>Condividi Nota
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <h6 class="text-white fw-semibold mb-3">{{ $title }}</h6>

                    <!-- QR Code Container -->
                    <div class="d-flex flex-column align-items-center mb-4">
                        <div class="p-3 bg-white rounded-3 shadow-sm d-inline-block" id="qrcode-container" style="min-width: 180px; min-height: 180px;">
                            <div id="qrcode" class="d-flex justify-content-center"></div>
                        </div>
                        <small class="text-secondary mt-2">
                            <i class="bi bi-camera me-1"></i> Inquadra il QR code per aprire la pagina
                        </small>
                    </div>

                    <!-- Link & Copy Section -->
                    <div class="mb-4">
                        <label class="form-label small text-secondary d-block text-start">Link diretto:</label>
                        <div class="input-group">
                            <input type="text" id="shareUrlInput" class="form-control bg-body-secondary text-white border-secondary font-monospace small" readonly>
                            <button class="btn btn-outline-warning" type="button" id="btnCopyLink">
                                <i class="bi bi-clipboard me-1"></i> <span id="copyBtnText">Copia</span>
                            </button>
                        </div>
                        <button type="button" class="btn btn-outline-info w-100 mt-2 d-none" id="btnNativeShare">
                            <i class="bi bi-send me-1"></i> Condividi tramite app...
                        </button>
                    </div>

                    <hr class="border-secondary my-4">

                    <!-- Sezione PDF -->
                    <div class="text-start">
                        <h6 class="text-warning fw-bold mb-2">
                            <i class="bi bi-file-earmark-pdf me-1"></i> {{ $isPdf ? 'Download PDF' : 'Esporta in PDF' }}
                        </h6>

                        @if ($isPdf)
                            <p class="small text-secondary mb-3">
                                Scarica direttamente il file PDF originale memorizzato nel Vault:
                            </p>
                            <div class="d-flex gap-2">
                                <a href="{{ $pdfDownloadUrl }}" class="btn btn-warning flex-grow-1" download>
                                    <i class="bi bi-download me-1"></i> Scarica PDF
                                </a>
                                <a href="{{ $pdfUrl }}" target="_blank" class="btn btn-outline-secondary" title="Apri in nuova scheda">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </div>
                        @else
                            @if(!empty($availableAccessLevels))
                                <p class="small text-secondary mb-2">
                                    Seleziona quali contenuti includere nel documento PDF (deseleziona i tag per rimuovere le sezioni riservate):
                                </p>
                                <div class="d-flex flex-wrap gap-2 mb-3" id="pdfTagCheckboxes">
                                    @foreach($availableAccessLevels as $lvl)
                                        <div class="form-check form-check-inline m-0">
                                            <input class="form-check-input pdf-level-checkbox" type="checkbox"
                                                   id="pdf_level_{{ $lvl['id'] }}"
                                                   value="{{ $lvl['id'] }}"
                                                   data-type="{{ $lvl['type'] }}"
                                                   data-slug="{{ $lvl['slug'] }}"
                                                   checked>
                                            <label class="form-check-label small fw-semibold" for="pdf_level_{{ $lvl['id'] }}" style="color: {{ $lvl['color'] }};">
                                                @if($lvl['type'] === 'master')
                                                    <i class="bi bi-shield-lock me-1"></i>
                                                @endif
                                                {{ $lvl['name'] }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="small text-secondary mb-3">
                                    Scarica una copia PDF formattata della pagina corrente.
                                </p>
                            @endif

                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-warning flex-grow-1" id="btnDownloadPdf">
                                    <i class="bi bi-download me-1"></i> <span id="pdfBtnText">Scarica PDF</span>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="btnPrintPdf" title="Stampa o Salva PDF tramite browser">
                                    <i class="bi bi-printer"></i>
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @php
        \App\Services\CustomLogger::note($note, "-------------------fine view nota-------------------");
    @endphp
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const currentUrl = window.location.href;
            const shareUrlInput = document.getElementById('shareUrlInput');
            if (shareUrlInput) {
                shareUrlInput.value = currentUrl;
            }

            // QR Code Generation
            let qrGenerated = false;
            function generateQrCode() {
                const qrContainer = document.getElementById('qrcode');
                if (!qrContainer || qrGenerated || typeof QRCode === 'undefined') return;
                qrContainer.innerHTML = '';
                new QRCode(qrContainer, {
                    text: currentUrl,
                    width: 160,
                    height: 160,
                    colorDark: "#111827",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
                qrGenerated = true;
            }

            const shareModal = document.getElementById('shareModal');
            if (shareModal) {
                shareModal.addEventListener('shown.bs.modal', function () {
                    generateQrCode();
                });
            }
            generateQrCode();

            // Pulsante Copia Link
            const btnCopyLink = document.getElementById('btnCopyLink');
            if (btnCopyLink) {
                btnCopyLink.addEventListener('click', async function () {
                    try {
                        await navigator.clipboard.writeText(currentUrl);
                        const textSpan = document.getElementById('copyBtnText');
                        if (textSpan) textSpan.innerText = 'Copiato! ✓';
                        btnCopyLink.classList.remove('btn-outline-warning');
                        btnCopyLink.classList.add('btn-success');
                        setTimeout(function () {
                            if (textSpan) textSpan.innerText = 'Copia';
                            btnCopyLink.classList.remove('btn-success');
                            btnCopyLink.classList.add('btn-outline-warning');
                        }, 2000);
                    } catch (err) {
                        prompt('Copia il link:', currentUrl);
                    }
                });
            }

            // Pulsante Native Share (Mobile)
            const btnNativeShare = document.getElementById('btnNativeShare');
            if (btnNativeShare && navigator.share) {
                btnNativeShare.classList.remove('d-none');
                btnNativeShare.addEventListener('click', async function () {
                    try {
                        await navigator.share({
                            title: document.title,
                            url: currentUrl
                        });
                    } catch (e) {}
                });
            }

            // Filtro Multi-Tag a Schermo
            const filterBtns = document.querySelectorAll('.access-filter-btn');
            function updateContentVisibility() {
                const activeGroupSlugs = new Set();
                let masterActive = false;

                filterBtns.forEach(function (btn) {
                    if (btn.classList.contains('active')) {
                        const type = btn.dataset.levelType;
                        const slug = btn.dataset.levelSlug;
                        if (type === 'master') masterActive = true;
                        if (type === 'group' && slug) activeGroupSlugs.add(slug);
                    }
                });

                // Master blocks
                document.querySelectorAll('.vault-note .master-block').forEach(function (el) {
                    if (masterActive) {
                        el.classList.remove('d-none');
                    } else {
                        el.classList.add('d-none');
                    }
                });

                // Access blocks
                document.querySelectorAll('.vault-note .access-block').forEach(function (el) {
                    const groupsStr = el.dataset.groups || '';
                    const blockGroups = groupsStr.split(',').filter(Boolean);
                    if (blockGroups.length === 0) return;

                    const hasActive = blockGroups.some(function (g) {
                        return activeGroupSlugs.has(g);
                    });
                    if (hasActive) {
                        el.classList.remove('d-none');
                    } else {
                        el.classList.add('d-none');
                    }
                });
            }

            filterBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    btn.classList.toggle('active');
                    updateContentVisibility();

                    // Sincronizza checkbox nel modal PDF se presente
                    const levelId = btn.dataset.levelId;
                    const cb = document.getElementById('pdf_level_' + levelId);
                    if (cb) {
                        cb.checked = btn.classList.contains('active');
                    }
                });
            });

            // Generazione PDF
            const btnDownloadPdf = document.getElementById('btnDownloadPdf');
            if (btnDownloadPdf) {
                btnDownloadPdf.addEventListener('click', async function () {
                    const pdfBtnText = document.getElementById('pdfBtnText');
                    if (pdfBtnText) pdfBtnText.innerText = 'Generazione in corso...';
                    btnDownloadPdf.disabled = true;

                    try {
                        const selectedGroups = new Set();
                        let masterSelected = false;

                        const checkboxes = document.querySelectorAll('.pdf-level-checkbox');
                        if (checkboxes.length > 0) {
                            checkboxes.forEach(function (cb) {
                                if (cb.checked) {
                                    if (cb.dataset.type === 'master') masterSelected = true;
                                    if (cb.dataset.type === 'group') selectedGroups.add(cb.dataset.slug);
                                }
                            });
                        } else {
                            masterSelected = true;
                        }

                        const noteSection = document.querySelector('.vault-note');
                        const titleEl = document.querySelector('.title-container h1');
                        if (!noteSection) return;

                        const printClone = document.createElement('div');
                        printClone.className = 'pdf-export-container';
                        printClone.innerHTML = `
                            <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #111; background: #fff; padding: 25px; line-height: 1.6;">
                                <h1 style="font-size: 22pt; border-bottom: 2px solid #d97706; padding-bottom: 8px; margin-bottom: 16px; color: #b45309;">
                                    ${titleEl ? titleEl.innerText : 'Nota'}
                                </h1>
                                <div class="vault-note-print" style="color: #1f2937;">
                                    ${noteSection.innerHTML}
                                </div>
                            </div>
                        `;

                        // Filtra master blocks nel clone
                        printClone.querySelectorAll('.master-block').forEach(function (el) {
                            if (!masterSelected) {
                                el.remove();
                            } else {
                                el.style.borderLeft = '3px solid #d97706';
                                el.style.padding = '8px 12px';
                                el.style.margin = '12px 0';
                                el.style.background = '#fffbeb';
                                el.style.borderRadius = '4px';
                            }
                        });

                        // Filtra access blocks nel clone
                        printClone.querySelectorAll('.access-block').forEach(function (el) {
                            const groupsStr = el.dataset.groups || '';
                            const blockGroups = groupsStr.split(',').filter(Boolean);
                            if (blockGroups.length > 0 && checkboxes.length > 0) {
                                const keep = blockGroups.some(function (g) {
                                    return selectedGroups.has(g);
                                });
                                if (!keep) {
                                    el.remove();
                                } else {
                                    el.style.background = '#f9fafb';
                                    el.style.margin = '12px 0';
                                    el.style.padding = '8px 12px';
                                    el.style.borderRadius = '4px';
                                }
                            }
                        });

                        // Ottimizza stili per testo ed immagini
                        printClone.querySelectorAll('a').forEach(function (a) {
                            a.style.color = '#2563eb';
                            a.style.textDecoration = 'none';
                        });
                        printClone.querySelectorAll('img').forEach(function (img) {
                            img.style.maxWidth = '100%';
                            img.style.height = 'auto';
                        });

                        const cleanTitle = (titleEl ? titleEl.innerText.trim() : 'Documento').replace(/[^a-zA-Z0-9_\-]/g, '_');

                        const opt = {
                            margin: [10, 10, 10, 10],
                            filename: cleanTitle + '.pdf',
                            image: { type: 'jpeg', quality: 0.98 },
                            html2canvas: { scale: 2, useCORS: true, logging: false },
                            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                        };

                        if (typeof html2pdf !== 'undefined') {
                            await html2pdf().set(opt).from(printClone).save();
                        } else {
                            window.print();
                        }
                    } catch (err) {
                        console.error('PDF error:', err);
                        alert('Si è verificato un errore durante la creazione del PDF. Puoi utilizzare il pulsante di stampa alternativo.');
                    } finally {
                        if (pdfBtnText) pdfBtnText.innerText = 'Scarica PDF';
                        btnDownloadPdf.disabled = false;
                    }
                });
            }

            // Pulsante Stampa Native
            const btnPrintPdf = document.getElementById('btnPrintPdf');
            if (btnPrintPdf) {
                btnPrintPdf.addEventListener('click', function () {
                    window.print();
                });
            }
        });
    </script>
@endsection
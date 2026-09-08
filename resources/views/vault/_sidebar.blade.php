@php
    \App\Services\CustomLogger::note($note, "-------------------inizio view _sideboard-------------------");
    $tree = $tree ?? [];
    $currentPath = $path ?? [];
    $masterFile = $masterFile ?? null;
    $note = $note ?? "note not found";
    $campaignFolder = isset($campaign) ? ($campaign instanceof \App\Models\Campaign ? $campaign->folder_name : $campaign) : \App\Helpers\VaultHelper::resolveCampaignFolder(null);
    $accessibleCampaigns = $accessibleCampaigns ?? (\Illuminate\Support\Facades\Auth::check() ? \Illuminate\Support\Facades\Auth::user()->accessibleCampaigns() : \App\Models\Campaign::orderBy('order')->get());
    \App\Services\CustomLogger::note($note, print_r($currentPath, true));
@endphp
<style>
    /* Specific sidebar behaviors not covered by Bootstrap */
    #vault-sidebar {
        width: 300px;
        min-width: 44px;
        flex-shrink: 0;
        position: relative;
        transition: width 0.2s ease, transform 0.25s ease;
    }

    #vault-sidebar.collapsed {
        width: 44px;
    }

    #vault-sidebar.collapsed .sidebar-content {
        display: none;
    }

    @media (max-width: 768px) {
        #vault-sidebar {
            position: fixed !important;
            left: 0;
            top: 0;
            bottom: 0;
            height: 100%;
            z-index: 1050;
            transform: translateX(-100%);
            box-shadow: var(--bs-box-shadow-lg);
            border-right: none !important;
        }

        #vault-sidebar.open {
            transform: translateX(0);
        }

        #vault-sidebar.collapsed {
            transform: translateX(-100%);
        }
    }

    .cursor-pointer {
        cursor: pointer;
    }

    .sidebar-content {
        overflow-wrap: anywhere;
    }
</style>

<aside class="bg-body-secondary border-end border-secondary-subtle rounded-4 d-flex flex-column z-3" id="vault-sidebar">
    <button class="btn btn-sm btn-dark position-absolute top-0 end-0 m-2 z-2 lh-1" id="sidebar-toggle"
        title="Toggle Sidebar">
        <span id="toggle-icon">◀</span>
    </button>

    <div class="sidebar-content p-3 pt-5 overflow-auto h-100">
        {{-- Selettore cambio campagna (solo utenti autenticati) --}}
        @auth
            @if(isset($accessibleCampaigns) && $accessibleCampaigns->count() > 1)
                <div class="mb-3 pb-2 border-bottom border-secondary">
                    <label for="campaign-select" class="form-label text-warning small fw-bold mb-1 d-flex align-items-center">
                        <i class="bi bi-compass me-1"></i> Campagna
                    </label>
                    <select class="form-select form-select-sm bg-dark text-white border-secondary" id="campaign-select" onchange="window.location.href='/vault/' + this.value">
                        @foreach($accessibleCampaigns as $camp)
                            <option value="{{ $camp->folder_name }}" {{ $campaignFolder === $camp->folder_name ? 'selected' : '' }}>
                                {{ $camp->display_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @elseif(isset($campaign))
                <div class="mb-3 pb-2 border-bottom border-secondary">
                    <span class="badge bg-dark border border-warning text-warning px-2 py-1">
                        <i class="bi bi-compass me-1"></i> {{ $campaign->display_name }}
                    </span>
                </div>
            @endif
        @else
            @if(isset($campaign))
                <div class="mb-3 pb-2 border-bottom border-secondary">
                    <span class="badge bg-dark border border-warning text-warning px-2 py-1">
                        <i class="bi bi-compass me-1"></i> {{ $campaign->display_name }}
                    </span>
                </div>
            @endif
        @endauth

        <h5 class="text-warning mb-3 fw-bold"><i class="bi bi-book me-2"></i>Vault</h5>
        <div class="vault-tree font-monospace small">
            @php
                if (!function_exists('sidebarHasFilesOrDirs')) {
                    function sidebarHasFilesOrDirs(array $treeNode): bool
                    {
                        if (!empty($treeNode['_files'])) {
                            return true;
                        }
                        foreach ($treeNode as $key => $val) {
                            if ($key !== '_files' && $key !== '_dirs' && is_array($val)) {
                                if (isset($val['_dirs']) && sidebarHasFilesOrDirs($val['_dirs'])) {
                                    return true;
                                }
                            }
                        }
                        return false;
                    }
                }

                if (!function_exists('renderTreeIndexPartial')) {
                    function renderTreeIndexPartial($items, $deep, $currentPath, $note, $masterFile, $campaignFolder)
                    {
                        $pathSegment = $currentPath[$deep] ?? null;
                        $isOnPath = $pathSegment !== null;

                        if ($deep == 0) {
                            $html = '<ul class="list-unstyled m-0">';
                        } else {
                            $html = '<ul class="list-unstyled border-start border-secondary-subtle ps-3 d-none">';
                        }

                        foreach ($items as $key => $value) {
                            if ($key !== '_files' && $key !== '_dirs' && is_array($value)) {
                                if (!sidebarHasFilesOrDirs($value['_dirs'] ?? [])) {
                                    continue;
                                }

                                $folderMatchesPath = $isOnPath && strcasecmp($key, $pathSegment) === 0;
                                $openClass = $folderMatchesPath ? ' open' : '';
                                $icon = $folderMatchesPath ? '📂' : '📁';

                                $displayName = $value['_label'] ?? $key;

                                $html .= '<li class="my-1">';
                                $html .= '<span class="folder' . $openClass . ' fw-bold text-warning cursor-pointer" onclick="this.classList.toggle(\'open\'); const ul = this.nextElementSibling; ul.classList.toggle(\'d-none\'); this.innerText = this.classList.contains(\'open\') ? \'📂\u00a0\' + this.dataset.name : \'📁\u00a0\' + this.dataset.name;" data-name="' . e($displayName) . '">' . $icon . '&nbsp;' . e($displayName) . '</span>';

                                $childHtml = renderTreeIndexPartial($value['_dirs'] ?? [], $deep + 1, $currentPath, $note, $masterFile, $campaignFolder);

                                if ($folderMatchesPath) {
                                    $childHtml = preg_replace('/class="([^"]*)d-none([^"]*)"/', 'class="$1d-block$2"', $childHtml, 1);
                                }

                                $html .= $childHtml;
                                $html .= '</li>';
                            }
                        }

                        $files = $items['_files'] ?? [];
                        foreach ($files as $file) {
                            $isActiveFile = false;
                            $fileUrlDecoded = rawurldecode($file['url']);
                            $currentNoteDecoded = rawurldecode((string)$note);

                            if (strcasecmp($fileUrlDecoded, $currentNoteDecoded) === 0 ||
                                strcasecmp(preg_replace('/\.(md|pdf)$/i', '', $fileUrlDecoded), preg_replace('/\.(md|pdf)$/i', '', $currentNoteDecoded)) === 0) {
                                $isActiveFile = true;
                            }

                            $activeClass = $isActiveFile ? ' active bg-white bg-opacity-10 text-white rounded fw-semibold px-2 py-1' : ' text-info';

                            $html .= '<li class="my-1">';
                            $isDmFile = isset($file['dm']) && $file['dm'];
                            $accessBadges = $file['access_badges'] ?? [];
                            $isPdf = !empty($file['is_pdf']) || (isset($file['type']) && $file['type'] === 'pdf') || str_ends_with(strtolower($file['url'] ?? ''), '.pdf');

                            $tagsHtml = '';
                            if ($isDmFile || !empty($accessBadges)) {
                                $tagsHtml .= '<span class="tag d-inline-flex align-items-center gap-1 ms-1">';
                                if ($isDmFile) {
                                    $tagsHtml .= '<span class="master-block">Master</span>';
                                }
                                foreach ($accessBadges as $badge) {
                                    $bColor = htmlspecialchars($badge['color'] ?: '#6c757d');
                                    $bName = htmlspecialchars($badge['name']);
                                    $tagsHtml .= '<span class="badge" style="background-color: ' . $bColor . '; color: #fff; font-size: 0.7em;">' . $bName . '</span>';
                                }
                                $tagsHtml .= '</span>';
                            }

                            $fileUrl = '/vault/' . $campaignFolder . '/' . $file['url'];
                            $icon = $isPdf 
                                ? '<i class="bi bi-file-earmark-pdf-fill text-danger me-1" title="PDF"></i>' 
                                : '<i class="bi bi-file-earmark-text text-secondary me-1" title="Markdown"></i>';
                            $html .= '<a href="' . $fileUrl . '" class="file text-decoration-none d-inline-block' . $activeClass . ' hover-underline">' . $icon . e($file['name']) . '</a>' . $tagsHtml;
                            $html .= '</li>';
                        }
                        $html .= '</ul>';
                        return $html;
                    }
                }
            @endphp

            {!! renderTreeIndexPartial($tree, 0, $currentPath, $note, $masterFile, $campaignFolder) !!}

        </div>

        <hr class="border-secondary">
        <h5 class="text-warning mb-3 fw-bold">📣 Legenda</h5>
        <div class="legenda bg-dark-subtle p-3 rounded small font-monospace">
            <ul class="list-unstyled m-0">
                @if (Auth::check() && Auth::user()->isMaster())
                    <li class="mb-1">
                        <span class="text-danger fw-bold">
                            #dm
                        </span> :
                        <span class="master-block">
                            esclusivo master
                        </span>
                    </li>
                    <li class="mb-1">
                        <span class="text-danger fw-bold">
                            #access
                        </span> :
                        <span class="badge bg-secondary">
                            gruppi di accesso
                        </span>
                    </li>
                @elseif (Auth::check() && Auth::user()->accessGroups->isNotEmpty())
                    <li class="mb-1">
                        <span class="text-info fw-bold">
                            #access
                        </span> :
                        <span class="badge bg-secondary">
                            gruppi di accesso
                        </span>
                    </li>
                @endif
                <li class="mb-1"><span class="text-info">[[link]]</span> : <span class="wikilink">collegamenti</span>
                </li>
            </ul>
        </div>
    </div>

    <style>
        .hover-underline:hover {
            text-decoration: underline !important;
            color: white !important;
        }
    </style>

    <script>
        (function () {
            const toggleBtn = document.getElementById('sidebar-toggle');
            const sidebar = document.getElementById('vault-sidebar');
            let mobileOpenBtn = null;

            function ensureMobileButton() {
                if (!mobileOpenBtn) {
                    mobileOpenBtn = document.createElement('button');
                    mobileOpenBtn.className = 'btn btn-dark border-secondary position-fixed start-0 top-0 mt-3 ms-2 z-3';
                    mobileOpenBtn.innerHTML = '☰';
                    mobileOpenBtn.onclick = () => {
                        toggleSidebar();
                    };
                    document.body.appendChild(mobileOpenBtn);
                }
            }

            function removeMobileButton() {
                if (mobileOpenBtn) {
                    mobileOpenBtn.remove();
                    mobileOpenBtn = null;
                }
            }

            let wasMobile = window.innerWidth <= 768;

            function updateMobileUI() {
                const isMobile = window.innerWidth <= 768;

                if (isMobile) {
                    if (!sidebar.classList.contains('open')) {
                        sidebar.classList.add('collapsed');
                    }
                    ensureMobileButton();
                } else {
                    sidebar.classList.remove('open');
                    if (wasMobile) {
                        sidebar.classList.remove('collapsed');
                    }
                    removeMobileButton();
                }
                wasMobile = isMobile;
            }

            function toggleSidebar() {
                if (window.innerWidth <= 768) {
                    const isOpen = sidebar.classList.contains('open');
                    if (isOpen) {
                        sidebar.classList.remove('open');
                        sidebar.classList.add('collapsed');
                    } else {
                        sidebar.classList.add('open');
                        sidebar.classList.remove('collapsed');
                    }
                } else {
                    toggleBtn.blur();
                    const isCollapsed = sidebar.classList.contains('collapsed');
                    if (isCollapsed) {
                        sidebar.classList.remove('collapsed');
                    } else {
                        sidebar.classList.add('collapsed');
                    }
                }

                const icon = document.getElementById('toggle-icon');
                if (window.innerWidth <= 768) {
                    icon.textContent = sidebar.classList.contains('open') ? '✖' : '☰';
                } else {
                    icon.textContent = sidebar.classList.contains('collapsed') ? '▶' : '◀';
                }

                setTimeout(() => window.dispatchEvent(new Event('resize')), 250);
            }

            document.addEventListener('click', function (e) {
                try {
                    if (window.innerWidth <= 768 && sidebar.classList.contains('open')) {
                        const path = e.composedPath ? e.composedPath() : (e.path || []);
                        if (!path.includes(sidebar) && !path.includes(mobileOpenBtn)) {
                            toggleSidebar();
                        }
                    }
                } catch (err) {
                }
            });

            window.addEventListener('resize', updateMobileUI);
            document.addEventListener('DOMContentLoaded', updateMobileUI);

            if (toggleBtn) {
                toggleBtn.addEventListener('click', toggleSidebar);
            }
        })();
    </script>
</aside>
@php
    \App\Services\CustomLogger::note($note, "-------------------fine view _sidebar-------------------");
@endphp
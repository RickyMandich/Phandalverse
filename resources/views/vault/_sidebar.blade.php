@php
    \App\Services\CustomLogger::note($note, "-------------------inizio view _sideboard-------------------");
    // $tree expected, $path is the current note path array (e.g. ["Personaggi", "Giocanti", "Vargas (Paladino Nano, 65)"])
    $tree = $tree ?? [];
    $currentPath = $path ?? [];
    $masterFile = $masterFile ?? null;
    $note = $note ?? "note not found";
    \App\Services\CustomLogger::note($note, print_r($currentPath, true));
@endphp
<style>
    /* Specific sidebar behaviors not covered by Bootstrap */
    #vault-sidebar {
        width: 300px;
        min-width: 44px;
        /* Collapsed width */
        flex-shrink: 0;
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
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            height: 100%;
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

    /* Custom cursor for folder items as Bootstrap doesn't have a utility for it */
    .cursor-pointer {
        cursor: pointer;
    }
</style>

<aside class="bg-body-secondary border-end border-secondary-subtle rounded-4 d-flex flex-column position-relative z-1"
    id="vault-sidebar">
    <button class="btn btn-sm btn-dark position-absolute top-0 end-0 m-2 z-2 lh-1" id="sidebar-toggle"
        title="Toggle Sidebar">
        <span id="toggle-icon">◀</span>
    </button>

    <div class="sidebar-content p-3 pt-5 overflow-auto h-100">
        <h5 class="text-warning mb-3 fw-bold"><i class="bi bi-book me-2"></i>Vault</h5>
        <div class="vault-tree font-monospace small">
            @php
                // Renderer that auto-expands folders matching the current path
                function renderTreeIndexPartial($items, $deep, $currentPath, $note, $masterFile)
                {
                    // \App\Services\CustomLogger::note($note, "renderTreeIndexPartial(\$items(" . print_r($items, true) . "), \$deep(" . print_r($deep, true) . "), \$currentPath(" . print_r($currentPath, true) . "), \$note(" . print_r($note, true) . "), \$masterFile(" . print_r($masterFile, true) . "))", "debug");
                    // Check if current folder matches the path at this depth
                    $pathSegment = $currentPath[$deep] ?? null;
                    $isOnPath = $pathSegment !== null;

                    // Root level is always visible, nested levels depend on path match
                    if ($deep == 0) {
                        $html = '<ul class="list-unstyled m-0">';
                    } else {
                        $html = '<ul class="list-unstyled border-start border-secondary-subtle ps-3 d-none">';
                    }

                    foreach ($items as $key => $value) {
                        if ($key !== '_files' && $key !== '_dirs' && is_array($value)) {
                            // Check if this folder matches the current path segment (case-insensitive)
                            $folderMatchesPath = $isOnPath && strcasecmp($key, $pathSegment) === 0;
                            $openClass = $folderMatchesPath ? ' open' : '';
                            // Icon toggle logic relies on 'open' class
                            $icon = $folderMatchesPath ? '📂' : '📁';

                            $displayName = $value['_label'] ?? $key;

                            $html .= '<li class="my-1">';
                            // Use d-bock/d-none toggling
                            $html .= '<span class="folder' . $openClass . ' fw-bold text-warning cursor-pointer" onclick="this.classList.toggle(\'open\'); const ul = this.nextElementSibling; ul.classList.toggle(\'d-none\'); this.innerText = this.classList.contains(\'open\') ? \'📂\u00a0\' + this.dataset.name : \'📁\u00a0\' + this.dataset.name;" data-name="' . e($displayName) . '">' . $icon . '&nbsp;' . e($displayName) . '</span>';

                            // Render children, always passing the current path (for file highlighting)
                            // but only increment depth if this folder matches the path (for auto-expansion)
                            $childHtml = renderTreeIndexPartial($value['_dirs'] ?? [], $deep + 1, $currentPath, $note, $masterFile);

                            // If folder matches path, show only its immediate child <ul> (remove d-none)
                            if ($folderMatchesPath) {
                                $childHtml = preg_replace('/class="([^"]*)d-none([^"]*)"/', 'class="$1d-block$2"', $childHtml, 1);
                            }

                            $html .= $childHtml;
                            $html .= '</li>';
                        }
                    }

                    // Check if current file matches (last element of path)
                    \App\Services\CustomLogger::note($note, "currentPath: " . print_r($currentPath, true));
                    $currentFileName = count($currentPath) > 0 ? end($currentPath) : null;
                    \App\Services\CustomLogger::note($note, "currentFileName: $currentFileName");

                    $files = $items['_files'] ?? [];
                    foreach ($files as $file) {
                        // Extract normalized filename from path (last segment after splitting by '/')
                        $pathSegments = explode('/', $file['path']);
                        $normalizedFileName = end($pathSegments);
                        $isActiveFile = $currentFileName !== null && strcasecmp($normalizedFileName, $currentFileName) === 0;

                        $activeClass = $isActiveFile ? ' active bg-white bg-opacity-10 text-white rounded fw-semibold px-2 py-1' : ' text-info';

                        $html .= '<li class="my-1">';
                        $isDmFile = isset($file['dm']) && $file['dm'];
                        $masterTag = $isDmFile ? '<span class="master-block tag">Master</span>' : '';
                        $html .= '<a href="/vault/' . $file['url'] . '" class="file text-decoration-none d-inline-block' . $activeClass . ' hover-underline">📄&nbsp;' . e($file['name']) . '</a>' . $masterTag;
                        $html .= '</li>';
                        \App\Services\CustomLogger::note($note, "(working on $currentFileName)" . $file["name"] . "\t=>\tdm: " . ($isDmFile ? 'true' : 'false'));
                    }
                    $html .= '</ul>';
                    return $html;
                }
            @endphp

            {!! renderTreeIndexPartial($tree, 0, $currentPath, $note, $masterFile) !!}

        </div>
        <hr class="border-secondary">
        <h5 class="text-warning mb-3 fw-bold">📣 Legenda</h5>
        <div class="legenda bg-dark-subtle p-3 rounded small font-monospace">
            <ul class="list-unstyled m-0">
                @if (Auth::user()->isMaster())
                <li class="mb-1">
                    <span class="text-danger fw-bold">
                        #dm
                    </span> : 
                    <span class="master-block">
                        esclusivo master
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
            // Create mobile open button
            let mobileOpenBtn = null;

            function ensureMobileButton() {
                if (!mobileOpenBtn) {
                    mobileOpenBtn = document.createElement('button');
                    mobileOpenBtn.className = 'btn btn-dark border-secondary position-fixed start-0 top-0 mt-3 ms-2 z-3';
                    mobileOpenBtn.innerHTML = '☰';
                    mobileOpenBtn.onclick = () => {
                        // Use the same toggle logic to ensure classes are consistent
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

            function updateMobileUI() {
                if (window.innerWidth <= 768) {
                    // closed by default on mobile
                    if (!sidebar.classList.contains('open')) {
                        sidebar.classList.add('collapsed');
                    }
                    ensureMobileButton();
                } else {
                    sidebar.classList.remove('open');
                    sidebar.classList.remove('collapsed');
                    removeMobileButton();
                }
            }

            function toggleSidebar() {
                if (window.innerWidth <= 768) {
                    // Mobile: toggle open class
                    const isOpen = sidebar.classList.contains('open');
                    if (isOpen) {
                        sidebar.classList.remove('open');
                        sidebar.classList.add('collapsed');
                    } else {
                        sidebar.classList.add('open');
                        sidebar.classList.remove('collapsed');
                    }
                } else {
                    // Desktop: toggle collapsed class
                    toggleBtn.blur(); // remove focus
                    const isCollapsed = sidebar.classList.contains('collapsed');
                    if (isCollapsed) {
                        sidebar.classList.remove('collapsed');
                    } else {
                        sidebar.classList.add('collapsed');
                    }
                }

                // Update toggle icon
                const icon = document.getElementById('toggle-icon');
                if (window.innerWidth <= 768) {
                    icon.textContent = sidebar.classList.contains('open') ? '✖' : '☰';
                } else {
                    icon.textContent = sidebar.classList.contains('collapsed') ? '▶' : '◀';
                }

                // trigger resize for graphs
                setTimeout(() => window.dispatchEvent(new Event('resize')), 250);
            }

            // Close sidebar on mobile when clicking outside
            document.addEventListener('click', function (e) {
                try {
                    if (window.innerWidth <= 768 && sidebar.classList.contains('open')) {
                        const path = e.composedPath ? e.composedPath() : (e.path || []);
                        if (!path.includes(sidebar) && !path.includes(mobileOpenBtn)) {
                            toggleSidebar();
                        }
                    }
                } catch (err) {
                    // ignore
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
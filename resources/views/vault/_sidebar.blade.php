@php
    // $tree expected, $path is the current note path array (e.g. ["Personaggi", "Giocanti", "Vargas (Paladino Nano, 65)"])
    $tree = $tree ?? [];
    $currentPath = $path ?? [];
@endphp
<aside class="vault-sidebar bg-secondary" id="vault-sidebar">
    <button class="sidebar-toggle" id="sidebar-toggle" title="Toggle Sidebar">
        <span id="toggle-icon">◀</span>
    </button>
    <div class="sidebar-content">
        <h5 class="text-warning mb-3">📚 Vault</h5>
        <div class="vault-tree">
            @php
                // Renderer that auto-expands folders matching the current path
                function renderTreeIndexPartial($items, $deep, $currentPath) {
                    // Check if current folder matches the path at this depth
                    $pathSegment = $currentPath[$deep] ?? null;
                    $isOnPath = $pathSegment !== null;

                    // Root level is always visible, nested levels depend on path match
                    if ($deep == 0) {
                        $html = '<ul>';
                    } else {
                        $html = '<ul style="display: none;" class="border-start border-secondary ps-3">';
                    }

                    foreach ($items as $key => $value) {
                        if ($key !== '_files' && $key !== '_dirs' && is_array($value)) {
                            // Check if this folder matches the current path segment (case-insensitive)
                            $folderMatchesPath = $isOnPath && strcasecmp($key, $pathSegment) === 0;
                            $openClass = $folderMatchesPath ? ' open' : '';

                            $html .= '<li>';
                            $html .= '<span class="folder' . $openClass . '" onclick="this.classList.toggle(\'open\'); this.nextElementSibling.style.display = this.classList.contains(\'open\') ? \'block\' : \'none\';">' . e($key) . '</span>';

                            // Render children, passing updated path context
                            $childHtml = renderTreeIndexPartial($value['_dirs'] ?? [], $deep + 1, $folderMatchesPath ? $currentPath : []);

                            // If folder matches path, show its children
                            if ($folderMatchesPath) {
                                $childHtml = str_replace('style="display: none;"', 'style="display: block;"', $childHtml);
                            }

                            $html .= $childHtml;
                            $html .= '</li>';
                        }
                    }

                    // Check if current file matches (last element of path)
                    $currentFileName = count($currentPath) > 0 ? end($currentPath) : null;

                    $files = $items['_files'] ?? [];
                    foreach ($files as $file) {
                        $isActiveFile = $currentFileName !== null && strcasecmp($file['name'], $currentFileName) === 0;
                        $activeClass = $isActiveFile ? ' active' : '';

                        $html .= '<li>';
                        $html .= '<a href="/vault/' . $file['url'] . '" class="file' . $activeClass . '">' . e($file['name']) . '</a>';
                        $html .= '</li>';
                    }

                    $html .= '</ul>';
                    return $html;
                }
            @endphp

            {!! renderTreeIndexPartial($tree, 0, $currentPath) !!}
            
        </div>
        <h5 class="text-warning mb-3">📣 Legenda</h5>
        <div class="legenda vault-tree bg-secondary-subtle p-2 pe-4 pb-4 pt-4 rounded">
            <ul>
                <li class="master-block wikilink">esclusivo master</li>
                <li class="wikilink">collegamenti ad altre pagine</li>
            </ul>
        </div>
    </div>

    <style>
        /* Sidebar base - docked in normal desktop flow */
        .vault-sidebar{
            flex: 0 0 300px;
            width: 300px;
            min-width: 40px;
            background: inherit;
            border-right: 1px solid rgba(255,255,255,0.04);
            overflow-y: auto;
            transition: width 0.2s ease, transform 0.25s ease;
            position: relative;
            padding-top: 8px;
            z-index: 5;
            border-radius: 6px;
        }

        /* Detached spacing from main content */
        .vault-layout { gap: 18px; display: flex; min-height: calc(100vh - 180px); }

        /* Toggle inside sidebar so it is never clipped */
        .sidebar-toggle{ position: absolute; top: 10px; right: 10px; z-index: 20; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.06); color: #fff; padding: 6px 8px; border-radius: 6px; cursor: pointer; }

        /* Collapsed (desktop): small width but toggle still visible */
        .vault-sidebar.collapsed{ width: 44px; min-width: 44px; }
        .vault-sidebar.collapsed .sidebar-content{ display: none; }

        .sidebar-content{ padding: 1rem; padding-top: 44px; }

        /* Mobile: off-canvas behavior and a floating opener */
        @media (max-width: 768px){
            .vault-sidebar{ position: fixed; left: 0; top: 0; bottom: 0; height: 100%; transform: translateX(-100%); box-shadow: 8px 0 24px rgba(0,0,0,0.6); }
            .vault-sidebar.open{ transform: translateX(0); }
            .vault-sidebar.collapsed{ transform: translateX(-100%); }

            /* Floating open button (visible when sidebar hidden) */
            .mobile-open-toggle{ display: block; position: fixed; left: 10px; top: 12px; z-index: 9999; background: rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.08); color: #fff; padding: 8px 10px; border-radius: 8px; }
        }

        /* Tree styles inside sidebar (slightly smaller) */
        .vault-tree{ font-family: 'Courier New', monospace; font-size: 0.92rem; }
        .vault-tree ul{ list-style: none; padding-left: 1rem; margin: 0; }
        .vault-tree li{ padding: 0.12rem 0; }
        .vault-tree .folder{ font-weight: 600; color: #f0ad4e; cursor: pointer; }
        .vault-tree .folder::before{ content: '📁\00a0'; }
        .vault-tree .folder.open::before{ content: '📂\00a0'; }
        .vault-tree .file{ color: #7fc3ff; }
        .vault-tree .file::before{ content: '📄\00a0'; }
        .vault-tree .file:hover{ color: #fff; text-decoration: underline; }
        .vault-tree .file.active{ color: #fff; font-weight: 600; background: rgba(255,255,255,0.1); border-radius: 4px; padding: 2px 6px; }
    </style>

    <script>
        (function(){
            const toggleBtn = document.getElementById('sidebar-toggle');
            const sidebar = document.getElementById('vault-sidebar');
            // Create mobile open button
            let mobileOpenBtn = null;

            function ensureMobileButton(){
                    if(!mobileOpenBtn){
                        mobileOpenBtn = document.createElement('button');
                        mobileOpenBtn.className = 'mobile-open-toggle';
                        mobileOpenBtn.innerHTML = '☰';
                        mobileOpenBtn.onclick = () => {
                            // Use the same toggle logic to ensure classes are consistent
                            toggleSidebar();
                        };
                        document.body.appendChild(mobileOpenBtn);
                    }
            }

            function removeMobileButton(){
                if(mobileOpenBtn){
                    mobileOpenBtn.remove();
                    mobileOpenBtn = null;
                }
            }

            function updateMobileUI(){
                if(window.innerWidth <= 768){
                    // closed by default on mobile
                    if(!sidebar.classList.contains('open')){
                        sidebar.classList.add('collapsed');
                    }
                    ensureMobileButton();
                } else {
                    sidebar.classList.remove('open');
                    sidebar.classList.remove('collapsed');
                    removeMobileButton();
                }
            }

            function toggleSidebar(){
                if(window.innerWidth <= 768){
                    // Mobile: toggle open class
                    const isOpen = sidebar.classList.contains('open');
                    if(isOpen){
                        sidebar.classList.remove('open');
                        sidebar.classList.add('collapsed');
                    } else {
                        sidebar.classList.add('open');
                        sidebar.classList.remove('collapsed');
                    }
                } else {
                    // Desktop: do not collapse the sidebar (keep always open)
                    // no-op to avoid reducing sidebar on desktop
                }

                // Update toggle icon
                const icon = document.getElementById('toggle-icon');
                if(window.innerWidth <= 768){
                    icon.textContent = sidebar.classList.contains('open') ? '✖' : '☰';
                } else {
                    icon.textContent = sidebar.classList.contains('collapsed') ? '▶' : '◀';
                }

                // trigger resize for graphs
                setTimeout(()=>window.dispatchEvent(new Event('resize')), 250);
            }

            // Close sidebar on mobile when clicking outside
            document.addEventListener('click', function(e){
                try {
                    if(window.innerWidth <= 768 && sidebar.classList.contains('open')){
                        const path = e.composedPath ? e.composedPath() : (e.path || []);
                        if(!path.includes(sidebar) && !path.includes(mobileOpenBtn)){
                            toggleSidebar();
                        }
                    }
                } catch(err) {
                    // ignore
                }
            });

            window.addEventListener('resize', updateMobileUI);
            document.addEventListener('DOMContentLoaded', updateMobileUI);

            if(toggleBtn){
                toggleBtn.addEventListener('click', toggleSidebar);
            }
        })();
    </script>
</aside>

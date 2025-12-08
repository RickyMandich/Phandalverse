@php
    // $tree expected
    $tree = $tree ?? [];
@endphp
<aside class="vault-sidebar" id="vault-sidebar">
    <button class="sidebar-toggle" id="sidebar-toggle" title="Toggle Sidebar">
        <span id="toggle-icon">◀</span>
    </button>
    <div class="sidebar-content">
        <h5 class="text-warning mb-3">📚 Vault</h5>
        <div class="vault-tree">
            @php
                // Reuse a safe renderer for the tree structure
                function renderTreeIndexPartial($items, $deep = 0) {
                    $html = $deep == 0 ? '<ul>' : '<ul style="display: none;" class="border-start border-secondary ps-3">';

                    foreach ($items as $key => $value) {
                        if ($key !== '_files' && $key !== '_dirs' && is_array($value)) {
                            $html .= '<li>';
                            $html .= '<span class="folder" onclick="this.classList.toggle(\'open\'); this.nextElementSibling.style.display = this.classList.contains(\'open\') ? \'block\' : \'none\';">' . e($key) . '</span>';
                            $html .= renderTreeIndexPartial($value['_dirs'] ?? [], $deep+1);
                            $html .= '</li>';
                        }
                    }

                    $files = $items['_files'] ?? [];
                    foreach ($files as $file) {
                        $html .= '<li>';
                        $html .= '<a href="/vault/' . $file['url'] . '" class="file">' . e($file['name']) . '</a>';
                        $html .= '</li>';
                    }

                    $html .= '</ul>';
                    return $html;
                }
            @endphp

            {!! renderTreeIndexPartial($tree) !!}
        </div>
    </div>

    <style>
        /* Sidebar base */
        .vault-sidebar{
            width: 300px;
            min-width: 250px;
            max-width: 400px;
            background: rgba(0,0,0,0.28);
            border-right: 1px solid rgba(255,255,255,0.04);
            box-shadow: 8px 0 24px rgba(0,0,0,0.4);
            overflow-y: auto;
            transition: width 0.28s ease, transform 0.25s ease;
            position: relative;
            padding-top: 8px;
            z-index: 5;
        }

        /* More detached look */
        .vault-layout .vault-sidebar{ margin-right: 16px; }

        .vault-sidebar.collapsed{ width: 36px; min-width: 36px; }
        .vault-sidebar .sidebar-toggle{ position: absolute; top: 10px; right: -18px; z-index: 10; }

        .sidebar-toggle{ background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.06); color: #fff; padding: 6px 8px; border-radius: 6px; cursor: pointer; }
        .sidebar-content{ padding: 1rem; padding-top: 44px; }

        /* Compact behaviour for small screens */
        @media (max-width: 768px){
            .vault-sidebar{ position: absolute; left: 0; top: 0; bottom: 0; height: 100%; transform: translateX(0); }
            .vault-sidebar.collapsed{ width: 28px; min-width: 28px; transform: translateX(-100%); }
            .vault-layout .vault-main{ margin-left: 0 !important; }
            .sidebar-toggle{ right: -26px; }
        }

        /* Tree styles inside sidebar (slightly smaller) */
        .vault-tree{ font-family: 'Courier New', monospace; font-size: 0.88rem; }
        .vault-tree ul{ list-style: none; padding-left: 1rem; margin: 0; }
        .vault-tree li{ padding: 0.12rem 0; }
        .vault-tree .folder{ font-weight: 600; color: #f0ad4e; cursor: pointer; }
        .vault-tree .folder::before{ content: '📁\00a0'; }
        .vault-tree .folder.open::before{ content: '📂\00a0'; }
        .vault-tree .file{ color: #7fc3ff; }
        .vault-tree .file::before{ content: '📄\00a0'; }
        .vault-tree .file:hover{ color: #fff; text-decoration: underline; }
    </style>

    <script>
        (function(){
            function toggleSidebar(){
                const sidebar = document.getElementById('vault-sidebar');
                const icon = document.getElementById('toggle-icon');
                sidebar.classList.toggle('collapsed');
                const collapsed = sidebar.classList.contains('collapsed');
                icon.textContent = collapsed ? '▶' : '◀';

                // On small screens, if collapsed hide further
                if(window.innerWidth <= 768){
                    if(collapsed) sidebar.style.transform = 'translateX(-100%)';
                    else sidebar.style.transform = 'translateX(0)';
                }

                // trigger resize for graphs
                setTimeout(()=>window.dispatchEvent(new Event('resize')), 250);
            }

            document.getElementById('sidebar-toggle').addEventListener('click', toggleSidebar);
        })();
    </script>
</aside>

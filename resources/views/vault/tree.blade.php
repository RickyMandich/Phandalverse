@extends('layouts.app')

@section('title', $title)

@section('style')
<style>
    .vault-tree {
        font-family: 'Courier New', monospace;
        padding: 2rem;
    }
    .vault-tree ul {
        list-style: none;
        padding-left: 1.5rem;
    }
    .vault-tree > ul {
        padding-left: 0;
    }
    .vault-tree li {
        position: relative;
        padding: 0.2rem 0;
    }
    .vault-tree .folder {
        font-weight: bold;
        color: #f0ad4e;
        cursor: pointer;
    }
    .vault-tree .folder::before {
        content: '📁 ';
    }
    .vault-tree .folder.open::before {
        content: '📂 ';
    }
    .vault-tree .file {
        color: #5bc0de;
    }
    .vault-tree .file::before {
        content: '📄 ';
    }
    .vault-tree .file:hover {
        color: #fff;
        text-decoration: underline;
    }
    .tree-container {
        background: rgba(0,0,0,0.2);
        border-radius: 8px;
        padding: 1.5rem;
    }
    .tree-title {
        margin-bottom: 1.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #444;
    }
</style>
@endsection

@section('content')
<div class="vault-layout d-flex">
    @include('vault._sidebar', ['tree' => $tree])

    <main class="vault-main flex-grow-1">
        <div class="vault-tree p-4">
    @if(Auth::check() && (Auth::isAdmin() || Auth::isMaster()))
        <div class="view-toggle mb-3">
            <a href="{{ route('vault.show') }}?view=tree" class="btn {{ ($currentView ?? 'tree') === 'tree' ? 'btn-primary' : 'btn-outline-secondary' }}">
                📂 Vista Albero
            </a>
            <a href="{{ route('vault.show') }}?view=graph" class="btn {{ ($currentView ?? 'tree') === 'graph' ? 'btn-primary' : 'btn-outline-secondary' }}">
                🕸️ Vista Grafo
            </a>
            @if(Auth::isAdmin())
            <form action="{{ route('admin.vault.setDefaultView') }}" method="POST" class="d-inline ms-3">
                @csrf
                <select name="view" class="form-select form-select-sm d-inline-block" style="width: auto;" onchange="this.form.submit()">
                    <option value="tree" {{ \App\Models\SystemSetting::getVaultDefaultView() === 'tree' ? 'selected' : '' }}>Default: Albero</option>
                    <option value="graph" {{ \App\Models\SystemSetting::getVaultDefaultView() === 'graph' ? 'selected' : '' }}>Default: Grafo</option>
                </select>
            </form>
            @endif
        </div>
    @endif

    <div class="tree-container">
        @if(isset($folderPath) && $folderPath)
            <nav class="mb-3">
                <a href="{{ route('vault.show') }}" class="text-info">📚 Vault</a>
                @php
                    $parts = explode('/', $folderPath);
                    $currentPath = '';
                @endphp
                @foreach($parts as $part)
                    @php
                        $currentPath = $currentPath ? $currentPath . '/' . $part : $part;
                    @endphp
                    <span class="text-muted"> / </span>
                    @if($loop->last)
                        <span class="text-warning">📂 {{ $part }}</span>
                    @else
                        <a href="{{ route('vault.show', ['note' => \App\Http\Controllers\VaultController::pathToCamelCase($currentPath)]) }}" class="text-info">{{ $part }}</a>
                    @endif
                @endforeach
            </nav>
            <h2 class="tree-title">📂 {{ basename($folderPath) }} - File Disponibili</h2>
        @else
            <h2 class="tree-title">📚 Vault - File Disponibili</h2>
        @endif
        @php
            function renderTree($items, $deep = 0) {
                $html = $deep == 0 ? '<ul>' : '<ul style="display: none;" class="border-start ps-4">';
                
                // Prima le cartelle
                foreach ($items as $key => $value) {
                    if ($key !== '_files' && $key !== '_dirs' && is_array($value)) {
                        $html .= '<li>';
                        $html .= '<span class="folder" onclick="this.classList.toggle(\'open\'); this.nextElementSibling.style.display = this.classList.contains(\'open\') ? \'block\' : \'none\';">' . e($key) . '</span>';
                        $html .= renderTree($value['_dirs'] ?? [], $deep+1);
                        $html .= '</li>';
                    }
                }
                
                // Poi i file
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
        
        {!! renderTree($tree) !!}
        </div>
    </main>
</div>
@endsection


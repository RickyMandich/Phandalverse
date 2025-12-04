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
<div class="vault-tree">
    <div class="tree-container">
        <h2 class="tree-title">📚 Vault - File Disponibili</h2>
        @php
            function renderTree($items, $deep = 0) {
                $html = $deep == 0 ? '<ul>' : '<ul style="display: block;" class="border-start ps-4">';
                
                // Prima le cartelle
                foreach ($items as $key => $value) {
                    if ($key !== '_files' && $key !== '_dirs' && is_array($value)) {
                        $html .= '<li>';
                        $html .= '<span class="folder open" onclick="this.classList.toggle(\'open\'); this.nextElementSibling.style.display = this.classList.contains(\'open\') ? \'block\' : \'none\';">' . e($key) . '</span>';
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
</div>
@endsection


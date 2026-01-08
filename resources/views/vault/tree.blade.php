@extends('layouts.app')

@section('title', $title)

@section('style')
    <style>
        /* Custom check for nested styling if needed */
        .cursor-pointer {
            cursor: pointer;
        }
    </style>
@endsection

@section('content')
    <div class="d-flex gap-0 h-100 w-100">
        @include('vault._sidebar', ['tree' => $tree, 'note' => $note])

        <main class="flex-grow-1 p-4" style="min-width: 0;">
            <div class="vault-tree-page">

                <div class="bg-body-tertiary p-4 rounded border border-secondary">
                    @if(isset($folderPath) && $folderPath)
                        <nav class="mb-3">
                            <a href="{{ route('vault.show') }}" class="text-info text-decoration-none">📚 Vault</a>
                            @php
                                $parts = explode('/', $folderPath);
                                $currentPath = '';
                            @endphp
                            @foreach($parts as $part)
                                @php
                                    $currentPath = $currentPath ? $currentPath . '/' . $part : $part;
                                @endphp
                                <span class="text-muted mx-1">/</span>
                                @if($loop->last)
                                    <span class="text-warning fw-bold">📂 {{ $part }}</span>
                                @else
                                    <a href="{{ route('vault.show', ['note' => \App\Http\Controllers\VaultController::pathToCamelCase($currentPath)]) }}"
                                        class="text-info text-decoration-none">{{ $part }}</a>
                                @endif
                            @endforeach
                        </nav>
                        <h2 class="display-6 mb-4 border-bottom border-secondary pb-2">📂 {{ basename($folderPath) }} - File
                            Disponibili</h2>
                    @else
                        <h2 class="display-6 mb-4 border-bottom border-secondary pb-2">📚 Vault - File Disponibili</h2>
                    @endif
                    @php
                        function renderTree($items, $deep = 0)
                        {
                            // Determine UL class based on depth
                            if ($deep == 0) {
                                $ulClass = 'list-unstyled m-0 font-monospace';
                            } else {
                                $ulClass = 'list-unstyled border-start border-secondary-subtle ps-4 d-none font-monospace';
                            }

                            $html = '<ul class="' . $ulClass . '">';

                            // Prima le cartelle (folders first)
                            foreach ($items as $key => $value) {
                                if ($key !== '_files' && $key !== '_dirs' && is_array($value)) {
                                    $displayName = $value['_label'] ?? $key;
                                    $html .= '<li class="my-1">';
                                    // Toggle logic: toggle 'open' on span, toggle 'd-none' on next UL
                                    $html .= '<span class="folder fw-bold text-warning cursor-pointer" onclick="this.classList.toggle(\'open\'); this.nextElementSibling.classList.toggle(\'d-none\'); this.innerText = this.classList.contains(\'open\') ? \'📂\u00a0\' + this.dataset.name : \'📁\u00a0\' + this.dataset.name;" data-name="' . e($displayName) . '">📁&nbsp;' . e($displayName) . '</span>';
                                    $html .= renderTree($value['_dirs'] ?? [], $deep + 1);
                                    $html .= '</li>';
                                }
                            }

                            // Poi i file (files next)
                            $files = $items['_files'] ?? [];
                            foreach ($files as $file) {
                                $html .= '<li class="my-1">';
                                $html .= '<a href="/vault/' . $file['url'] . '" class="file text-info text-decoration-none link-underline link-underline-opacity-0 link-underline-opacity-100-hover">📄&nbsp;' . e($file['name']) . '</a>';
                                $html .= '</li>';
                            }

                            $html .= '</ul>';
                            return $html;
                        }
                    @endphp

                    {!! renderTree($tree) !!}
                </div>
            </div>
        </main>
    </div>
@endsection
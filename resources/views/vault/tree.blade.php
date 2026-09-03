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
    @php
        \App\Services\CustomLogger::note($note, "-------------------inizio view tree-------------------");
        $campaignFolder = isset($campaign) ? ($campaign instanceof \App\Models\Campaign ? $campaign->folder_name : $campaign) : \App\Helpers\VaultHelper::resolveCampaignFolder(null);
    @endphp
    <div class="d-flex gap-0 h-100 w-100">
        @include('vault._sidebar', ['tree' => $fullTree, 'note' => $note, 'campaign' => $campaign, 'accessibleCampaigns' => $accessibleCampaigns])

        <main class="flex-grow-1 p-4" style="min-width: 0;">
            <div class="vault-tree-page">

                <div class="bg-body-tertiary p-4 rounded border border-secondary">
                    @if(isset($folderPath) && $folderPath)
                        <nav class="mb-3">
                            <a href="{{ route('vault.show', ['campaign' => $campaignFolder]) }}" class="text-info text-decoration-none">📚 Vault</a>
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
                                    <a href="{{ route('vault.show', ['campaign' => $campaignFolder, 'note' => \App\Http\Controllers\VaultController::pathToCamelCase($currentPath)]) }}"
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
                        if (!function_exists('renderTree')) {
                            function renderTree($items, $deep = 0, $note = '', $campaignFolder = 'newCampaign')
                            {
                                if ($deep == 0) {
                                    $ulClass = 'list-unstyled m-0 font-monospace';
                                } else {
                                    $ulClass = 'list-unstyled border-start border-secondary-subtle ps-4 d-none font-monospace';
                                }

                                $html = '<ul class="' . $ulClass . '">';

                                foreach ($items as $key => $value) {
                                    if ($key !== '_files' && $key !== '_dirs' && is_array($value)) {
                                        if (function_exists('sidebarHasFilesOrDirs') && !sidebarHasFilesOrDirs($value['_dirs'] ?? [])) {
                                            continue;
                                        }
                                        $displayName = $value['_label'] ?? $key;
                                        $html .= '<li class="my-1">';
                                        $html .= '<span class="folder fw-bold text-warning cursor-pointer" onclick="this.classList.toggle(\'open\'); this.nextElementSibling.classList.toggle(\'d-none\'); this.innerText = this.classList.contains(\'open\') ? \'📂\u00a0\' + this.dataset.name : \'📁\u00a0\' + this.dataset.name;" data-name="' . e($displayName) . '">📁&nbsp;' . e($displayName) . '</span>';
                                        $html .= renderTree($value['_dirs'] ?? [], $deep + 1, $note, $campaignFolder);
                                        $html .= '</li>';
                                    }
                                }

                                $files = $items['_files'] ?? [];
                                foreach ($files as $file) {
                                    $isDmFile = isset($file['dm']) && $file['dm'];
                                    $accessBadges = $file['access_badges'] ?? [];

                                    $tagsHtml = '';
                                    if ($isDmFile || !empty($accessBadges)) {
                                        $tagsHtml .= '<span class="ms-2 d-inline-flex align-items-center gap-1">';
                                        if ($isDmFile) {
                                            $tagsHtml .= '<span class="master-block">Master</span>';
                                        }
                                        foreach ($accessBadges as $badge) {
                                            $bColor = htmlspecialchars($badge['color'] ?: '#6c757d');
                                            $bName = htmlspecialchars($badge['name']);
                                            $tagsHtml .= '<span class="badge" style="background-color: ' . $bColor . '; color: #fff; font-size: 0.75em;">' . $bName . '</span>';
                                        }
                                        $tagsHtml .= '</span>';
                                    }

                                    $fileUrl = '/vault/' . $campaignFolder . '/' . $file['url'];
                                    $html .= '<li class="my-1 d-flex align-items-center">';
                                    $html .= '<a href="' . $fileUrl . '" class="file text-info text-decoration-none link-underline link-underline-opacity-0 link-underline-opacity-100-hover">📄&nbsp;' . e($file['name']) . '</a>' . $tagsHtml;
                                    $html .= '</li>';
                                }

                                $html .= '</ul>';
                                return $html;
                            }
                        }
                    @endphp

                    {!! renderTree($tree, 0, $note, $campaignFolder) !!}
                </div>
            </div>
        </main>
    </div>
@php
    \App\Services\CustomLogger::note($note, "-------------------fine view tree-------------------");
@endphp
@endsection
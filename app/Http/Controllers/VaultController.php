<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Services\AccessControlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\Services\MarkdownPreprocessor;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\CustomLogger;
use App\Helpers\VaultHelper;

class VaultController extends Controller
{
    /**
     * Entrypoint `/vault`: reindirizza alla campagna iniziale dell'utente
     * (priorità: default_campaign_id -> order più basso accessibile).
     */
    public function index(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        if ($user) {
            $campaign = $user->resolveInitialCampaign();
        } else {
            $campaign = Campaign::orderBy('order')->first();
        }

        if (!$campaign) {
            abort(404, 'Nessuna campagna configurata nel sistema.');
        }

        return redirect()->route('vault.show', ['campaign' => $campaign->folder_name]);
    }

    /**
     * Prepara il path per l'URL.
     * Dato che i file sono già normalizzati, restituiamo il path così com'è.
     */
    public static function pathToCamelCase(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Carica la configurazione estetica del grafo da `Vault/{folder}/.obsidian/graph-config.json`
     * Se il file non esiste o è invalido, ritorna una configurazione di default.
     */
    private function loadGraphConfig(Campaign|string|null $campaign = null): array
    {
        $folder = VaultHelper::resolveCampaignFolder($campaign);
        $obsidianDir = base_path('Vault/' . $folder . '/.obsidian');

        if (!File::exists($obsidianDir)) {
            $obsidianDir = base_path('Vault/.obsidian');
        }

        $configPath = $obsidianDir . DIRECTORY_SEPARATOR . 'graph-config.json';
        $graphJsonPath = $obsidianDir . DIRECTORY_SEPARATOR . 'graph.json';

        try {
            // Prefer explicit graph-config.json if present
            if (File::exists($configPath)) {
                $json = File::get($configPath);
                $data = json_decode($json, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    return $data;
                }
            }

            // Fallback: try to parse Obsidian's graph.json
            if (File::exists($graphJsonPath)) {
                $json = File::get($graphJsonPath);
                $data = json_decode($json, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    $colors = [];
                    $legend = [];
                    $groups = $data['colorGroups'] ?? [];

                    foreach ($groups as $grp) {
                        $query = $grp['query'] ?? '';
                        $colorObj = $grp['color'] ?? null;
                        $rgb = null;
                        if (is_array($colorObj) && isset($colorObj['rgb'])) {
                            $rgb = $colorObj['rgb'];
                        }

                        if ($rgb !== null) {
                            $hex = sprintf('#%06X', $rgb & 0xFFFFFF);
                        } else {
                            $hex = null;
                        }

                        // try to extract a key from query for the legend
                        $key = null;
                        if (str_contains($query, 'tag:#')) {
                            if (preg_match('/tag:#([a-zA-Z0-9_\-]+)/', $query, $m)) {
                                $key = strtolower($m[1]);
                            }
                        } elseif (str_contains($query, 'path:')) {
                            if (preg_match('/path:([a-zA-Z0-9_\-]+)/', $query, $m)) {
                                $key = strtolower($m[1]);
                            }
                        }

                        if ($key && $hex) {
                            $colors[$key] = $hex;
                            $labelMap = [
                                'universo' => 'Universi',
                                'città' => 'Città',
                                'citta' => 'Città',
                                'pg' => 'PG',
                                'png' => 'PNG',
                                'saga' => 'Saghe',
                                'evento' => 'Eventi',
                                'definizioni' => 'Definizioni',
                                'artefatti' => 'Artefatti'
                            ];
                            $legend[$key] = $labelMap[$key] ?? ucfirst($key);
                        }
                    }

                    // Map Obsidian forces to our config
                    return [
                        'colors' => $colors,
                        'legend' => $legend,
                        'colorGroups' => $groups,
                        'repelStrength' => $data['repelStrength'] ?? 20,
                        'linkStrength' => $data['linkStrength'] ?? 1,
                        'linkDistance' => $data['linkDistance'] ?? 30,
                        'centerStrength' => $data['centerStrength'] ?? 0.77,
                        'nodeSizeMultiplier' => $data['nodeSizeMultiplier'] ?? 1,
                        'lineSizeMultiplier' => $data['lineSizeMultiplier'] ?? 1,
                        'showTags' => $data['showTags'] ?? false,
                        'showAttachments' => $data['showAttachments'] ?? false,
                        'hideUnresolved' => $data['hideUnresolved'] ?? false,
                        'showOrphans' => $data['showOrphans'] ?? true,
                    ];
                }
            }
        } catch (\Exception $e) {
            // ignore and fallback to defaults
        }

        // Default values
        return [
            'colors' => [
                'universo' => '#D66B5C',
                'città' => '#D6C05C',
                'pg' => '#C6307F',
                'png' => '#7A7AFF',
                'saga' => '#5CD67A',
                'evento' => '#5CBCD6',
                'definizioni' => '#AD7FA8',
                'artefatti' => '#FCE94F',
                'default' => '#888'
            ],
            'legend' => [
                'universo' => 'Universi',
                'città' => 'Città',
                'pg' => 'PG',
                'png' => 'PNG',
                'saga' => 'Saghe',
                'evento' => 'Eventi',
                'altro' => 'Altri'
            ],
            'repelStrength' => 20,
            'linkStrength' => 1,
            'linkDistance' => 30,
            'centerStrength' => 0.77,
            'nodeSizeMultiplier' => 1,
            'lineSizeMultiplier' => 1,
        ];
    }

    /**
     * Converte il parametro dell'URL nel path reale.
     */
    public static function camelCaseToPath(string $camelPath): ?string
    {
        if (str_ends_with(strtolower($camelPath), '.md')) {
            $camelPath = substr($camelPath, 0, -3);
        }

        return $camelPath;
    }

    /**
     * Costruisce l'albero dei file del vault partendo dalla mappa (map.json)
     * e verificando l'esistenza dei file su disco per la specifica campagna.
     */
    public function buildFileTree(?string $basePath = null, $note = '', Campaign|string|null $campaign = null): array
    {
        $folder = VaultHelper::resolveCampaignFolder($campaign);
        $map = VaultHelper::getMap($note, $campaign);
        $tree = [];

        $startNode = $map;
        $basePathParts = ($basePath && $basePath !== '') ? explode('/', str_replace('\\', '/', $basePath)) : [];
        $validStart = true;
        $currentRealPath = '';

        foreach ($basePathParts as $part) {
            $lowerPart = strtolower($part);
            if (isset($startNode['directories'][$lowerPart])) {
                $scanPath = base_path('Vault/' . $folder . '/' . ($currentRealPath ?: ''));
                if (!File::isDirectory($scanPath)) {
                    $scanPath = base_path('Vault/' . ($currentRealPath ?: ''));
                }

                $realFolder = null;
                if (File::isDirectory($scanPath)) {
                    $items = scandir($scanPath);
                    foreach ($items as $item) {
                        if ($item !== '.' && $item !== '..' && strtolower($item) === $lowerPart && is_dir($scanPath . '/' . $item)) {
                            $realFolder = $item;
                            break;
                        }
                    }
                }

                if ($realFolder) {
                    $startNode = $startNode['directories'][$lowerPart];
                    $currentRealPath = $currentRealPath ? $currentRealPath . '/' . $realFolder : $realFolder;
                } else {
                    $validStart = false;
                    break;
                }
            } else {
                $validStart = false;
                break;
            }
        }

        if (!$validStart) {
            return [];
        }

        $result = $this->traverseMapAndBuildTree($startNode, $basePath ?? '', $note, $currentRealPath, $campaign);
        $tree = $result['tree'];
        $missingFiles = $result['missing'];

        if (!empty($missingFiles)) {
            $currentHash = md5(json_encode($missingFiles));
            $cacheKey = "vault_missing_files_hash_{$folder}";
            $lastHash = \Illuminate\Support\Facades\Cache::get($cacheKey);

            if ($currentHash !== $lastHash) {
                $isPartial = ($basePath !== null && $basePath !== '');
                $msg = "⚠️ <b>Vault Integrity Warning [{$folder}]" . ($isPartial ? " (Partial Scan)" : "") . "</b>\n\n";
                $msg .= "Found " . count($missingFiles) . " files/directories defined in map.json but missing on disk:\n\n";

                $limit = 20;
                foreach (array_slice($missingFiles, 0, $limit) as $file) {
                    $msg .= "- " . htmlspecialchars($file) . "\n";
                }
                if (count($missingFiles) > $limit) {
                    $msg .= "... and " . (count($missingFiles) - $limit) . " more.";
                }

                \App\Services\TelegramService::send($msg);
                \Illuminate\Support\Facades\Cache::put($cacheKey, $currentHash, 86400);
            }
        } elseif ($basePath === null || $basePath === '') {
            \Illuminate\Support\Facades\Cache::forget("vault_missing_files_hash_{$folder}");
        }

        return $tree;
    }

    private function traverseMapAndBuildTree($mapNode, $currentPath, $note, $currentRealPath = '', Campaign|string|null $campaign = null): array
    {
        $folder = VaultHelper::resolveCampaignFolder($campaign);
        $branch = ['_files' => []];
        $missing = [];

        $fullDirPath = base_path('Vault/' . $folder . '/' . $currentRealPath);
        if (!File::isDirectory($fullDirPath)) {
            $fullDirPath = base_path('Vault/' . $currentRealPath);
        }

        $realDirectoryContents = [];
        $realFileContents = [];

        if (File::isDirectory($fullDirPath)) {
            $items = scandir($fullDirPath);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..')
                    continue;
                $low = strtolower($item);
                $isDir = is_dir($fullDirPath . '/' . $item);
                if ($isDir) {
                    $realDirectoryContents[$low] = $item;
                } else {
                    $realFileContents[$low] = $item;
                }
            }
        }

        // 1. Process Directories
        if (isset($mapNode['directories'])) {
            foreach ($mapNode['directories'] as $dirKey => $dirData) {
                $dirKeyLower = strtolower($dirKey);
                $realDirName = $realDirectoryContents[$dirKeyLower] ?? null;
                $dirPathDisplay = $currentPath ? $currentPath . '/' . $dirKey : $dirKey;

                if ($realDirName) {
                    $nextRealPath = $currentRealPath ? $currentRealPath . '/' . $realDirName : $realDirName;
                    $subResult = $this->traverseMapAndBuildTree($dirData, $dirPathDisplay, $note, $nextRealPath, $campaign);

                    if ($this->hasFilesOrDirs($subResult['tree'])) {
                        $branch[$dirKey] = [
                            '_label' => $dirData['original'] ?? ucfirst($dirKey),
                            '_dirs' => $subResult['tree']
                        ];
                    }

                    $missing = array_merge($missing, $subResult['missing']);
                } else {
                    $missing[] = $dirPathDisplay . " (Directory)";
                }
            }
        }

        // 2. Process Files
        $processedRealFiles = [];
        if (isset($mapNode['files'])) {
            foreach ($mapNode['files'] as $fileName => $originalName) {
                $fileNameLower = strtolower($fileName);
                $cleanNameLower = $fileNameLower;
                $isPdfKey = str_ends_with($fileNameLower, '.pdf');
                $isMdKey = str_ends_with($fileNameLower, '.md');

                if ($isMdKey) {
                    $cleanNameLower = substr($fileNameLower, 0, -3);
                } elseif ($isPdfKey) {
                    $cleanNameLower = substr($fileNameLower, 0, -4);
                }

                $realFileName = $realFileContents[$fileNameLower] ?? null;
                if (!$realFileName) {
                    if (isset($realFileContents[$cleanNameLower . '.md'])) {
                        $realFileName = $realFileContents[$cleanNameLower . '.md'];
                    } elseif (isset($realFileContents[$cleanNameLower . '.pdf'])) {
                        $realFileName = $realFileContents[$cleanNameLower . '.pdf'];
                    }
                }

                if ($realFileName) {
                    $processedRealFiles[strtolower($realFileName)] = true;
                }

                $relPathNoExt = $currentPath ? $currentPath . '/' . $cleanNameLower : $cleanNameLower;
                $checkPathForLog = $relPathNoExt . ($isPdfKey ? '.pdf' : '.md');

                if ($realFileName) {
                    $targetPath = base_path('Vault/' . $folder . '/' . ($currentRealPath ? $currentRealPath . '/' . $realFileName : $realFileName));
                    if (!File::exists($targetPath)) {
                        $targetPath = base_path('Vault/' . ($currentRealPath ? $currentRealPath . '/' . $realFileName : $realFileName));
                    }

                    $ext = strtolower(pathinfo($realFileName, PATHINFO_EXTENSION));
                    $isPdf = ($ext === 'pdf');

                    $isDm = false;
                    $accessBadges = [];
                    $currentUser = Auth::user();

                    if ($isPdf) {
                        $pdfTag = VaultHelper::getPdfAccessTag($relPathNoExt . '.pdf', $campaign);
                        $isDm = AccessControlService::isDmOnly($pdfTag);

                        if (!AccessControlService::pdfIsVisibleTo($relPathNoExt . '.pdf', $currentUser, $campaign)) {
                            continue;
                        }

                        $pdfRequiredGroups = AccessControlService::requiredGroupsFromNoteTag($pdfTag);
                        if (!empty($pdfRequiredGroups)) {
                            $campaignId = ($campaign instanceof Campaign) ? $campaign->id : null;
                            $accessBadges = AccessControlService::computeBadgeGroups($currentUser, $pdfRequiredGroups, $campaignId);
                        }
                    } else {
                        $content = '';
                        try {
                            $content = File::get($targetPath);
                            $isDm = AccessControlService::isDmOnly($content);
                        } catch (\Throwable $e) {
                        }

                        if (!AccessControlService::noteIsVisibleTo($content, $currentUser, $campaign)) {
                            continue;
                        }

                        $campaignId = ($campaign instanceof Campaign) ? $campaign->id : null;
                        $requiredGroups = AccessControlService::requiredGroupsFromNoteTag($content);
                        if (!empty($requiredGroups)) {
                            $accessBadges = AccessControlService::computeBadgeGroups($currentUser, $requiredGroups, $campaignId);
                        }
                    }

                    $fileUrl = $isPdf ? $relPathNoExt . '.pdf' : $relPathNoExt;

                    $branch['_files'][] = [
                        'name' => $originalName,
                        'path' => $relPathNoExt,
                        'url' => self::pathToCamelCase($fileUrl),
                        'dm' => $isDm,
                        'access_badges' => $accessBadges,
                        'type' => $isPdf ? 'pdf' : 'md',
                        'is_pdf' => $isPdf,
                    ];
                } else {
                    if (count($missing) < 5) {
                        CustomLogger::note($note, "VaultTree Missing: '$checkPathForLog' (Key: $fileName)", "debug tree");
                    }
                    $missing[] = $checkPathForLog;
                }
            }
        }

        // Process unmapped PDF files in folder
        foreach ($realFileContents as $low => $rName) {
            if (isset($processedRealFiles[$low])) {
                continue;
            }
            $ext = strtolower(pathinfo($rName, PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $cleanRName = pathinfo($rName, PATHINFO_FILENAME);
                $relPathNoExt = $currentPath ? $currentPath . '/' . strtolower($cleanRName) : strtolower($cleanRName);
                $currentUser = Auth::user();
                $pdfTag = VaultHelper::getPdfAccessTag($relPathNoExt . '.pdf', $campaign);
                $isDm = AccessControlService::isDmOnly($pdfTag);

                if (!AccessControlService::pdfIsVisibleTo($relPathNoExt . '.pdf', $currentUser, $campaign)) {
                    continue;
                }

                $unmappedAccessBadges = [];
                $pdfRequiredGroups = AccessControlService::requiredGroupsFromNoteTag($pdfTag);
                if (!empty($pdfRequiredGroups)) {
                    $campaignId = ($campaign instanceof Campaign) ? $campaign->id : null;
                    $unmappedAccessBadges = AccessControlService::computeBadgeGroups($currentUser, $pdfRequiredGroups, $campaignId);
                }

                $branch['_files'][] = [
                    'name' => VaultHelper::getOriginalName($relPathNoExt . '.pdf', $note, $campaign),
                    'path' => $relPathNoExt,
                    'url' => self::pathToCamelCase($relPathNoExt . '.pdf'),
                    'dm' => $isDm,
                    'access_badges' => $unmappedAccessBadges,
                    'type' => 'pdf',
                    'is_pdf' => true,
                ];
            }
        }

        return ['tree' => $branch, 'missing' => $missing];
    }

    private function hasFilesOrDirs(array $treeNode): bool
    {
        if (!empty($treeNode['_files'])) {
            return true;
        }

        foreach ($treeNode as $key => $val) {
            if ($key !== '_files' && is_array($val)) {
                if (isset($val['_dirs']) && $this->hasFilesOrDirs($val['_dirs'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Costruisce i dati per la visualizzazione a grafo per la campagna specificata.
     */
    private function buildGraphData(?Campaign $campaign = null): array
    {
        $folder = VaultHelper::resolveCampaignFolder($campaign);
        CustomLogger::graph("--- Inizio generazione dati grafo [$folder] ---");

        $vaultPath = base_path('Vault/' . $folder);
        if (!File::isDirectory($vaultPath)) {
            $vaultPath = base_path('Vault');
        }

        $files = File::exists($vaultPath) ? File::allFiles($vaultPath) : [];
        CustomLogger::graph("File totali trovati nel vault: " . count($files));

        $nodes = [];
        $links = [];
        $nodeIndex = [];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $relativePath = str_replace('\\', '/', $file->getRelativePath());
            $name = $file->getFilenameWithoutExtension();

            if (!mb_check_encoding($name, 'UTF-8')) {
                $name = mb_convert_encoding($name, 'UTF-8', 'ISO-8859-1');
            }
            if (!mb_check_encoding($relativePath, 'UTF-8')) {
                $relativePath = mb_convert_encoding($relativePath, 'UTF-8', 'ISO-8859-1');
            }

            $fullPath = $relativePath ? $relativePath . '/' . $name . '.md' : $name . '.md';
            $content = File::get($file->getPathname());

            if (!AccessControlService::noteIsVisibleTo($content, null, $campaign)) {
                CustomLogger::graph("Nodo escluso dal grafo per permessi: $fullPath");
                continue;
            }

            $originalName = VaultHelper::getOriginalName($fullPath, 'system', $campaign);

            preg_match_all('/(?<=^|\s)#([a-zA-Z][a-zA-Z0-9_-]*)(?=\s|$)/m', $content, $tagMatches);
            $tags = $tagMatches[1] ?? [];

            $nodeId = $name;
            $nodeIndex[$name] = count($nodes);

            $nodes[] = [
                'id' => $nodeId,
                'name' => $originalName,
                'path' => $fullPath,
                'url' => self::pathToCamelCase($fullPath),
                'tags' => $tags,
                'connections' => 0,
            ];
        }

        foreach ($files as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $name = $file->getFilenameWithoutExtension();
            $content = File::get($file->getPathname());

            if (!AccessControlService::noteIsVisibleTo($content, null, $campaign)) {
                continue;
            }

            preg_match_all('/!?\[\[([^\]|#]+)(?:#[^\]|]*)?(?:\|([^\]]+))?\]\]/', $content, $matches);

            if (!empty($matches[0])) {
                foreach ($matches[1] as $linkedNote) {
                    $linkedNote = trim($linkedNote);
                    $linkedNoteName = basename($linkedNote, '.md');

                    if (isset($nodeIndex[$linkedNoteName])) {
                        $sourceIdx = $nodeIndex[$name] ?? null;
                        $targetIdx = $nodeIndex[$linkedNoteName];

                        if ($sourceIdx !== null && $sourceIdx !== $targetIdx) {
                            $links[] = [
                                'source' => $name,
                                'target' => $linkedNoteName,
                            ];
                            $nodes[$sourceIdx]['connections']++;
                            $nodes[$targetIdx]['connections']++;
                        }
                    }
                }
            }
        }

        $uniqueLinks = [];
        foreach ($links as $link) {
            $key = min($link['source'], $link['target']) . '-' . max($link['source'], $link['target']);
            if (!isset($uniqueLinks[$key])) {
                $uniqueLinks[$key] = $link;
            }
        }

        $connectedNodes = array_filter($nodes, function ($node) {
            return $node['connections'] > 0;
        });

        return [
            'nodes' => array_values($connectedNodes),
            'links' => array_values($uniqueLinks),
        ];
    }

    /**
     * Converte il parametro dell'URL nel path reale di una cartella.
     */
    public static function camelCaseToFolderPath(string $camelPath, Campaign|string|null $campaign = null): ?string
    {
        $folder = VaultHelper::resolveCampaignFolder($campaign);
        $full = base_path('Vault/' . $folder . '/' . $camelPath);
        if (is_dir($full)) {
            return $camelPath;
        }

        if (is_dir(base_path('Vault/' . $camelPath))) {
            return $camelPath;
        }

        return null;
    }

    /**
     * Verifica l'autorizzazione di accesso alla campagna per la richiesta corrente.
     */
    protected function checkCampaignAccess(Request $request, Campaign $campaign): void
    {
        $user = Auth::user();
        if ($user && !$user->hasAccessToCampaign($campaign)) {
            abort(403, 'Accesso non autorizzato a questa campagna.');
        }

        // Salva in sessione la campagna correntemente visualizzata
        session(['current_campaign' => $campaign->folder_name]);
    }

    /**
     * Recupera l'elenco delle campagne accessibili all'utente.
     */
    protected function getAccessibleCampaigns()
    {
        return Auth::check() ? Auth::user()->accessibleCampaigns() : Campaign::orderBy('order')->get();
    }

    public function search(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Campagna di contesto per l'albero della sidebar (non filtra i risultati):
        // priorità alla campagna vista di recente in sessione, poi a resolveInitialCampaign()
        $contextCampaign = null;
        $sessionFolder = session('current_campaign');
        if ($sessionFolder) {
            $contextCampaign = $user->accessibleCampaigns()->firstWhere('folder_name', $sessionFolder);
        }
        if (!$contextCampaign) {
            $contextCampaign = $user->resolveInitialCampaign();
        }

        if (!$contextCampaign) {
            abort(404, 'Nessuna campagna configurata nel sistema.');
        }

        $query = $request->query('q');
        $note = "search=>{$query}";
        $tree = $this->buildFileTree(null, note: $note, campaign: $contextCampaign);
        $resultsByCampaign = [];
        $totalResults = 0;

        if ($query) {
            $orderedCampaigns = $user->accessibleCampaigns();

            // Se l'utente ha una default_campaign_id accessibile, spostarla in prima posizione
            if ($user->default_campaign_id) {
                $defaultIndex = null;
                foreach ($orderedCampaigns as $idx => $camp) {
                    if ($camp->id === $user->default_campaign_id) {
                        $defaultIndex = $idx;
                        break;
                    }
                }
                if ($defaultIndex !== null && $defaultIndex !== 0) {
                    $defaultCampaign = $orderedCampaigns->pull($defaultIndex);
                    $orderedCampaigns->prepend($defaultCampaign);
                }
            }

            foreach ($orderedCampaigns as $camp) {
                $campResults = $this->searchInCampaign($query, $camp, $note);
                if (!empty($campResults)) {
                    $resultsByCampaign[] = [
                        'campaign' => $camp,
                        'results' => $campResults,
                    ];
                    $totalResults += count($campResults);
                }
            }

            // Redirect automatico a risultato singolo, nella campagna corretta (non necessariamente quella di contesto)
            if ($totalResults === 1) {
                $only = $resultsByCampaign[0];
                return redirect()->route('vault.show', [
                    'campaign' => $only['campaign']->folder_name,
                    'note' => $only['results'][0]['url']
                ]);
            }
        }

        $graphConfig = $this->loadGraphConfig($contextCampaign);

        return view('vault.search', [
            'query' => $query,
            'resultsByCampaign' => $resultsByCampaign,
            'totalResults' => $totalResults,
            'tree' => $tree,
            'note' => $note,
            'graphConfig' => $graphConfig,
            'campaign' => $contextCampaign,
            'accessibleCampaigns' => $this->getAccessibleCampaigns(),
        ]);
    }

    /**
     * Esegue la ricerca all'interno di una singola campagna, applicando lo stesso
     * filtro di visibilità/DM/gruppi già in uso, e arricchisce i risultati con url/is_pdf/directory.
     */
    private function searchInCampaign(string $query, Campaign $campaign, string $note): array
    {
        $results = VaultHelper::searchNotes($query, $note, $campaign);

        // Filter out DM-only / gruppo-riservato files per l'utente corrente
        $results = array_filter($results, function ($result) use ($campaign) {
            $folder = VaultHelper::resolveCampaignFolder($campaign);
            $isPdf = !empty($result['is_pdf']) || str_ends_with(strtolower($result['path']), '.pdf');
            $cleanPath = preg_replace('/\.(md|pdf)$/i', '', $result['path']);
            $ext = $isPdf ? '.pdf' : '.md';
            $path = base_path('Vault/' . $folder . '/' . $cleanPath . $ext);
            if (!File::exists($path)) {
                $path = base_path('Vault/' . $cleanPath . $ext);
            }
            if (File::exists($path)) {
                if ($isPdf) {
                    return AccessControlService::pdfIsVisibleTo($cleanPath . '.pdf', null, $campaign);
                }
                return AccessControlService::noteIsVisibleTo(File::get($path), null, $campaign);
            }
            return true;
        });

        foreach ($results as &$result) {
            $isPdf = !empty($result['is_pdf']) || str_ends_with(strtolower($result['path']), '.pdf');
            $cleanPath = preg_replace('/\.(md|pdf)$/i', '', $result['path']);
            $result['url'] = self::pathToCamelCase($isPdf ? $cleanPath . '.pdf' : $cleanPath);
            $result['is_pdf'] = $isPdf;
            $result['directory'] = dirname($result['path']);
            if ($result['directory'] === '.') {
                $result['directory'] = '';
            }
        }
        unset($result);

        return array_values($results);
    }

    public function show(Request $request, Campaign $campaign, $note = null)
    {
        $this->checkCampaignAccess($request, $campaign);

        return $this->renderVaultNote($request, $campaign, $note, false);
    }

    /**
     * Variante pubblica di `show()` per la pseudo-campagna condivisa "materiale":
     * nessuna riga in `campagne`, nessun controllo di accesso, consultabile anche da anonimo.
     */
    public function showMateriale(Request $request, $note = null)
    {
        return $this->renderVaultNote($request, 'materiale', $note, true);
    }

    /**
     * Corpo effettivo di `show()`/`showMateriale()`. $publicShared=true disattiva i redirect
     * al login imposti per le home/albero delle campagne vere (materiale è sempre pubblica).
     */
    protected function renderVaultNote(Request $request, Campaign|string $campaign, $note = null, bool $publicShared = false)
    {
        $folder = VaultHelper::resolveCampaignFolder($campaign);

        // Servire file binari (immagini) direttamente, ad eccezione dei PDF che se non richiesti con ?raw=1 o ?download=1 vanno visualizzati nella vista nota
        if ($note !== null) {
            $decoded = rawurldecode($note);
            if (strpos($decoded, '..') !== false) {
                abort(404);
            }
            $decoded = ltrim($decoded, '/\\');

            $candidate = base_path('Vault/' . $folder . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $decoded));
            if (!File::exists($candidate)) {
                $candidate = base_path('Vault' . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $decoded));
            }

            $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
            if (File::exists($candidate) && is_file($candidate) && $ext !== 'md') {
                if ($ext === 'pdf') {
                    if (!AccessControlService::pdfIsVisibleTo(str_replace('\\', '/', $decoded), Auth::user(), $campaign)) {
                        abort(403, 'Accesso negato');
                    }
                    if ($request->has('download') || $request->query('download')) {
                        $downloadName = VaultHelper::getOriginalName(basename($candidate), $note, $campaign);
                        if (!str_ends_with(strtolower($downloadName), '.pdf')) {
                            $downloadName .= '.pdf';
                        }
                        return response()->download($candidate, $downloadName);
                    }
                    if ($request->query('raw')) {
                        return response()->file($candidate, [
                            'Content-Type' => 'application/pdf',
                            'Content-Disposition' => 'inline; filename="' . basename($candidate) . '"'
                        ]);
                    }
                    // Altrimenti continua sotto per renderizzare la vista nota con l'anteprima PDF!
                } else {
                    return response()->file($candidate);
                }
            }
        }

        // Vista Home (Grafo + Albero)
        if ($note === null || $note === '') {
            if (!$publicShared && !Auth::check()) {
                return redirect()->route('login');
            }

            $note = "graph";
            $tree = $this->buildFileTree(null, note: $note, campaign: $campaign);

            // Materiale non ha un grafo (non richiesto): mostra solo l'albero, come la vista cartella.
            if ($publicShared) {
                return view('vault.tree', [
                    'title' => VaultHelper::resolveCampaignDisplayName($campaign),
                    'tree' => $tree,
                    'fullTree' => $tree,
                    'currentView' => 'tree',
                    'folderPath' => null,
                    'graphConfig' => [],
                    'note' => $note,
                    'campaign' => $campaign,
                    'accessibleCampaigns' => $this->getAccessibleCampaigns(),
                ]);
            }

            $graphData = $this->buildGraphData($campaign);
            $graphConfig = $this->loadGraphConfig($campaign);

            return view('vault.index', [
                'title' => 'Vault - ' . VaultHelper::resolveCampaignDisplayName($campaign),
                'tree' => $tree,
                'graphData' => $graphData,
                'graphConfig' => $graphConfig,
                'note' => $note,
                'campaign' => $campaign,
                'accessibleCampaigns' => $this->getAccessibleCampaigns(),
            ]);
        }

        CustomLogger::note($note, "Visualizzazione nota $note per campagna {$folder}");

        // Risoluzione Nota
        $filePath = MarkdownPreprocessor::findNotePath($note, $campaign);
        $cleanFilePath = preg_replace('/\.(md|pdf)$/i', '', $filePath);

        $fullSystemPath = base_path("Vault/{$folder}/{$cleanFilePath}.md");
        if (!File::exists($fullSystemPath)) {
            $fullSystemPath = base_path("Vault/{$cleanFilePath}.md");
        }

        $isPdf = false;
        $pdfSystemPath = null;

        if (!File::exists($fullSystemPath)) {
            // Verifica se è un file PDF
            $tryPdf = base_path("Vault/{$folder}/{$cleanFilePath}.pdf");
            if (!File::exists($tryPdf)) {
                $tryPdf = base_path("Vault/{$cleanFilePath}.pdf");
            }

            if (File::exists($tryPdf) && is_file($tryPdf)) {
                $isPdf = true;
                $pdfSystemPath = $tryPdf;
            } elseif (isset($candidate) && File::exists($candidate) && is_file($candidate) && isset($ext) && $ext === 'pdf') {
                $isPdf = true;
                $pdfSystemPath = $candidate;
            }
        }

        if (!$isPdf && !File::exists($fullSystemPath)) {
            CustomLogger::note($note, "Nota non trovata: $fullSystemPath", 'error');
            abort(404, 'Nota non trovata');
        }

        $pathSegments = preg_split('#[\\\\/]#', $cleanFilePath);
        $currentUser = Auth::user();

        if ($isPdf) {
            $pdfRelPath = self::pathToCamelCase($cleanFilePath . '.pdf');
            $pdfTag = VaultHelper::getPdfAccessTag($pdfRelPath, $campaign);
            $isDm = AccessControlService::isDmOnly($pdfTag);

            if (!AccessControlService::pdfIsVisibleTo($pdfRelPath, $currentUser, $campaign)) {
                CustomLogger::note($note, "Accesso negato: PDF riservato (dm o gruppo) per utente non autorizzato");
                abort(404, 'Nota non trovata');
            }

            $accessBadges = [];
            $pdfRequiredGroups = AccessControlService::requiredGroupsFromNoteTag($pdfTag);
            if (!empty($pdfRequiredGroups)) {
                $pdfCampaignId = ($campaign instanceof Campaign) ? $campaign->id : null;
                $accessBadges = AccessControlService::computeBadgeGroups($currentUser, $pdfRequiredGroups, $pdfCampaignId);
            }

            $title = VaultHelper::getOriginalName($cleanFilePath . '.pdf', $note, $campaign);
            $tree = $this->buildFileTree(null, note: $note, campaign: $campaign);
            $graphConfig = $this->loadGraphConfig($campaign);

            $pdfUrl = route('vault.raw', ['campaign' => $folder, 'note' => self::pathToCamelCase($cleanFilePath . '.pdf')]);
            $pdfDownloadUrl = route('vault.raw', ['campaign' => $folder, 'note' => self::pathToCamelCase($cleanFilePath . '.pdf'), 'download' => 1]);

            return view('vault.note', [
                'title' => $title,
                'html' => '',
                'tree' => $tree,
                'path' => $pathSegments,
                'graphConfig' => $graphConfig,
                'masterFile' => $isDm,
                'accessBadges' => $accessBadges,
                'availableAccessLevels' => [],
                'note' => $note,
                'campaign' => $campaign,
                'accessibleCampaigns' => $this->getAccessibleCampaigns(),
                'isPdf' => true,
                'pdfUrl' => $pdfUrl,
                'pdfDownloadUrl' => $pdfDownloadUrl,
            ]);
        }

        $content = File::get($fullSystemPath);

        if (!AccessControlService::noteIsVisibleTo($content, $currentUser, $campaign)) {
            CustomLogger::note($note, "Accesso negato: file DM o gruppo riservato per non-autorizzati");
            abort(404, 'Nota non trovata');
        }

        $masterFile = AccessControlService::isDmOnly($content);
        $hasMasterContent = $masterFile || (bool) preg_match('/#startMaster/i', $content);

        $accessBadges = [];
        $requiredGroups = AccessControlService::requiredGroupsFromNoteTag($content);
        if (!empty($requiredGroups)) {
            $noteCampaignId = ($campaign instanceof Campaign) ? $campaign->id : null;
            $accessBadges = AccessControlService::computeBadgeGroups($currentUser, $requiredGroups, $noteCampaignId);
        }

        // Estrai tutti i gruppi menzionati nella nota (sia a livello nota che a livello blocco)
        $allNoteGroups = [];
        if (preg_match_all('/#(?:access|startAccess)-([a-z0-9]+(?:_[a-z0-9]+)*)/i', $content, $mTags)) {
            foreach ($mTags[1] as $match) {
                $slugs = explode('_', strtolower($match));
                foreach ($slugs as $s) {
                    $allNoteGroups[$s] = true;
                }
            }
        }
        $noteGroupSlugs = array_keys($allNoteGroups);

        $availableAccessLevels = [];
        if ($currentUser && $currentUser->isMaster() && $hasMasterContent) {
            $availableAccessLevels[] = [
                'id' => 'master',
                'type' => 'master',
                'name' => 'Master',
                'slug' => 'master',
                'color' => '#ffc107',
            ];
        }

        if (!empty($noteGroupSlugs)) {
            $allGroups = AccessControlService::getAllGroups(($campaign instanceof Campaign) ? $campaign->id : null);
            foreach ($allGroups as $group) {
                $gSlug = strtolower($group->slug);
                if (in_array($gSlug, $noteGroupSlugs, true)) {
                    $canAccess = $currentUser && ($currentUser->isMaster() || $currentUser->hasAccessToAnyGroup([$gSlug]));
                    if ($canAccess) {
                        $availableAccessLevels[] = [
                            'id' => 'group_' . $gSlug,
                            'type' => 'group',
                            'name' => $group->name,
                            'slug' => $gSlug,
                            'color' => $group->color ?: '#6c757d',
                        ];
                    }
                }
            }
        }

        $title = VaultHelper::getOriginalName($filePath . '.md', $note, $campaign);
        // (nota) $campaign->id non viene mai letto direttamente qui sotto: le uniche letture
        // dirette sono già state sostituite sopra con l'accesso protetto instanceof-safe.

        $html = MarkdownPreprocessor::toHtml($content, $note, $campaign);
        $tree = $this->buildFileTree(null, note: $note, campaign: $campaign);
        $graphConfig = $this->loadGraphConfig($campaign);

        return view('vault.note', [
            'title' => $title,
            'html' => $html,
            'tree' => $tree,
            'path' => $pathSegments,
            'graphConfig' => $graphConfig,
            'masterFile' => $masterFile,
            'accessBadges' => $accessBadges,
            'availableAccessLevels' => $availableAccessLevels,
            'note' => $note,
            'campaign' => $campaign,
            'accessibleCampaigns' => $this->getAccessibleCampaigns(),
            'isPdf' => false,
            'pdfUrl' => null,
            'pdfDownloadUrl' => null,
        ]);

        // Vista Cartella
        $folderPath = self::camelCaseToFolderPath($note, $campaign);
        if ($folderPath !== null) {
            if (!$publicShared && !Auth::check()) {
                return redirect()->route('login');
            }

            $defaultView = SystemSetting::getVaultDefaultView();
            $requestedView = $request->query('view');

            if (Auth::check() && Auth::user()->isMaster() && $requestedView) {
                $currentView = $requestedView;
            } else {
                $currentView = $defaultView;
            }

            $tree = $this->buildFileTree($folderPath, $note, $campaign);
            $fullTree = $this->buildFileTree(null, note: $note, campaign: $campaign);
            $graphConfig = $this->loadGraphConfig($campaign);

            return view('vault.tree', [
                'title' => 'Vault - ' . basename($folderPath),
                'tree' => $tree,
                'fullTree' => $fullTree,
                'currentView' => 'tree',
                'folderPath' => $folderPath,
                'graphConfig' => $graphConfig,
                'note' => $note,
                'campaign' => $campaign,
                'accessibleCampaigns' => $this->getAccessibleCampaigns(),
            ]);
        }
    }

    /**
     * Ritorna il file markdown originale invece di renderizzarlo, oppure serve il PDF raw/download.
     */
    public function rawShow(Request $request, Campaign $campaign, $note = null)
    {
        $this->checkCampaignAccess($request, $campaign);

        return $this->renderVaultRaw($request, $campaign, $note);
    }

    /**
     * Variante pubblica di `rawShow()` per la pseudo-campagna condivisa "materiale".
     */
    public function rawShowMateriale(Request $request, $note = null)
    {
        return $this->renderVaultRaw($request, 'materiale', $note);
    }

    protected function renderVaultRaw(Request $request, Campaign|string $campaign, $note = null)
    {
        $folder = VaultHelper::resolveCampaignFolder($campaign);

        if ($note !== null) {
            $decoded = rawurldecode($note);
            if (strpos($decoded, '..') !== false) {
                abort(404);
            }
            $decoded = ltrim($decoded, '/\\');
            $candidate = base_path('Vault/' . $folder . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $decoded));
            if (!File::exists($candidate)) {
                $candidate = base_path('Vault' . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $decoded));
            }
            $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
            if (File::exists($candidate) && is_file($candidate) && $ext !== 'md') {
                if ($ext === 'pdf') {
                    $currentUser = Auth::user();
                    if (!AccessControlService::pdfIsVisibleTo(str_replace('\\', '/', $decoded), $currentUser, $campaign)) {
                        abort(403, 'Accesso negato');
                    }
                }

                if ($request->has('download') || $request->query('download')) {
                    $downloadName = VaultHelper::getOriginalName(basename($candidate), $note, $campaign);
                    if (!str_ends_with(strtolower($downloadName), '.' . $ext)) {
                        $downloadName .= '.' . $ext;
                    }
                    return response()->download($candidate, $downloadName);
                }

                $headers = [];
                if ($ext === 'pdf') {
                    $headers['Content-Type'] = 'application/pdf';
                    $headers['Content-Disposition'] = 'inline; filename="' . basename($candidate) . '"';
                }
                return response()->file($candidate, $headers);
            }
        }

        if ($note === null || $note === '') {
            abort(404, 'Nessuna nota specificata');
        }

        $filePath = MarkdownPreprocessor::findNotePath($note, $campaign);
        $cleanFilePath = preg_replace('/\.(md|pdf)$/i', '', $filePath);

        // Controlla prima se il target risolto è un file PDF
        $tryPdf = base_path("Vault/{$folder}/{$cleanFilePath}.pdf");
        if (!File::exists($tryPdf)) {
            $tryPdf = base_path("Vault/{$cleanFilePath}.pdf");
        }
        if (File::exists($tryPdf) && is_file($tryPdf)) {
            $currentUser = Auth::user();
            if (!AccessControlService::pdfIsVisibleTo(self::pathToCamelCase($cleanFilePath . '.pdf'), $currentUser, $campaign)) {
                abort(403, 'Accesso negato');
            }
            if ($request->has('download') || $request->query('download')) {
                $downloadName = VaultHelper::getOriginalName($cleanFilePath . '.pdf', $note, $campaign);
                if (!str_ends_with(strtolower($downloadName), '.pdf')) {
                    $downloadName .= '.pdf';
                }
                return response()->download($tryPdf, $downloadName);
            }
            return response()->file($tryPdf, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . basename($tryPdf) . '"'
            ]);
        }

        $fullSystemPath = base_path("Vault/{$folder}/{$cleanFilePath}.md");
        if (!File::exists($fullSystemPath)) {
            $fullSystemPath = base_path("Vault/{$cleanFilePath}.md");
        }

        if (!File::exists($fullSystemPath)) {
            abort(404, 'Nota non trovata');
        }

        $content = File::get($fullSystemPath);

        if (!AccessControlService::noteIsVisibleTo($content, null, $campaign)) {
            abort(403, 'Accesso negato');
        }

        if (!Auth::check() || !Auth::user()->isMaster()) {
            $content = MarkdownPreprocessor::filterMasterBlocks($content, $campaign);
        } else {
            $content = MarkdownPreprocessor::stripDmMarker($content);
        }

        return response($content)
            ->header('Content-Type', 'text/markdown; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . basename($fullSystemPath) . '"');
    }

    /**
     * Imposta la vista di default del vault (solo admin)
     */
    public function setDefaultView(Request $request)
    {
        $request->validate([
            'view' => 'required|in:tree,graph',
        ]);

        SystemSetting::setVaultDefaultView($request->view);

        return redirect()->back()->with('success', 'Vista di default aggiornata a: ' . $request->view);
    }
}

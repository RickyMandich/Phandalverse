<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\Services\MarkdownPreprocessor;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\CustomLogger;
use App\Helpers\VaultHelper;

class VaultController extends Controller
{
    /**
     * Prepara il path per l'URL.
     * Dato che i file sono già normalizzati, restituiamo il path così com'è.
     */
    public static function pathToCamelCase(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Carica la configurazione estetica del grafo da `Vault/.obsidian/graph-config.json`
     * Se il file non esiste o è invalido, ritorna una configurazione di default.
     */
    private function loadGraphConfig(): array
    {
        $obsidianDir = base_path('Vault/.obsidian');
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

            // Fallback: try to parse Obsidian's graph.json and map colorGroups
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

                        // try to extract a key from query
                        $key = null;
                        if (str_contains($query, 'tag:#')) {
                            // tag:#universo or tag:#png  tag:#phandalmain
                            if (preg_match('/tag:#([a-zA-Z0-9_\-]+)/', $query, $m)) {
                                $key = strtolower($m[1]);
                            }
                        } elseif (str_contains($query, 'path:')) {
                            if (preg_match('/path:([a-zA-Z0-9_\-]+)/', $query, $m)) {
                                $key = strtolower($m[1]);
                            }
                        }

                        if ($key) {
                            if ($hex) {
                                $colors[$key] = $hex;
                            }
                            // generate a readable label
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

                    return [
                        'colors' => $colors,
                        'legend' => $legend,
                    ];
                }
            }
        } catch (\Exception $e) {
            // ignore and fallback to defaults
        }

        // Default colors & legend (keeps previous hardcoded values)
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
            ]
        ];
    }

    /**
     * Converte il parametro dell'URL nel path reale.
     * Dato che l'URL usa già il path normalizzato, puliamo solo l'estensione se presente.
     */
    public static function camelCaseToPath(string $camelPath): ?string
    {
        // Rimuovi estensione .md se presente per uniformità
        if (str_ends_with(strtolower($camelPath), '.md')) {
            $camelPath = substr($camelPath, 0, -3);
        }

        return $camelPath;
    }

    /**
     * Costruisce l'albero dei file del vault partendo dalla mappa (map.json)
     * e verificando l'esistenza dei file su disco.
     * 
     * @param string|null $basePath Path relativo della cartella da cui partire
     */
    private function buildFileTree(?string $basePath = null, $note = ''): array
    {
        $map = VaultHelper::getMap($note);
        $tree = [];

        // Navigate to the start node in the map corresponding to $basePath
        $startNode = $map;
        $basePathParts = $basePath ? explode('/', $basePath) : [];
        $validStart = true;

        foreach ($basePathParts as $part) {
            $lowerPart = strtolower($part);
            if (isset($startNode['directories'][$lowerPart])) {
                $startNode = $startNode['directories'][$lowerPart];
            } else {
                $validStart = false;
                break;
            }
        }

        if (!$validStart) {
            return [];
        }

        // Traverse map and build tree
        $result = $this->traverseMapAndBuildTree($startNode, $basePath ?? '', $note);
        $tree = $result['tree'];
        $missingFiles = $result['missing'];

        // Handle missing files notification
        if (!empty($missingFiles)) {
            $currentHash = md5(json_encode($missingFiles));
            $cacheKey = 'vault_missing_files_hash';
            $lastHash = \Illuminate\Support\Facades\Cache::get($cacheKey);

            if ($currentHash !== $lastHash) {
                $msg = "⚠️ <b>Vault Integrity Warning</b>\n\n";
                $msg .= "Found " . count($missingFiles) . " files/directories defined in map.json but missing on disk:\n\n";

                // Limit the list length
                $limit = 20;
                foreach (array_slice($missingFiles, 0, $limit) as $file) {
                    $msg .= "- " . htmlspecialchars($file) . "\n";
                }
                if (count($missingFiles) > $limit) {
                    $msg .= "... and " . (count($missingFiles) - $limit) . " more.";
                }

                \App\Services\TelegramService::send($msg);

                // Cache the hash indefinitely (or for a long time)
                // The alert will only trigger again if the LIST of missing files changes.
                \Illuminate\Support\Facades\Cache::put($cacheKey, $currentHash, 86400); // 1 day
            }
        } else {
            // clear cache if fixed so next error triggers immediately
            \Illuminate\Support\Facades\Cache::forget('vault_missing_files_hash');
        }

        return $tree;
    }

    private function traverseMapAndBuildTree($mapNode, $currentPath, $note, $currentRealPath = ''): array
    {
        // resulting structure for this level
        $branch = ['_files' => []];
        $missing = [];

        // Scan current real directory to handle case-insensitive matching on Linux
        $fullDirPath = base_path('Vault/' . $currentRealPath);
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
                // Determine real directory name
                $dirKeyLower = strtolower($dirKey);
                $realDirName = $realDirectoryContents[$dirKeyLower] ?? null;

                $dirPathDisplay = $currentPath ? $currentPath . '/' . $dirKey : $dirKey;

                if ($realDirName) {
                    $nextRealPath = $currentRealPath ? $currentRealPath . '/' . $realDirName : $realDirName;

                    $subResult = $this->traverseMapAndBuildTree($dirData, $dirPathDisplay, $note, $nextRealPath);

                    // The view renderTreeIndexPartial recurses on $value['_dirs'].
                    // It expects the recursive content (files + subdirs) to be IN _dirs.
                    $branch[$dirKey] = [
                        '_label' => $dirData['original'] ?? ucfirst($dirKey),
                        '_dirs' => $subResult['tree'] // Put the entire sub-tree (including _files) here
                    ];

                    $missing = array_merge($missing, $subResult['missing']);
                } else {
                    // Directory missing on disk
                    $missing[] = $dirPathDisplay . " (Directory)";
                }
            }
        }

        // 2. Process Files
        if (isset($mapNode['files'])) {
            foreach ($mapNode['files'] as $fileName => $originalName) {
                // fileName in map usually has extension, e.g. "foo.md".
                // But sometimes keys in map might be messy.
                // We check if we can find a matching file in the scan.

                $fileNameLower = strtolower($fileName);
                $cleanNameLower = $fileNameLower;
                if (str_ends_with($fileNameLower, '.md')) {
                    $cleanNameLower = substr($fileNameLower, 0, -3);
                }

                // Try to find exact match first (normalized key)
                $realFileName = $realFileContents[$fileNameLower] ?? null;

                // If not found, try adding/removing .md
                if (!$realFileName) {
                    if (isset($realFileContents[$cleanNameLower . '.md'])) {
                        $realFileName = $realFileContents[$cleanNameLower . '.md'];
                    }
                }

                // Calculate display paths
                $relPathNoExt = $currentPath ? $currentPath . '/' . $cleanNameLower : $cleanNameLower;
                $checkPathForLog = $relPathNoExt . '.md';

                if ($realFileName) {
                    $fullPath = base_path('Vault/' . ($currentRealPath ? $currentRealPath . '/' . $realFileName : $realFileName));

                    // Check DM Status
                    $isDm = false;
                    try {
                        $content = File::get($fullPath);
                        $isDm = preg_match('/(?<=^|\s)#dm(?=\s|$)/i', $content) ? true : false;
                    } catch (\Throwable $e) {
                    }

                    if (($isDm && !Auth::check()) || ($isDm && !Auth::isMaster())) {
                        continue;
                    }

                    $branch['_files'][] = [
                        'name' => $originalName,
                        'path' => $relPathNoExt,
                        'url' => self::pathToCamelCase($relPathNoExt),
                        'dm' => $isDm
                    ];
                } else {
                    // Really missing
                    if (count($missing) < 5) {
                        CustomLogger::note($note, "VaultTree Missing: '$checkPathForLog' (Key: $fileName)", "debug tree");
                    }
                    $missing[] = $checkPathForLog;
                }
            }
        }

        return ['tree' => $branch, 'missing' => $missing];
    }

    /**
     * Costruisce i dati per la visualizzazione a grafo
     */
    private function buildGraphData(): array
    {
        $vaultPath = base_path('Vault');
        $files = File::allFiles($vaultPath);
        $nodes = [];
        $links = [];
        $nodeIndex = [];

        // Prima passata: crea tutti i nodi
        foreach ($files as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $relativePath = str_replace('\\', '/', $file->getRelativePath());
            $name = $file->getFilenameWithoutExtension();

            // Fix encoding
            if (!mb_check_encoding($name, 'UTF-8')) {
                $name = mb_convert_encoding($name, 'UTF-8', 'ISO-8859-1');
            }
            if (!mb_check_encoding($relativePath, 'UTF-8')) {
                $relativePath = mb_convert_encoding($relativePath, 'UTF-8', 'ISO-8859-1');
            }

            $fullPath = $relativePath ? $relativePath . '/' . $name . '.md' : $name . '.md';
            $content = File::get($file->getPathname());

            // Estrai i tag
            preg_match_all('/(?<=^|\s)#([a-zA-Z][a-zA-Z0-9_-]*)(?=\s|$)/m', $content, $tagMatches);
            $tags = $tagMatches[1] ?? [];

            $nodeId = $name; // Usa il nome come ID (Obsidian fa così)
            $nodeIndex[$name] = count($nodes);

            $nodes[] = [
                'id' => $nodeId,
                'name' => $name,
                'path' => $fullPath,
                'url' => self::pathToCamelCase($fullPath),
                'tags' => $tags,
                'connections' => 0,
            ];
        }

        // Seconda passata: trova i link (wikilinks)
        foreach ($files as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $name = $file->getFilenameWithoutExtension();
            if (!mb_check_encoding($name, 'UTF-8')) {
                $name = mb_convert_encoding($name, 'UTF-8', 'ISO-8859-1');
            }

            $content = File::get($file->getPathname());

            // Trova tutti i wikilinks
            preg_match_all('/\[\[([^\]|#]+)(?:#[^\]|]*)?(?:\|[^\]]+)?\]\]/', $content, $matches);

            foreach ($matches[1] as $linkedNote) {
                $linkedNote = trim($linkedNote);

                // Verifica se il nodo target esiste
                if (isset($nodeIndex[$linkedNote]) && isset($nodeIndex[$name])) {
                    $sourceIdx = $nodeIndex[$name];
                    $targetIdx = $nodeIndex[$linkedNote];

                    // Evita link duplicati e auto-referenze
                    if ($sourceIdx !== $targetIdx) {
                        $links[] = [
                            'source' => $name,
                            'target' => $linkedNote,
                        ];

                        // Incrementa il conteggio connessioni
                        $nodes[$sourceIdx]['connections']++;
                        $nodes[$targetIdx]['connections']++;
                    }
                }
            }
        }

        // Rimuovi link duplicati
        $uniqueLinks = [];
        foreach ($links as $link) {
            $key = min($link['source'], $link['target']) . '-' . max($link['source'], $link['target']);
            if (!isset($uniqueLinks[$key])) {
                $uniqueLinks[$key] = $link;
            }
        }

        // Filtra i nodi senza connessioni (nodi fantasma/orfani)
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
    public static function camelCaseToFolderPath(string $camelPath): ?string
    {
        if (is_dir(base_path('Vault/' . $camelPath))) {
            return $camelPath;
        }

        return null;
    }

    public function search(Request $request)
    {
        $query = $request->query('q');
        $note = "search=>$query";
        $tree = $this->buildFileTree(null, note: $note);
        $results = [];

        if ($query) {
            $results = VaultHelper::searchNotes($query, $note);

            // Filter out DM-only files for non-masters
            if (!Auth::check() || !Auth::isMaster()) {
                $results = array_filter($results, function ($result) {
                    $path = base_path('Vault/' . $result['path'] . '.md');
                    if (File::exists($path)) {
                        $content = File::get($path);
                        return !preg_match('/(?<=^|\s)#dm(?=\s|$)/i', $content);
                    }
                    return true;
                });
            }

            // Convert paths to URLs and format results
            foreach ($results as &$result) {
                $result['url'] = str_replace('.md', '', self::pathToCamelCase($result['path']));
                // result['path'] in map is normalized (la-ruota.md), we want to show the directory path
                $result['directory'] = dirname($result['path']);
                if ($result['directory'] === '.') {
                    $result['directory'] = '';
                }
            }

            CustomLogger::note($note, print_r($results, true));

            if (count($results) == 1) {
                return redirect()->route('vault.show', ['note' => $results[0]['url']]);
            }
        }

        $graphConfig = $this->loadGraphConfig();

        return view('vault.search', [
            'query' => $query,
            'results' => $results,
            'tree' => $tree,
            'note' => $note,
            'graphConfig' => $graphConfig,
        ]);
    }

    public function show(Request $request, $note = null)
    {
        // If the requested note segment decodes to an existing file inside Vault,
        // serve it directly (this allows image embeds to point to /vault/<encoded-path>). 
        if ($note !== null) {
            // decode the segment and normalize separators
            $decoded = rawurldecode($note);
            // security: block attempts to escape the vault
            if (strpos($decoded, '..') !== false) {
                abort(404);
            }
            $decoded = ltrim($decoded, '/\\');
            $candidate = base_path('Vault' . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $decoded));
            $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
            if (File::exists($candidate) && is_file($candidate) && $ext !== 'md') {
                return response()->file($candidate);
            } else {
                // If the decoded segment looks like an image path/name but the file
                // does not exist, log a warning to aid debugging.
                $ext = strtolower(pathinfo($decoded, PATHINFO_EXTENSION));
                $imageExt = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'bmp', 'avif'];
                if (in_array($ext, $imageExt) || $ext === '') {
                    Log::warning('Requested vault file not found: ' . $candidate . ' (decoded from: ' . $note . ')');
                }
            }
        }
        // Se non viene passato il parametro note, mostra la home del vault
        // con pannello laterale (albero) e vista a grafo nella stessa pagina.
        if ($note === null || $note === '') {
            $note = "graph";
            $tree = $this->buildFileTree(null, note: $note);
            $graphData = $this->buildGraphData();
            $graphConfig = $this->loadGraphConfig();
            return view('vault.index', [
                'title' => 'Vault',
                'tree' => $tree,
                'graphData' => $graphData,
                'graphConfig' => $graphConfig,
                'note' => $note,
            ]);
        }

        CustomLogger::note($note, "Visualizzazione nota $note");

        // Prima controlla se è una cartella
        $folderPath = self::camelCaseToFolderPath($note);
        if ($folderPath !== null) {
            Log::info("È una cartella: $folderPath");


            // Determina quale vista mostrare
            $defaultView = SystemSetting::getVaultDefaultView();
            $requestedView = $request->query('view');

            if (Auth::check() && Auth::isMaster() && $requestedView) {
                $currentView = $requestedView;
            } else {
                $currentView = $defaultView;
            }

            // Per le cartelle mostriamo solo la vista albero
            CustomLogger::note($note, "inizio a generare il tree", "VaultController:468");
            $tree = $this->buildFileTree($folderPath, $note);
            CustomLogger::note($note, "inizio a generare il fulltree", "VaultController:470");
            $fullTree = $this->buildFileTree(null, note: $note);
            $graphConfig = $this->loadGraphConfig();
            return view('vault.tree', [
                'title' => 'Vault - ' . basename($folderPath),
                'tree' => $tree,
                'fullTree' => $fullTree,
                'currentView' => 'tree',
                'folderPath' => $folderPath,
                'graphConfig' => $graphConfig,
                'note' => $note,
            ]);
        }

        // Risolve il path reale della nota (gestendo il case-sensitivity del server)
        $filePath = MarkdownPreprocessor::findNotePath($note);

        $fullSystemPath = base_path("Vault/" . $filePath . ".md");

        $pathSegments = preg_split('#[\\\\/]#', $filePath);

        Log::info("Il path reale della nota è: " . print_r($pathSegments, true));


        CustomLogger::note($note, "cerco la nota: $fullSystemPath");
        if (!File::exists($fullSystemPath)) {
            CustomLogger::note($note, "Nota non trovata: $fullSystemPath", 'error');
            return view('errors.504');
        }

        $content = File::get($fullSystemPath);
        if (env('DEBUG_HTML', false))
            CustomLogger::note($note, "Contenuto ORIGINALE dal file: " . $content);

        CustomLogger::note($note, "ora controllo se è il master: " . Auth::isMaster() . "(master=" . Auth::getMaster() . ") e l'utente è " . Auth::getName());
        // Gestione blocchi master e DM
        // If file is DM-only and user is not master, act as if file doesn't exist
        $masterFile = false;
        if (!Auth::isMaster() && preg_match('/(?<=^|\s)#dm(?=\s|$)/i', $content)) {
            CustomLogger::note($note, "Accesso negato: file DM per non-master");
            abort(504, 'Nota non trovata');
        } else if (preg_match('/(?<=^|\s)#dm(?=\s|$)/i', $content)) {
            $masterFile = true;
        }

        if (!Auth::isMaster()) {
            CustomLogger::note($note, "Filtro i blocchi master");
            $content = MarkdownPreprocessor::filterMasterBlocks($content);
        } else {
            CustomLogger::note($note, "Mostro i blocchi master");
            // Non rimuoviamo qui i marker: il renderizer li gestirà correttamente.
            // Rimuovi solo il marker #dm per i master (se presente)
            $content = MarkdownPreprocessor::stripDmMarker($content);
        }

        if (env('DEBUG_HTML', false))
            CustomLogger::note($note, "Contenuto dopo filtro: " . $content);

        if (preg_match('/(?<=^|[\\\\\\/])[^\\\\\\/]+(?=\\.md$)/', $fullSystemPath, $matches)) {
            // Use VaultHelper to get the original displayed title if possible
            // $fullSystemPath is absolute here. We need relative path to look up in map.
            $relativePathForHelper = str_replace(base_path('Vault/'), '', $fullSystemPath);
            // Fix slashes
            $relativePathForHelper = str_replace('\\', '/', $relativePathForHelper);

            $title = VaultHelper::getOriginalName($relativePathForHelper, $note);
            CustomLogger::note($note, "il path del file è: $fullSystemPath e il titolo del file è: $title", "VaultController:532(show)");
        }


        // Converte Markdown → HTML con supporto wikilink/embed
        $html = MarkdownPreprocessor::toHtml($content, $note);
        if (env('DEBUG_HTML', false))
            CustomLogger::note($note, "HTML generato: " . $html);
        $tree = $this->buildFileTree(null, note: $note);
        $graphConfig = $this->loadGraphConfig();
        return view('vault.note', [
            'title' => $title,
            'html' => $html,
            'tree' => $tree,
            'path' => $pathSegments,
            'graphConfig' => $graphConfig,
            'masterFile' => $masterFile,
            'note' => $note,
        ]);
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

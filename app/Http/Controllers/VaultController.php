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
     * Costruisce l'albero dei file del vault
     * @param string|null $basePath Path relativo della cartella da cui partire (es. "Personaggi/Giocanti")
     */
    private function buildFileTree(?string $basePath = null, $note): array
    {
        $vaultPath = base_path('Vault/' . $basePath);
        $files = File::allFiles($vaultPath);
        $tree = [];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            // Check if file contains #dm tag (for all users, to include in tree data)
            $isDmFile = false;
            try {
                $contentPreview = File::get($file->getPathname());
                $isDmFile = preg_match('/(?<=^|\s)#dm(?=\s|$)/i', $contentPreview) ? true : false;
            } catch (\Throwable $e) {
                // if file can't be read, skip it to avoid breaking the tree
                continue;
            }

            // If user is not master, skip files marked with #dm so they appear nonexistent
            if ((!Auth::check() || !Auth::isMaster()) && $isDmFile) {
                continue;
            }

            $relativePath = str_replace('\\', '/', $file->getRelativePath());
            $name = $file->getFilenameWithoutExtension();

            // Fix encoding per caratteri speciali (es. à, è, ò, ù)
            if (!mb_check_encoding($name, 'UTF-8')) {
                $name = mb_convert_encoding($name, 'UTF-8', 'ISO-8859-1');
            }
            if (!mb_check_encoding($relativePath, 'UTF-8')) {
                $relativePath = mb_convert_encoding($relativePath, 'UTF-8', 'ISO-8859-1');
            }

            // // Se abbiamo un basePath, filtra solo i file che iniziano con quel path
            // if ($basePath !== null) {
            //     if (!str_starts_with($relativePath, $basePath)) {
            //         continue;
            //     }
            //     // Rimuovi il basePath dal relativePath per costruire l'albero relativo
            //     $relativePath = substr($relativePath, strlen($basePath));
            //     $relativePath = ltrim($relativePath, '/');
            // }

            $fullRelativePath = $relativePath ? $relativePath . '/' . $name : $name;

            // Costruisce la struttura ad albero
            $parts = $relativePath ? explode('/', $relativePath) : [];
            $current = &$tree;
            $currentDirPath = $basePath ?? '';

            foreach ($parts as $part) {
                // Costruisci il path della cartella corrente per cercare il nome originale nel DB/Map
                $currentDirPath = $currentDirPath ? $currentDirPath . '/' . $part : $part;

                if (!isset($current[$part])) {
                    CustomLogger::note($note, "currentDirPath=>" . $currentDirPath, "VaultController:214");
                    $current[$part] = [
                        '_files' => [],
                        '_dirs' => [],
                        '_label' => VaultHelper::getOriginalDirectoryName($currentDirPath, $note)
                    ];
                }
                $current = &$current[$part]['_dirs'];
            }

            // Mantieni l'URL originale (con basePath) per i link
            $originalFullPath = $basePath ? $basePath . '/' . $fullRelativePath : $fullRelativePath;

            $current['_files'][] = [
                'name' => VaultHelper::getNormalizedName($fullRelativePath . '.md', $note), // Use original name for display (append extension for lookup)
                'path' => $fullRelativePath,
                'url' => self::pathToCamelCase($originalFullPath),
                'dm' => $isDmFile, // Boolean indicating if file is DM-only
            ];
        }

        return $tree;
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
                $imageExt = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'bmp'];
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

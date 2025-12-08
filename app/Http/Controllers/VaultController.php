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

class VaultController extends Controller
{
    /**
     * Converte un path reale in camelCase per l'URL
     * Es: "Personaggi/La Ruota" -> "personaggi/laRuota"
     */
    public static function pathToCamelCase(string $path): string
    {
        $parts = explode('/', $path);
        $result = [];

        foreach ($parts as $part) {
            // Rimuove spazi extra e converte in camelCase
            $words = preg_split('/\s+/', trim($part));
            $camelPart = Str::lower(array_shift($words));
            foreach ($words as $word) {
                $camelPart .= Str::ucfirst(Str::lower($word));
            }
            $result[] = $camelPart;
        }

        return implode('/', $result);
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
            if (\Illuminate\Support\Facades\File::exists($configPath)) {
                $json = \Illuminate\Support\Facades\File::get($configPath);
                $data = json_decode($json, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    return $data;
                }
            }

            // Fallback: try to parse Obsidian's graph.json and map colorGroups
            if (\Illuminate\Support\Facades\File::exists($graphJsonPath)) {
                $json = \Illuminate\Support\Facades\File::get($graphJsonPath);
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
     * Converte un path camelCase in path reale cercando nel file index
     * Es: "personaggi/laRuota" -> "Personaggi/La Ruota"
     */
    public static function camelCaseToPath(string $camelPath): ?string
    {
        $index = MarkdownPreprocessor::buildFileIndex();

        // Cerca nel file index un match (case-insensitive)
        $camelPathLower = strtolower($camelPath);
        foreach ($index as $name => $realPath) {
            if (strtolower(self::pathToCamelCase($realPath)) === $camelPathLower) {
                return $realPath;
            }
        }

        return null;
    }

    /**
     * Costruisce l'albero dei file del vault
     * @param string|null $basePath Path relativo della cartella da cui partire (es. "Personaggi/Giocanti")
     */
    private function buildFileTree(?string $basePath = null): array
    {
        $vaultPath = base_path('Vault');
        $files = File::allFiles($vaultPath);
        $tree = [];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'md') {
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

            // Se abbiamo un basePath, filtra solo i file che iniziano con quel path
            if ($basePath !== null) {
                if (!str_starts_with($relativePath, $basePath)) {
                    continue;
                }
                // Rimuovi il basePath dal relativePath per costruire l'albero relativo
                $relativePath = substr($relativePath, strlen($basePath));
                $relativePath = ltrim($relativePath, '/');
            }

            $fullRelativePath = $relativePath ? $relativePath . '/' . $name : $name;

            // Costruisce la struttura ad albero
            $parts = $relativePath ? explode('/', $relativePath) : [];
            $current = &$tree;

            foreach ($parts as $part) {
                if (!isset($current[$part])) {
                    $current[$part] = ['_files' => [], '_dirs' => []];
                }
                $current = &$current[$part]['_dirs'];
            }

            // Mantieni l'URL originale (con basePath) per i link
            $originalFullPath = $basePath ? $basePath . '/' . $fullRelativePath : $fullRelativePath;

            $current['_files'][] = [
                'name' => $name,
                'path' => $fullRelativePath,
                'url' => self::pathToCamelCase($originalFullPath),
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

            $fullPath = $relativePath ? $relativePath . '/' . $name : $name;
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
     * Converte un path camelCase in path reale cercando cartelle
     * Es: "personaggi/giocanti" -> "Personaggi/Giocanti"
     */
    public static function camelCaseToFolderPath(string $camelPath): ?string
    {
        $vaultPath = base_path('Vault');

        // Crea un indice di tutte le cartelle (ricorsivamente)
        $folderIndex = [];
        $stack = [$vaultPath];

        while (!empty($stack)) {
            $currentDir = array_pop($stack);
            $subDirs = File::directories($currentDir);

            foreach ($subDirs as $dir) {
                $relativePath = str_replace('\\', '/', substr($dir, strlen($vaultPath) + 1));

                // Fix encoding
                if (!mb_check_encoding($relativePath, 'UTF-8')) {
                    $relativePath = mb_convert_encoding($relativePath, 'UTF-8', 'ISO-8859-1');
                }

                // Salta le cartelle nascoste (come .obsidian)
                if (str_starts_with(basename($relativePath), '.')) {
                    continue;
                }

                $folderIndex[strtolower(self::pathToCamelCase($relativePath))] = $relativePath;
                $stack[] = $dir;
            }
        }

        $camelPathLower = strtolower($camelPath);
        return $folderIndex[$camelPathLower] ?? null;
    }

    public function show(Request $request, $note = null)
    {
        // Se non viene passato il parametro note, mostra la home del vault
        // con pannello laterale (albero) e vista a grafo nella stessa pagina.
        if ($note === null || $note === '') {
            $tree = $this->buildFileTree();
            $graphData = $this->buildGraphData();
            $graphConfig = $this->loadGraphConfig();
            return view('vault.index', [
                'title' => 'Vault',
                'tree' => $tree,
                'graphData' => $graphData,
                'graphConfig' => $graphConfig,
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
            $tree = $this->buildFileTree($folderPath);
            $graphConfig = $this->loadGraphConfig();
            return view('vault.tree', [
                'title' => 'Vault - ' . basename($folderPath),
                'tree' => $tree,
                'currentView' => 'tree',
                'folderPath' => $folderPath,
                'graphConfig' => $graphConfig,
            ]);
        }

        // Converti il path camelCase in path reale
        $realPath = self::camelCaseToPath($note);

        if ($realPath === null) {
            // Fallback: prova con il path originale (per retrocompatibilità)
            $realPath = $note;
        }

        $path = base_path("Vault/" . $realPath . ".md");

        CustomLogger::note($note, "cerco la nota: $path");
        if (!File::exists($path)) {
            CustomLogger::note($note, "Nota non trovata: $path", 'error');
            abort(404, "Nota non trovata");
        }

        $content = File::get($path);
        CustomLogger::note($note, "Contenuto ORIGINALE dal file: " . $content);

        CustomLogger::note($note, "ora controllo se è il master: ".Auth::isMaster()."(master=".Auth::getMaster().") e l'utente è ".Auth::getName());
        // Gestione blocchi master
        if(!Auth::isMaster()) {
            CustomLogger::note($note, "Filtro i blocchi master");
            $content = MarkdownPreprocessor::filterMasterBlocks($content);
        }else{
            CustomLogger::note($note, "Mostro i blocchi master");
            $content = MarkdownPreprocessor::stripMasterMarkers($content);
        }

        CustomLogger::note($note, "Contenuto dopo filtro: " . $content);

        if (preg_match('/(?<=^|[\\\\\\/])[^\\\\\\/]+(?=\\.md$)/', $path, $matches)) {
            $title = $matches[0];
            $title = ucfirst($title);
            Log::info("il path del file è: $path e il titolo del file è: $title");
        }


        // Converte Markdown → HTML con supporto wikilink/embed
        $html = MarkdownPreprocessor::toHtml($content);
        CustomLogger::note($note, "HTML generato: " . $html);
        $tree = $this->buildFileTree();
        $graphConfig = $this->loadGraphConfig();
        return view('vault.note', [
            'title' => $title,
            'html'  => $html,
            'tree' => $tree,
            'graphConfig' => $graphConfig,
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

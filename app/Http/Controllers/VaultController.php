<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use App\Services\MarkdownPreprocessor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
     */
    private function buildFileTree(): array
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

            $current['_files'][] = [
                'name' => $name,
                'path' => $fullRelativePath,
                'url' => self::pathToCamelCase($fullRelativePath),
            ];
        }

        return $tree;
    }

    public function show($note = null)
    {
        // Se non viene passato il parametro note, mostra l'albero dei file
        if ($note === null || $note === '') {
            $tree = $this->buildFileTree();
            return view('vault.tree', [
                'title' => 'Vault',
                'tree' => $tree,
            ]);
        }

        Log::info("Visualizzazione nota $note");

        // Converti il path camelCase in path reale
        $realPath = self::camelCaseToPath($note);

        if ($realPath === null) {
            // Fallback: prova con il path originale (per retrocompatibilità)
            $realPath = $note;
        }

        $path = base_path("Vault/" . $realPath . ".md");

        Log::info("cerco la nota: $path");
        if (!File::exists($path)) {
            Log::warning("Nota non trovata: $path");
            abort(404, "Nota non trovata");
        }

        $content = File::get($path);

        // Rimuove i blocchi master
        if(!Auth::isMaster()) {
            $content = MarkdownPreprocessor::filterMasterBlocks($content);
        }

        // Converte Markdown → HTML con supporto wikilink/embed
        $html = MarkdownPreprocessor::toHtml($content);
        return view('vault.note', [
            'title' => $realPath,
            'html'  => $html,
        ]);
    }
}

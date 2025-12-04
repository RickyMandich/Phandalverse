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

            // Fix encoding per caratteri speciali (es. à, è, ò, ù)
            if (!mb_check_encoding($name, 'UTF-8')) {
                $name = mb_convert_encoding($name, 'UTF-8', 'ISO-8859-1');
            }
            if (!mb_check_encoding($relativePath, 'UTF-8')) {
                $relativePath = mb_convert_encoding($relativePath, 'UTF-8', 'ISO-8859-1');
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
        Log::info("Contenuto ORIGINALE dal file: " . $content);

        Log::info("ora controllo se è il master: ".Auth::isMaster()."(master=".Auth::getMaster().") e l'utente è ".Auth::getName());
        // Gestione blocchi master
        if(!Auth::isMaster()) {
            Log::info("Filtro i blocchi master");
            $content = MarkdownPreprocessor::filterMasterBlocks($content);
        }else{
            Log::info("Mostro i blocchi master");
            $content = MarkdownPreprocessor::stripMasterMarkers($content);
        }

        Log::info("Contenuto dopo filtro: " . $content);

        if (preg_match('/(?<=^|[\\\\\\/])[^\\\\\\/]+(?=\\.md$)/', $path, $matches)) {
            $title = $matches[0];
            Log::info("il path del file è: $path e il titolo del file è: $title");
        }


        // Converte Markdown → HTML con supporto wikilink/embed
        $html = MarkdownPreprocessor::toHtml($content);
        Log::info("HTML generato: " . $html);
        return view('vault.note', [
            'title' => $title,
            'html'  => $html,
        ]);
    } 
}

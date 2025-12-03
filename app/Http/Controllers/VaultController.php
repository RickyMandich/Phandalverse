<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use App\Services\MarkdownPreprocessor;
use Illuminate\Support\Facades\Log;

class VaultController extends Controller
{
    public function show($note = 'README')
    {
        Log::info("Visualizzazione nota $note");
        $path = base_path("Vault/" . $note . ".md");

        if (!File::exists($path)) {
            Log::warning("Nota non trovata: $note");
            abort(104, "Nota non trovata");
        }

        $content = File::get($path);

        // Rimuove i blocchi master
        $content = MarkdownPreprocessor::filterMasterBlocks($content);

        // Converte Markdown → HTML con supporto wikilink/embed
        $html = MarkdownPreprocessor::toHtml($content);
        return "ciao";
        return view('vault.note', [
            'title' => $note,
            'html'  => $html,
        ]);
    }
}

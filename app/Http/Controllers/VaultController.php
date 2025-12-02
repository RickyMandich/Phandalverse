<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Services\MarkdownPreprocessor;

class VaultController extends Controller
{
    public function show($note = 'README')
    {
        $path = base_path("Vault/" . $note . ".md");

        if (!File::exists($path)) {
            abort(404, "Nota non trovata");
        }

        $content = File::get($path);

        // Rimuove i blocchi master
        $content = MarkdownPreprocessor::filterMasterBlocks($content);

        // Converte Markdown → HTML con CommonMark
        $html = Str::markdown($content);

        return view('vault.note', [
            'title' => $note,
            'html'  => $html,
        ]);
    }
}

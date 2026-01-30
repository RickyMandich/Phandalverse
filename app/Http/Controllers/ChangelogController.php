<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\Helpers\VaultHelper;
use App\Http\Controllers\VaultController;

class ChangelogController extends Controller
{
    /**
     * Display a listing of all versions.
     */
    public function index(Request $request)
    {
        $indexPath = base_path('Vault/.normalize/changelogs/index.json');

        if (!File::exists($indexPath)) {
            $versions = [];
        } else {
            $data = json_decode(File::get($indexPath), true);
            $versions = $data['versions'] ?? [];
        }

        // We need the tree for the sidebar
        $vaultController = new VaultController();
        $tree = $vaultController->buildFileTree();

        return view('vault.changelog.index', [
            'versions' => $versions,
            'tree' => $tree,
            'note' => 'changelog' // Used to highlight the sidebar if we add a link there later
        ]);
    }

    /**
     * Display the specified version.
     */
    public function show(Request $request, $version)
    {
        // Sanitize input: allow only alphanumeric and underscore (v_1_2_3 format)
        $versionFile = preg_replace('/[^a-zA-Z0-9_]/', '', $version);
        $changelogPath = base_path("Vault/.normalize/changelogs/{$versionFile}.json");

        if (!File::exists($changelogPath)) {
            abort(404, 'Versione non trovata');
        }

        $changelog = json_decode(File::get($changelogPath), true);

        // We need the tree for the sidebar
        $vaultController = new VaultController();
        $tree = $vaultController->buildFileTree();

        return view('vault.changelog.show', [
            'changelog' => $changelog,
            'tree' => $tree,
            'note' => 'changelog'
        ]);
    }
}

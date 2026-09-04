<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use App\Helpers\VaultHelper;
use App\Http\Controllers\VaultController;

class ChangelogController extends Controller
{
    protected function getAccessibleCampaigns()
    {
        return Auth::check() ? Auth::user()->accessibleCampaigns() : Campaign::orderBy('order')->get();
    }

    /**
     * Display a listing of all versions for a campaign.
     */
    public function index(Request $request, Campaign $campaign)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $folder = VaultHelper::resolveCampaignFolder($campaign);
        $indexPath = $campaign->changelogsPath('index.json');

        if (!File::exists($indexPath)) {
            $indexPath = base_path("Vault/{$folder}/.normalize/changelogs/index.json");
        }
        if (!File::exists($indexPath)) {
            $indexPath = base_path('Vault/.normalize/changelogs/index.json');
        }

        if (!File::exists($indexPath)) {
            \App\Services\CustomLogger::note('changelog', "Index file not found at: " . $indexPath);
            $versions = [];
        } else {
            $content = File::get($indexPath);
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                \App\Services\CustomLogger::note('changelog', "JSON decode error: " . json_last_error_msg());
                $versions = [];
            } else {
                $versions = $data['versions'] ?? [];
            }
        }

        $vaultController = new VaultController();
        $tree = $vaultController->buildFileTree(null, 'changelog', $campaign);

        return view('vault.changelog.index', [
            'versions' => $versions,
            'tree' => $tree,
            'note' => 'changelog',
            'campaign' => $campaign,
            'accessibleCampaigns' => $this->getAccessibleCampaigns(),
        ]);
    }

    /**
     * Display the specified version for a campaign.
     */
    public function show(Request $request, Campaign $campaign, $version)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $folder = VaultHelper::resolveCampaignFolder($campaign);
        $versionFile = preg_replace('/[^a-zA-Z0-9_]/', '', $version);
        $changelogPath = $campaign->changelogsPath("{$versionFile}.json");

        if (!File::exists($changelogPath)) {
            $changelogPath = base_path("Vault/{$folder}/.normalize/changelogs/{$versionFile}.json");
        }
        if (!File::exists($changelogPath)) {
            $changelogPath = base_path("Vault/.normalize/changelogs/{$versionFile}.json");
        }

        if (!File::exists($changelogPath)) {
            abort(404, 'Versione non trovata');
        }

        $changelog = json_decode(File::get($changelogPath), true);

        $vaultController = new VaultController();
        $tree = $vaultController->buildFileTree(null, 'changelog', $campaign);

        return view('vault.changelog.show', [
            'changelog' => $changelog,
            'tree' => $tree,
            'note' => 'changelog',
            'campaign' => $campaign,
            'accessibleCampaigns' => $this->getAccessibleCampaigns(),
        ]);
    }
}

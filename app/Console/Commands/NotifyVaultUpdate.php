<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class NotifyVaultUpdate extends Command
{
    /**
     * php artisan vault:notify-update
     */
    protected $signature = 'vault:notify-update {branch?}';

    protected $description = 'Invia la notifica Telegram di aggiornamento del Vault a tutti gli iscritti';

    public function handle(): int
    {
        $branch = $this->argument('branch');
        $folder = $branch ?: ''; // fallback vuoto = vecchio comportamento

        $indexPath = base_path('Vault/' . ($folder ? $folder . '/' : '') . '.normalize/changelogs/index.json');
        if (!File::exists($indexPath)) {
            $indexPath = base_path('Vault/.normalize/changelogs/index.json'); // fallback legacy
        }

        $version = env('APP_VERSION', '3.1.7'); // Fallback

        if (File::exists($indexPath)) {
            $content = File::get($indexPath);
            $data = json_decode($content, true);
            if (isset($data['versions'][0]['version'])) {
                $version = $data['versions'][0]['version'];
            }
        }

        $versionSlug = str_replace([' ', '.'], '_', strtolower(trim($version)));
        $encodedVersionSlug = rawurlencode($versionSlug);

        // In ambiente self-hosted con dominio reale e HTTPS, config('app.url')
        // è già affidabile: niente più bisogno del fallback su getSchemeAndHttpHost().
        $baseUrl = rtrim(config('app.url'), '/');
        $url = $baseUrl . "/vault/changelog/" . $encodedVersionSlug;
        $campaign = $branch ? Campaign::where('folder_name', $branch)->first() : null;
        $campaignLabel = $campaign?->display_name;

        $message = "🚀 <b>Nuovo aggiornamento disponibile!</b>\n";
        $message .= $campaignLabel
            ? "Il Vault della campagna <b>{$campaignLabel}</b> è stato aggiornato alla versione: <b>$version</b>\n\n"
            : "Il Vault è stato aggiornato alla versione: <b>$version</b>\n\n";
        $message .= "Clicca il pulsante sotto per leggere le novità direttamente qui!";

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '📄 Leggi Changelog',
                        'url' => $url,
                    ],
                ],
            ],
        ];

        if ($campaign) {
            TelegramService::broadcastCampaign($campaign, $message, true, $replyMarkup);
        } else {
            TelegramService::broadcast($message, true, $replyMarkup); // fallback legacy, nessuna campagna nota
        }

        $this->info("Notifica inviata per la versione $version");

        return self::SUCCESS;
    }
}
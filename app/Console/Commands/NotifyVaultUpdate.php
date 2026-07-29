<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class NotifyVaultUpdate extends Command
{
    /**
     * php artisan vault:notify-update
     */
    protected $signature = 'vault:notify-update';

    protected $description = 'Invia la notifica Telegram di aggiornamento del Vault a tutti gli iscritti';

    public function handle(): int
    {
        // Recuperiamo l'ultima versione dal file index.json del Vault
        $indexPath = base_path('Vault/.normalize/changelogs/index.json');
        if (!File::exists($indexPath)) {
            $indexPath = base_path('vault/.normalize/changelogs/index.json');
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

        $message = "🚀 <b>Nuovo aggiornamento disponibile!</b>\n";
        $message .= "Il Vault è stato aggiornato alla versione: <b>$version</b>\n\n";
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

        TelegramService::broadcast($message, true, $replyMarkup);

        $this->info("Notifica inviata per la versione $version");

        return self::SUCCESS;
    }
}
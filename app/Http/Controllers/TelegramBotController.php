<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\Request;
use App\Services\TelegramService;
use App\Models\TelegramSubscriber;
use App\Helpers\VaultHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class TelegramBotController extends Controller
{
    /**
     * Gestisce le richieste in entrata dal webhook di Telegram.
     */
    public function webhook(Request $request)
    {
        try {
            $update = $request->all();

            // Gestione Callback Queries (dai pulsanti inline)
            if (isset($update['callback_query'])) {
                return $this->handleCallback($update['callback_query']);
            }

            if (!isset($update['message'])) {
                return response('OK');
            }

            $message = $update['message'];
            $chatId = $message['chat']['id'];
            $threadId = $message['message_thread_id'] ?? null;
            $text = $message['text'] ?? '';

            // Determiniamo il nome da visualizzare/salvare
            $chat = $message['chat'];
            if ($chat['type'] === 'private') {
                $displayName = $message['from']['username'] ?? ($message['from']['first_name'] ?? 'User');
            } else {
                $displayName = $chat['title'] ?? 'Gruppo';
                if ($threadId) {
                    $topicName = $message['reply_to_message']['forum_topic_created']['name'] ?? null;
                    if ($topicName) {
                        $displayName .= " / " . $topicName;
                    } else {
                        $displayName .= " / Topic " . $threadId;
                    }
                }
            }

            \App\Services\CustomLogger::telegram("Webhook received: " . json_encode($update));

            // Gestione Comandi
            if (str_starts_with($text, '/')) {
                $parts = explode(' ', $text, 2);
                $fullCommand = strtolower($parts[0]);
                $params = trim($parts[1] ?? '');
                $command = explode('@', $fullCommand)[0];

                if ($command === '/start') {
                    if (!empty($params) && str_starts_with($params, 'view_')) {
                        $slug = str_replace('view_', '', $params);
                        $slug = str_replace(['___', '__'], '/', $slug);
                        return $this->handleView($chatId, $slug, $threadId);
                    }
                    $this->handleStart($chatId, $displayName, $threadId);
                } elseif ($command === '/subscribe') {
                    $this->handleSubscribe($chatId, $displayName, $threadId);
                } elseif ($command === '/unsubscribe') {
                    $this->handleUnsubscribe($chatId, $threadId);
                } elseif ($command === '/search') {
                    $this->handleSearch($chatId, $params, $threadId);
                } elseif ($command === '/view') {
                    $this->handleView($chatId, $params, $threadId);
                }
            }

            return response('OK');

        } catch (\Throwable $e) {
            \App\Services\CustomLogger::telegram("Errore nel Webhook Telegram: " . $e->getMessage(), 'error');
            TelegramService::notifyError($e, $request->fullUrl());
            return response('OK');
        }
    }

    /**
     * Gestisce i click sui pulsanti inline
     */
    protected function handleCallback($callbackQuery)
    {
        $chatId = $callbackQuery['message']['chat']['id'];
        $threadId = $callbackQuery['message']['message_thread_id'] ?? null;
        $data = $callbackQuery['data'];

        if (str_starts_with($data, 'view:')) {
            $payload = str_replace('view:', '', $data);
            $parts = explode('|', $payload, 2);
            $campaignFolder = count($parts) === 2 ? $parts[0] : null;
            $slug = count($parts) === 2 ? $parts[1] : $parts[0];
            $this->handleView($chatId, $slug, $threadId, $campaignFolder);
        } elseif (str_starts_with($data, 'sub:camp_')) {
            $campaignId = (int) str_replace('sub:camp_', '', $data);
            $campaign = Campaign::find($campaignId);
            if ($campaign) {
                $subscriber = TelegramSubscriber::where('chat_id', $chatId)
                    ->where('thread_id', $threadId)
                    ->where('campaign_id', $campaign->id)
                    ->first();

                if ($subscriber) {
                    TelegramService::sendToChat($chatId, "Questa chat/topic è già iscritta alle notifiche di <b>{$campaign->display_name}</b>! ✅", 'HTML', null, $threadId);
                } else {
                    TelegramSubscriber::create([
                        'campaign_id' => $campaign->id,
                        'chat_id' => $chatId,
                        'thread_id' => $threadId,
                        'username' => $callbackQuery['from']['username'] ?? ($callbackQuery['from']['first_name'] ?? 'User')
                    ]);
                    TelegramService::sendToChat($chatId, "Iscrizione completata per <b>{$campaign->display_name}</b>! 🔔 Riceverai una notifica ad ogni aggiornamento.", 'HTML', null, $threadId);
                }
            }
        } elseif (str_starts_with($data, 'unsub:camp_')) {
            $campaignId = (int) str_replace('unsub:camp_', '', $data);
            $campaign = Campaign::find($campaignId);
            TelegramSubscriber::where('chat_id', $chatId)
                ->where('thread_id', $threadId)
                ->where('campaign_id', $campaignId)
                ->delete();

            $name = $campaign ? $campaign->display_name : "Campagna #{$campaignId}";
            TelegramService::sendToChat($chatId, "Notifiche disattivate per <b>{$name}</b>. 📴", 'HTML', null, $threadId);
        } elseif ($data === 'unsub:all') {
            TelegramSubscriber::where('chat_id', $chatId)
                ->where('thread_id', $threadId)
                ->delete();
            TelegramService::sendToChat($chatId, "Tutte le notifiche sono state disattivate per questo topic. 📴", 'HTML', null, $threadId);
        }

        TelegramService::answerCallbackQuery($callbackQuery['id']);
        return response('OK');
    }

    protected function handleStart($chatId, $username, $threadId = null)
    {
        $message = "Ciao $username! Benvenuto nel bot di Phandalverse. 🌌\n\n";
        $message .= "Comandi disponibili:\n";
        $message .= "🚀 /subscribe - Attiva le notifiche per una o più campagne\n";
        $message .= "📴 /unsubscribe - Disattiva le notifiche\n";
        $message .= "🔍 /search <nome> - Cerca una nota nel vault\n\n";
        $message .= "Puoi anche semplicemente scrivere il nome di una nota per cercarla.";

        TelegramService::sendToChat($chatId, $message, 'HTML', null, $threadId);
    }

    protected function handleSubscribe($chatId, $username, $threadId = null)
    {
        $campaigns = Campaign::orderBy('order')->get();

        if ($campaigns->isEmpty()) {
            TelegramService::sendToChat($chatId, "Nessuna campagna attiva disponibile per l'iscrizione al momento.", 'HTML', null, $threadId);
            return;
        }

        if ($campaigns->count() === 1) {
            $campaign = $campaigns->first();
            $subscriber = TelegramSubscriber::where('chat_id', $chatId)
                ->where('thread_id', $threadId)
                ->where('campaign_id', $campaign->id)
                ->first();

            if ($subscriber) {
                TelegramService::sendToChat($chatId, "Questa chat/topic è già iscritta alle notifiche di <b>{$campaign->display_name}</b>! ✅", 'HTML', null, $threadId);
            } else {
                TelegramSubscriber::create([
                    'campaign_id' => $campaign->id,
                    'chat_id' => $chatId,
                    'thread_id' => $threadId,
                    'username' => $username
                ]);
                TelegramService::sendToChat($chatId, "Iscrizione completata per <b>{$campaign->display_name}</b>! 🔔", 'HTML', null, $threadId);
            }
            return;
        }

        // Più campagne: mostra bottoni inline per scegliere
        $keyboard = [];
        foreach ($campaigns as $camp) {
            $isSubbed = TelegramSubscriber::where('chat_id', $chatId)
                ->where('thread_id', $threadId)
                ->where('campaign_id', $camp->id)
                ->exists();

            $statusIcon = $isSubbed ? "✅ " : "➕ ";
            $keyboard[] = [
                [
                    'text' => $statusIcon . $camp->display_name,
                    'callback_data' => "sub:camp_{$camp->id}"
                ]
            ];
        }

        $replyMarkup = ['inline_keyboard' => $keyboard];
        TelegramService::sendToChat($chatId, "Scegli a quale campagna desideri iscriverti per ricevere gli aggiornamenti:", 'HTML', $replyMarkup, $threadId);
    }

    protected function handleUnsubscribe($chatId, $threadId = null)
    {
        $subs = TelegramSubscriber::where('chat_id', $chatId)
            ->where('thread_id', $threadId)
            ->with('campaign')
            ->get();

        if ($subs->isEmpty()) {
            TelegramService::sendToChat($chatId, "Non ci sono iscrizioni attive per questo topic.", 'HTML', null, $threadId);
            return;
        }

        if ($subs->count() === 1) {
            $sub = $subs->first();
            $name = $sub->campaign ? $sub->campaign->display_name : 'Campagna';
            $sub->delete();
            TelegramService::sendToChat($chatId, "Notifiche disattivate per <b>{$name}</b>. 📴", 'HTML', null, $threadId);
            return;
        }

        $keyboard = [];
        foreach ($subs as $sub) {
            $name = $sub->campaign ? $sub->campaign->display_name : "Campagna #{$sub->campaign_id}";
            $keyboard[] = [
                [
                    'text' => "📴 Disiscriviti da " . $name,
                    'callback_data' => "unsub:camp_{$sub->campaign_id}"
                ]
            ];
        }
        $keyboard[] = [
            [
                'text' => "🚫 Disiscriviti da TUTTE",
                'callback_data' => "unsub:all"
            ]
        ];

        $replyMarkup = ['inline_keyboard' => $keyboard];
        TelegramService::sendToChat($chatId, "Scegli da quale campagna vuoi disattivare le notifiche:", 'HTML', $replyMarkup, $threadId);
    }

    protected function handleSearch($chatId, $query, $threadId = null)
    {
        if (empty($query)) {
            TelegramService::sendToChat($chatId, "Per favore, specifica cosa vuoi cercare. Esempio: /search Than", 'HTML', null, $threadId);
            return;
        }

        $campaigns = Campaign::orderBy('order')->get();
        if ($campaigns->isEmpty()) {
            $campaigns = [null];
        }

        $allResults = [];
        foreach ($campaigns as $camp) {
            $results = VaultHelper::searchNotes($query, "telegram_search", $camp);
            foreach ($results as $r) {
                $r['campaign'] = $camp;
                $allResults[] = $r;
            }
        }

        if (empty($allResults)) {
            TelegramService::sendToChat($chatId, "Nessun risultato trovato per: " . $query, 'HTML', null, $threadId);
            return;
        }

        TelegramService::sendToChat($chatId, "🔍 Risultati della ricerca per '<b>$query</b>':", 'HTML', null, $threadId);

        $limitedResults = array_slice($allResults, 0, 4);

        foreach ($limitedResults as $result) {
            $camp = $result['campaign'];
            $folder = $camp ? $camp->folder_name : 'newCampaign';
            $campName = $camp ? $camp->display_name : 'Vault';

            $slug = str_replace('.md', '', VaultController::pathToCamelCase($result['path']));
            $encodedSlug = implode('/', array_map('rawurlencode', explode('/', $slug)));

            $baseUrl = config('app.url');
            if ($baseUrl === 'http://localhost' || str_contains($baseUrl, 'localhost')) {
                $baseUrl = request()->getSchemeAndHttpHost();
            }

            $url = rtrim($baseUrl, '/') . "/vault/" . $folder . "/" . $encodedSlug;

            $message = "📑 <b>" . htmlspecialchars($result['original']) . "</b> (<i>" . htmlspecialchars($campName) . "</i>)\n";
            $message .= "<code>/view $slug</code>";

            $isGroup = str_starts_with((string) $chatId, '-');

            $replyMarkup = [
                'inline_keyboard' => [
                    [
                        $isGroup ? [
                            'text' => '🌍 Apri nel Sito',
                            'url' => $url
                        ] : [
                            'text' => '🌍 Apri Sito (Mini App)',
                            'web_app' => ['url' => $url]
                        ],
                        [
                            'text' => '📖 Leggi qui',
                            'callback_data' => 'view:' . $folder . '|' . $slug
                        ]
                    ]
                ]
            ];

            TelegramService::sendToChat($chatId, $message, 'HTML', $replyMarkup, $threadId);
        }

        if (count($allResults) > 4) {
            TelegramService::sendToChat($chatId, "...e altri " . (count($allResults) - 4) . " risultati.", 'HTML', null, $threadId);
        }
    }

    protected function handleView($chatId, $slug, $threadId = null, ?string $campaignFolder = null)
    {
        if (empty($slug)) {
            TelegramService::sendToChat($chatId, "Specifica la nota da leggere. Esempio: /view personaggi/giocanti/than", 'HTML', null, $threadId);
            return;
        }

        $campaign = $campaignFolder ? Campaign::where('folder_name', $campaignFolder)->first() : Campaign::orderBy('order')->first();
        $folder = VaultHelper::resolveCampaignFolder($campaign);

        $path = \App\Services\MarkdownPreprocessor::findNotePath($slug, $campaign);
        $fullPath = base_path("Vault/{$folder}/" . $path . ".md");
        if (!File::exists($fullPath)) {
            $fullPath = base_path("Vault/" . $path . ".md");
        }

        if (!File::exists($fullPath)) {
            TelegramService::sendToChat($chatId, "Nota non trovata: $slug", 'HTML', null, $threadId);
            return;
        }

        $content = File::get($fullPath);

        // Pulizia Blocchi DM e Frontmatter
        $content = \App\Services\MarkdownPreprocessor::filterMasterBlocks($content, $campaign);
        $content = \App\Services\MarkdownPreprocessor::stripDmMarker($content);
        $content = preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $content);

        // Rimozione tabelle
        $content = preg_replace('/<table[^>]*>.*?<\/table>/is', "\n<i>[Tabella rimossa - visualizzala sul sito]</i>\n", $content);
        $content = preg_replace('/(\n|^)\|.+\|\r?\n\|[-:| ]+\|\r?\n(\|.+\|(\r?\n|$))+/m', "\n<i>[Tabella rimossa - visualizzala sul sito]</i>\n", $content);

        // Escape HTML globale
        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        // Embed ![[NomeNota]]
        $content = preg_replace_callback('/!\[\[([^\]|#]+)(?:#[^\]|]*)?(?:\|([^\]]+))?\]\]/', function ($m) use ($campaign) {
            $alias = !empty($m[2]) ? html_entity_decode($m[2]) : null;
            $fileName = trim($m[1]);
            $hasExt = preg_match('/\.(png|jpg|jpeg|gif|webp|svg|avif)$/i', $fileName);
            $lookupPath = $hasExt ? $fileName : $fileName . ".md";
            $noteName = $alias ?: VaultHelper::getOriginalName($lookupPath, "telegram_view", $campaign);
            return "\n📎 <b>" . htmlspecialchars($noteName) . "</b> (Embed)\n";
        }, $content);

        // Wikilink [[NomeNota]]
        $content = preg_replace_callback('/\[\[([^\]|#]+)(?:#[^\]|]*)?(?:\|([^\]]+))?\]\]/', function ($m) use ($campaign) {
            $alias = !empty($m[2]) ? html_entity_decode($m[2]) : null;
            $noteName = $alias ?: VaultHelper::getOriginalName(trim($m[1]) . ".md", "telegram_view", $campaign);
            return "<b>" . htmlspecialchars($noteName) . "</b>";
        }, $content);

        // Formattazione
        $content = preg_replace('/\*\*(.+?)\*\*/', '<b>$1</b>', $content);
        $content = preg_replace('/\*(.+?)\*/', '<i>$1</i>', $content);
        $content = preg_replace('/^#+\s+(.+)$/m', "\n<b>$1</b>", $content);
        $content = preg_replace('/^\s*[\-\*]\s+(.+)$/m', "• $1", $content);

        $maxLen = 3800;
        $suffix = "";
        if (strlen($content) > $maxLen) {
            $content = substr($content, 0, $maxLen);
            $suffix = "\n\n... (contenuto troncato, leggi sul sito)";
        }

        $title = VaultHelper::getOriginalName($path . ".md", "telegram_view", $campaign);

        $encodedSlug = implode('/', array_map('rawurlencode', explode('/', $slug)));
        $baseUrl = config('app.url');
        if ($baseUrl === 'http://localhost' || str_contains($baseUrl, 'localhost')) {
            $baseUrl = request()->getSchemeAndHttpHost();
        }
        $url = rtrim($baseUrl, '/') . "/vault/" . $folder . "/" . $encodedSlug;

        $message = "📖 <b>" . htmlspecialchars($title) . "</b>\n\n";
        $message .= trim($content) . $suffix;

        $isGroup = str_starts_with((string) $chatId, '-');

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    $isGroup ? [
                        'text' => '🌍 Apri nel Sito',
                        'url' => $url
                    ] : [
                        'text' => '🌍 Apri nel Sito',
                        'web_app' => ['url' => $url]
                    ]
                ]
            ]
        ];

        TelegramService::sendToChat($chatId, $message, 'HTML', $replyMarkup, $threadId);
    }

    /**
     * Invia la notifica di aggiornamento agli iscritti della specifica campagna.
     */
    public function notifyUpdate(Request $request)
    {
        $token = trim($request->query('token', ''), '"\'');
        if ($token !== env('JOB_TOKEN')) {
            abort(403);
        }

        $campaignFolder = $request->query('campaign');
        $campaign = null;
        if ($campaignFolder) {
            $campaign = Campaign::where('folder_name', $campaignFolder)->first();
        }
        if (!$campaign) {
            $campaign = Campaign::orderBy('order')->first();
        }

        if (!$campaign) {
            return response()->json(['error' => 'Nessuna campagna trovata'], 404);
        }

        $folder = $campaign->folder_name;

        // Recuperiamo l'ultima versione dal file index.json del Vault della campagna
        $indexPath = $campaign->changelogsPath('index.json');
        if (!File::exists($indexPath)) {
            $indexPath = base_path("Vault/{$folder}/.normalize/changelogs/index.json");
        }
        if (!File::exists($indexPath)) {
            $indexPath = base_path('Vault/.normalize/changelogs/index.json');
        }

        $version = env('APP_VERSION', '1.0.0');

        if (File::exists($indexPath)) {
            $content = File::get($indexPath);
            $data = json_decode($content, true);
            if (isset($data['versions'][0]['version'])) {
                $version = $data['versions'][0]['version'];
            }
        }

        $versionSlug = str_replace([' ', '.'], '_', strtolower(trim($version)));
        $encodedVersionSlug = rawurlencode($versionSlug);

        $baseUrl = config('app.url');
        if ($baseUrl === 'http://localhost' || str_contains($baseUrl, 'localhost') || !str_starts_with($baseUrl, 'https')) {
            $baseUrl = $request->getSchemeAndHttpHost();
            if (!str_contains($baseUrl, 'localhost')) {
                $baseUrl = str_replace('http://', 'https://', $baseUrl);
            }
        }
        $url = rtrim($baseUrl, '/') . "/vault/" . $folder . "/changelog/" . $encodedVersionSlug;

        $message = "🚀 <b>Nuovo aggiornamento per {$campaign->display_name}!</b>\n";
        $message .= "Il Vault è stato aggiornato alla versione: <b>{$version}</b>\n\n";
        $message .= "Clicca il pulsante sotto per leggere le novità!";

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '📄 Leggi Changelog',
                        'url' => $url
                    ]
                ]
            ]
        ];

        TelegramService::broadcastCampaign($campaign, $message, true, $replyMarkup);

        return response()->json([
            'status' => 'success',
            'campaign' => $campaign->folder_name,
            'version' => $version,
            'notified' => true
        ]);
    }
}

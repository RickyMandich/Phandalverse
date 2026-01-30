<?php

namespace App\Http\Controllers;

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
                    // Proviamo a recuperare il nome del topic se il messaggio è una risposta al messaggio di creazione
                    $topicName = $message['reply_to_message']['forum_topic_created']['name'] ?? null;

                    if ($topicName) {
                        $displayName .= " / " . $topicName;
                    } else {
                        // Fallback: se non troviamo il nome, usiamo l'ID del topic
                        $displayName .= " / Topic " . $threadId;
                    }
                }
            }

            \App\Services\CustomLogger::telegram("Webhook received: " . json_encode($update));

            if (str_starts_with($text, '/start')) {
                // Gestione deep linking: /start view_slug
                if (str_contains($text, ' ')) {
                    $param = explode(' ', $text)[1];
                    if (str_starts_with($param, 'view_')) {
                        $slug = str_replace('view_', '', $param);
                        // Se lo slug è codificato con underscore al posto di slash
                        $slug = str_replace(['___', '__'], '/', $slug);
                        return $this->handleView($chatId, $slug, $threadId);
                    }
                }
                $this->handleStart($chatId, $displayName, $threadId);
            } elseif (str_starts_with($text, '/subscribe')) {
                $this->handleSubscribe($chatId, $displayName, $threadId);
            } elseif (str_starts_with($text, '/unsubscribe')) {
                $this->handleUnsubscribe($chatId, $threadId);
            } elseif (str_starts_with($text, '/search')) {
                $query = trim(str_replace('/search', '', $text));
                $this->handleSearch($chatId, $query, $threadId);
            } elseif (str_starts_with($text, '/view')) {
                $slug = trim(str_replace('/view', '', $text));
                $this->handleView($chatId, $slug, $threadId);
            } else {
                // Se non è un comando, lo trattiamo come una ricerca
                $this->handleSearch($chatId, $text, $threadId);
            }

            return response('OK');

        } catch (\Throwable $e) {
            // In caso di errore (es. DB non pronto), logghiamo e notifichiamo l'admin una volta.
            // Ritorniamo comunque OK a Telegram per evitare che continui a riprovare all'infinito (retry).
            \App\Services\CustomLogger::telegram("Errore nel Webhook Telegram: " . $e->getMessage(), 'error');

            // Notifica manuale per evitare che si perda il primo errore
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
            $slug = str_replace('view:', '', $data);
            $this->handleView($chatId, $slug, $threadId);
        }

        TelegramService::answerCallbackQuery($callbackQuery['id']);
        return response('OK');
    }

    protected function handleStart($chatId, $username, $threadId = null)
    {
        $message = "Ciao $username! Benvenuto nel bot di Phandalverse. 🌌\n\n";
        $message .= "Comandi disponibili:\n";
        $message .= "🚀 /subscribe - Attiva le notifiche per i cambiamenti nel server\n";
        $message .= "📴 /unsubscribe - Disattiva le notifiche\n";
        $message .= "🔍 /search <nome> - Cerca una nota nel vault\n\n";
        $message .= "Puoi anche semplicemente scrivere il nome di una nota per cercarla.";

        TelegramService::sendToChat($chatId, $message, 'HTML', null, $threadId);
    }

    protected function handleSubscribe($chatId, $username, $threadId = null)
    {
        // Cerchiamo se l'iscrizione esiste già per la combinazione Chat e Topic
        $subscriber = TelegramSubscriber::where('chat_id', $chatId)
            ->where('thread_id', $threadId)
            ->first();

        if ($subscriber) {
            TelegramService::sendToChat($chatId, "Questa chat/topic è già iscritta alle notifiche! ✅", 'HTML', null, $threadId);
        } else {
            TelegramSubscriber::create([
                'chat_id' => $chatId,
                'thread_id' => $threadId,
                'username' => $username
            ]);
            TelegramService::sendToChat($chatId, "Iscrizione completata per questo topic! Riceverete una notifica ogni volta che ci saranno aggiornamenti sul server. 🔔", 'HTML', null, $threadId);
        }
    }

    protected function handleUnsubscribe($chatId, $threadId = null)
    {
        $subscriber = TelegramSubscriber::where('chat_id', $chatId)
            ->where('thread_id', $threadId)
            ->first();

        if ($subscriber) {
            $subscriber->delete();
            TelegramService::sendToChat($chatId, "Notifiche disattivate per questo topic. 📴", 'HTML', null, $threadId);
        } else {
            TelegramService::sendToChat($chatId, "Non ci sono iscrizioni attive per questo topic.", 'HTML', null, $threadId);
        }
    }

    protected function handleSearch($chatId, $query, $threadId = null)
    {
        if (empty($query)) {
            TelegramService::sendToChat($chatId, "Per favore, specifica cosa vuoi cercare. Esempio: /search Than", 'HTML', null, $threadId);
            return;
        }

        $results = VaultHelper::searchNotes($query, "telegram_search");

        if (empty($results)) {
            TelegramService::sendToChat($chatId, "Nessun risultato trovato per: " . $query, 'HTML', null, $threadId);
            return;
        }

        TelegramService::sendToChat($chatId, "🔍 Risultati della ricerca per '<b>$query</b>':", 'HTML', null, $threadId);

        // Limitiamo a 3 risultati per non intasare la chat con messaggi multipli
        $limitedResults = array_slice($results, 0, 3);

        foreach ($limitedResults as $result) {
            $slug = str_replace('.md', '', VaultController::pathToCamelCase($result['path']));

            // Codifichiamo lo slug per l'URL (per gestire spazi e caratteri speciali)
            $encodedSlug = implode('/', array_map('rawurlencode', explode('/', $slug)));

            // Usiamo l'Host corrente della richiesta se APP_URL è localhost
            $baseUrl = config('app.url');
            if ($baseUrl === 'http://localhost' || str_contains($baseUrl, 'localhost')) {
                $baseUrl = request()->getSchemeAndHttpHost();
            }

            $url = rtrim($baseUrl, '/') . "/vault/" . $encodedSlug;

            $message = "📑 <b>" . htmlspecialchars($result['original']) . "</b>\n";
            $message .= "<code>/view $slug</code>";

            $replyMarkup = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => '🌍 Apri Sito (Mini App)',
                            'web_app' => ['url' => $url]
                        ],
                        [
                            'text' => '📖 Leggi qui',
                            'callback_data' => 'view:' . $slug
                        ]
                    ]
                ]
            ];

            TelegramService::sendToChat($chatId, $message, 'HTML', $replyMarkup, $threadId);
        }

        if (count($results) > 3) {
            TelegramService::sendToChat($chatId, "...e altri " . (count($results) - 3) . " risultati.", 'HTML', null, $threadId);
        }
    }

    protected function handleView($chatId, $slug, $threadId = null)
    {
        if (empty($slug)) {
            TelegramService::sendToChat($chatId, "Specifica la nota da leggere. Esempio: /view personaggi/giocanti/than-warlock-tiefling-30", 'HTML', null, $threadId);
            return;
        }

        $path = \App\Services\MarkdownPreprocessor::findNotePath($slug);
        $fullPath = base_path("Vault/" . $path . ".md");

        if (!\Illuminate\Support\Facades\File::exists($fullPath)) {
            TelegramService::sendToChat($chatId, "Nota non trovata: $slug", 'HTML', null, $threadId);
            return;
        }

        $content = \Illuminate\Support\Facades\File::get($fullPath);

        // 1. Pulizia Blocchi DM e Frontmatter
        $content = \App\Services\MarkdownPreprocessor::filterMasterBlocks($content);
        $content = \App\Services\MarkdownPreprocessor::stripDmMarker($content);
        $content = preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $content);

        // 2. RIMOZIONE DRASTICA TABELLE (Sia HTML che Markdown)
        // Rimuove tag <table>...</table>
        $content = preg_replace('/<table[^>]*>.*?<\/table>/is', "\n<i>[Tabella rimossa - visualizzala sul sito]</i>\n", $content);
        // Rimuove tabelle Markdown classiche
        $content = preg_replace('/(\n|^)\|.+\|\r?\n\|[-:| ]+\|\r?\n(\|.+\|(\r?\n|$))+/m', "\n<i>[Tabella rimossa - visualizzala sul sito]</i>\n", $content);

        // 3. ESCAPE HTML GLOBALE
        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        // 4. ELABORAZIONE WIKILINK ED EMBED
        // Embed ![[NomeNota]]
        $content = preg_replace_callback('/!\[\[([^\]|#]+)(?:#[^\]|]*)?(?:\|([^\]]+))?\]\]/', function ($m) {
            $alias = !empty($m[2]) ? html_entity_decode($m[2]) : null;
            $fileName = trim($m[1]);
            // Se finisce con estensione immagine, cerchiamo il nome pulito
            $hasExt = preg_match('/\.(png|jpg|jpeg|gif|webp|svg|avif)$/i', $fileName);
            $lookupPath = $hasExt ? $fileName : $fileName . ".md";
            $noteName = $alias ?: VaultHelper::getOriginalName($lookupPath, "telegram_view");
            return "\n📎 <b>" . htmlspecialchars($noteName) . "</b> (Embed)\n";
        }, $content);

        // Wikilink [[NomeNota]]
        $content = preg_replace_callback('/\[\[([^\]|#]+)(?:#[^\]|]*)?(?:\|([^\]]+))?\]\]/', function ($m) {
            $alias = !empty($m[2]) ? html_entity_decode($m[2]) : null;
            $noteName = $alias ?: VaultHelper::getOriginalName(trim($m[1]) . ".md", "telegram_view");
            return "<b>" . htmlspecialchars($noteName) . "</b>";
        }, $content);

        // 5. FORMATTAZIONE MARKDOWN SU TESTO ESCAPATO
        $content = preg_replace('/\*\*(.+?)\*\*/', '<b>$1</b>', $content);
        $content = preg_replace('/\*(.+?)\*/', '<i>$1</i>', $content);
        $content = preg_replace('/^#+\s+(.+)$/m', "\n<b>$1</b>", $content);
        $content = preg_replace('/^\s*[\-\*]\s+(.+)$/m', "• $1", $content);

        // 6. LIMITAZIONE LUNGHEZZA
        $maxLen = 3800;
        $suffix = "";
        if (strlen($content) > $maxLen) {
            $content = substr($content, 0, $maxLen);
            $suffix = "\n\n... (contenuto troncato, leggi sul sito)";
        }

        $title = VaultHelper::getOriginalName($path . ".md", "telegram_view");

        // Prepariamo l'URL per il pulsante
        $encodedSlug = implode('/', array_map('rawurlencode', explode('/', $slug)));
        $baseUrl = config('app.url');
        if ($baseUrl === 'http://localhost' || str_contains($baseUrl, 'localhost')) {
            $baseUrl = request()->getSchemeAndHttpHost();
        }
        $url = rtrim($baseUrl, '/') . "/vault/" . $encodedSlug;

        $message = "📖 <b>" . htmlspecialchars($title) . "</b>\n\n";
        $message .= trim($content) . $suffix;

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '🌍 Apri nel Sito',
                        'web_app' => ['url' => $url]
                    ]
                ]
            ]
        ];

        TelegramService::sendToChat($chatId, $message, 'HTML', $replyMarkup, $threadId);
    }

    /**
     * Invia la notifica di aggiornamento a tutti gli iscritti.
     */
    public function notifyUpdate(Request $request)
    {
        if ($request->query('token') !== env('JOB_TOKEN')) {
            abort(403);
        }

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
        // Codifichiamo per sicurezza (anche se gli underscore sono ok)
        $encodedVersionSlug = rawurlencode($versionSlug);

        // --- LOGICA URL SICURA PER MINI APP ---
        $baseUrl = config('app.url');
        if ($baseUrl === 'http://localhost' || str_contains($baseUrl, 'localhost') || !str_starts_with($baseUrl, 'https')) {
            $baseUrl = $request->getSchemeAndHttpHost();
            // Forza HTTPS se siamo in produzione (necessario per Mini App Telegram)
            if (!str_contains($baseUrl, 'localhost')) {
                $baseUrl = str_replace('http://', 'https://', $baseUrl);
            }
        }
        $url = rtrim($baseUrl, '/') . "/vault/changelog/" . $encodedVersionSlug;

        $message = "🚀 <b>Nuovo aggiornamento disponibile!</b>\n";
        $message .= "Il Vault è stato aggiornato alla versione: <b>$version</b>\n\n";
        $message .= "Clicca il pulsante sotto per leggere le novità direttamente qui!";

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '📄 Leggi Changelog (Mini App)',
                        'web_app' => ['url' => $url]
                    ]
                ]
            ]
        ];

        TelegramService::broadcast($message, true, $replyMarkup);

        return response()->json([
            'status' => 'success',
            'version' => $version,
            'notified' => true
        ]);
    }
}

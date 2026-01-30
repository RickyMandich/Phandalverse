<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TelegramService;
use App\Models\TelegramSubscriber;
use App\Helpers\VaultHelper;
use Illuminate\Support\Facades\Log;

class TelegramBotController extends Controller
{
    /**
     * Gestisce le richieste in entrata dal webhook di Telegram.
     */
    public function webhook(Request $request)
    {
        try {
            $update = $request->all();

            if (!isset($update['message'])) {
                return response('OK');
            }

            $message = $update['message'];
            $chatId = $message['chat']['id'];
            $text = $message['text'] ?? '';
            $username = $message['from']['username'] ?? ($message['from']['first_name'] ?? 'User');

            Log::info("Telegram Webhook receive: " . json_encode($update));

            if (str_starts_with($text, '/start')) {
                $this->handleStart($chatId, $username);
            } elseif (str_starts_with($text, '/subscribe')) {
                $this->handleSubscribe($chatId, $username);
            } elseif (str_starts_with($text, '/unsubscribe')) {
                $this->handleUnsubscribe($chatId);
            } elseif (str_starts_with($text, '/search')) {
                $query = trim(str_replace('/search', '', $text));
                $this->handleSearch($chatId, $query);
            } elseif (str_starts_with($text, '/view')) {
                $slug = trim(str_replace('/view', '', $text));
                $this->handleView($chatId, $slug);
            } else {
                // Se non è un comando, lo trattiamo come una ricerca
                $this->handleSearch($chatId, $text);
            }

            return response('OK');

        } catch (\Throwable $e) {
            // In caso di errore (es. DB non pronto), logghiamo e notifichiamo l'admin una volta.
            // Ritorniamo comunque OK a Telegram per evitare che continui a riprovare all'infinito (retry).
            Log::error("Errore nel Webhook Telegram: " . $e->getMessage());

            // Notifica manuale per evitare che si perda il primo errore
            TelegramService::notifyError($e, $request->fullUrl());

            return response('OK');
        }
    }

    protected function handleStart($chatId, $username)
    {
        $message = "Ciao $username! Benvenuto nel bot di Phandalverse. 🌌\n\n";
        $message .= "Comandi disponibili:\n";
        $message .= "🚀 /subscribe - Attiva le notifiche per i cambiamenti nel server\n";
        $message .= "📴 /unsubscribe - Disattiva le notifiche\n";
        $message .= "🔍 /search <nome> - Cerca una nota nel vault\n\n";
        $message .= "Puoi anche semplicemente scrivere il nome di una nota per cercarla.";

        TelegramService::sendToChat($chatId, $message);
    }

    protected function handleSubscribe($chatId, $username)
    {
        $subscriber = TelegramSubscriber::where('chat_id', $chatId)->first();

        if ($subscriber) {
            TelegramService::sendToChat($chatId, "Sei già iscritto alle notifiche! ✅");
        } else {
            TelegramSubscriber::create([
                'chat_id' => $chatId,
                'username' => $username
            ]);
            TelegramService::sendToChat($chatId, "Iscrizione completata! Riceverai una notifica ogni volta che ci saranno aggiornamenti sul server. 🔔");
        }
    }

    protected function handleUnsubscribe($chatId)
    {
        $subscriber = TelegramSubscriber::where('chat_id', $chatId)->first();

        if ($subscriber) {
            $subscriber->delete();
            TelegramService::sendToChat($chatId, "Ti sei disiscritto dalle notifiche. 📴");
        } else {
            TelegramService::sendToChat($chatId, "Non sei iscritto alle notifiche.");
        }
    }

    protected function handleSearch($chatId, $query)
    {
        if (empty($query)) {
            TelegramService::sendToChat($chatId, "Per favore, specifica cosa vuoi cercare. Esempio: /search Than");
            return;
        }

        $results = VaultHelper::searchNotes($query, "telegram_search");

        if (empty($results)) {
            TelegramService::sendToChat($chatId, "Nessun risultato trovato per: " . $query);
            return;
        }

        $message = "🔍 Risultati della ricerca per '$query':\n\n";

        // Limitiamo a 5 risultati per non intasare la chat
        $limitedResults = array_slice($results, 0, 5);

        foreach ($limitedResults as $result) {
            $slug = str_replace('.md', '', VaultController::pathToCamelCase($result['path']));
            $url = config('app.url') . "/vault/" . $slug;
            $message .= "📑 <b>" . $result['original'] . "</b>\n";
            $message .= "🔗 <a href=\"$url\">Apri sul Sito</a>\n";
            $message .= "📖 /view $slug\n\n";
        }

        if (count($results) > 5) {
            $message .= "...e altri " . (count($results) - 5) . " risultati.";
        }

        TelegramService::sendToChat($chatId, $message);
    }

    protected function handleView($chatId, $slug)
    {
        if (empty($slug)) {
            TelegramService::sendToChat($chatId, "Specifica la nota da leggere. Esempio: /view personaggi/giocanti/than-warlock-tiefling-30");
            return;
        }

        $path = \App\Services\MarkdownPreprocessor::findNotePath($slug);
        $fullPath = base_path("Vault/" . $path . ".md");

        if (!\Illuminate\Support\Facades\File::exists($fullPath)) {
            TelegramService::sendToChat($chatId, "Nota non trovata: $slug");
            return;
        }

        $content = \Illuminate\Support\Facades\File::get($fullPath);

        // Rimuoviamo i marker #dm e i blocchi master per sicurezza (se non vogliamo che siano pubblici)
        // Ma qui il bot è usato da chi ha il link, decidiamo se filtrare.
        // Se è per uso personale/master, mostriamo tutto. Se è per i giocatori, filtriamo.
        // Dato che non c'è auth sul bot (chiunque può avviarlo se sa il nome), meglio filtrare.

        $content = \App\Services\MarkdownPreprocessor::filterMasterBlocks($content);
        $content = \App\Services\MarkdownPreprocessor::stripDmMarker($content);

        // Pulizia minima per Telegram (rimuoviamo wikilinks complessi e limitiamo lunghezza)
        $content = preg_replace('/\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/', '$2', $content); // Sostituisce [[Link|Alias]] con Alias
        $content = preg_replace('/\[\[([^\]]+)\]\]/', '$1', $content); // Sostituisce [[Link]] con Link

        $maxLen = 3500;
        $suffix = "";
        if (strlen($content) > $maxLen) {
            $content = substr($content, 0, $maxLen);
            $suffix = "\n\n... (contenuto troncato, leggi sul sito per la versione completa)";
        }

        $title = VaultHelper::getOriginalName($path . ".md", "telegram_view");
        $message = "📖 <b>$title</b>\n\n";
        $message .= htmlspecialchars($content) . $suffix;

        TelegramService::sendToChat($chatId, $message);
    }
}

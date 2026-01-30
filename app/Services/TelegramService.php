<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected static function getBotToken(): ?string
    {
        return env('TELEGRAM_BOT_TOKEN');
    }

    protected static function getAdminChatId(): ?string
    {
        return env('TELEGRAM_ADMIN_CHAT_ID');
    }

    protected static function getApiUrl(): string
    {
        return 'https://api.telegram.org/bot' . self::getBotToken();
    }

    /**
     * Invia un messaggio a un chat ID specifico
     */
    public static function sendToChat(string $chatId, string $message, $parseMode = 'HTML'): bool
    {
        try {
            $token = self::getBotToken();

            if (!$token) {
                Log::error('TelegramService: TELEGRAM_BOT_TOKEN non configurato');
                return false;
            }

            // Gestione legacy per parametro booleano
            if ($parseMode === true)
                $parseMode = 'HTML';
            if ($parseMode === false)
                $parseMode = null;

            $payload = [
                'chat_id' => $chatId,
                'text' => $message,
                'disable_web_page_preview' => false,
            ];

            if ($parseMode) {
                $payload['parse_mode'] = $parseMode;
            }

            $response = Http::post(self::getApiUrl() . '/sendMessage', $payload);

            if ($response->successful()) {
                return true;
            }

            Log::error('Telegram API error (ChatID: ' . $chatId . '): ' . $response->body());
            return false;

        } catch (\Exception $e) {
            Log::error('TelegramService errore: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Invia un messaggio al chat admin configurato nel .env
     */
    public static function send(string $message, bool $parseHtml = true): bool
    {
        $chatId = self::getAdminChatId();
        if (!$chatId) {
            Log::error('TelegramService: TELEGRAM_ADMIN_CHAT_ID non configurato');
            return false;
        }
        return self::sendToChat($chatId, $message, $parseHtml);
    }

    /**
     * Invia un messaggio a tutti gli iscritti
     */
    public static function broadcast(string $message, bool $parseHtml = true): void
    {
        $subscribers = \App\Models\TelegramSubscriber::all();
        foreach ($subscribers as $subscriber) {
            self::sendToChat($subscriber->chat_id, $message, $parseHtml);
        }
    }

    /**
     * Invia notifica di errore sistema
     */
    public static function notifyError(\Throwable $exception, ?string $url = null): void
    {
        $message = "🚨 <b>Errore Sistema</b>\n\n";
        $message .= "<b>Tipo:</b> " . class_basename($exception) . "\n";
        $message .= "<b>Messaggio:</b> " . htmlspecialchars(substr($exception->getMessage(), 0, 500)) . "\n";
        $message .= "<b>File:</b> " . basename($exception->getFile()) . ":" . $exception->getLine() . "\n";

        if ($url) {
            $message .= "<b>URL:</b> " . htmlspecialchars($url) . "\n";
        }

        $message .= "<b>Data:</b> " . now()->format('d/m/Y H:i:s');

        self::send($message);
    }

    /**
     * Invia notifica di nuovo report
     */
    public static function notifyNewReport(string $title, string $description, ?string $from = null): void
    {
        $message = "📢 <b>Nuova Segnalazione</b>\n\n";
        $message .= "<b>Titolo:</b> " . htmlspecialchars($title) . "\n";
        $message .= "<b>Descrizione:</b> " . htmlspecialchars(substr($description, 0, 300)) . "\n";

        if ($from) {
            $message .= "<b>Da:</b> " . htmlspecialchars($from) . "\n";
        }

        $message .= "<b>Data:</b> " . now()->format('d/m/Y H:i:s');

        self::send($message);
    }

    /**
     * Invia messaggio generico di notifica
     */
    public static function notify(string $title, string $body): void
    {
        $message = "📌 <b>" . htmlspecialchars($title) . "</b>\n\n";
        $message .= htmlspecialchars($body) . "\n";
        $message .= "\n<i>" . now()->format('d/m/Y H:i:s') . "</i>";

        self::send($message);
    }
}


<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSent;
use App\Services\TelegramService;
use App\Services\EmailLogService;
use Throwable;

class ForwardMailToTelegram
{
    /**
     * Handle the event.
     */
    public function handle(MessageSent $event): void
    {
        // abilita/disabilita con variabile d'ambiente
        if (! env('TELEGRAM_FORWARD_EMAILS', false)) {
            return;
        }

        try {
            $message = $event->message;

            // Estrai destinatari (dovrebbe funzionare sia per Swift_Message che Symfony Email)
            $tos = [];
            if (method_exists($message, 'getTo')) {
                $toArr = $message->getTo();
                if (is_array($toArr) && !empty($toArr)) {
                    $tos = array_keys($toArr);
                }
            } elseif (property_exists($message, 'to')) {
                // fallback generico
                $tos = (array) $message->to;
            }

            $toText = $tos ? implode(', ', $tos) : '(unknown)';

            // Estrai il corpo preferendo testo semplice
            $body = '';
            if (method_exists($message, 'getTextBody')) {
                $body = $message->getTextBody();
            } elseif (method_exists($message, 'getHtmlBody')) {
                $body = $message->getHtmlBody();
            } elseif (method_exists($message, 'getBody')) {
                $body = $message->getBody();
            } else {
                // fallback: cast a string e poi strip_tags
                $body = (string) $message;
            }

            $bodyText = $body ? strip_tags((string) $body) : '(no body)';

            // tronco per Telegram
            $max = (int) env('TELEGRAM_MAX_EMAIL_PREVIEW', 3800);
            if (mb_strlen($bodyText) > $max) {
                $bodyText = mb_substr($bodyText, 0, $max) . "\n\n(troncato...)";
            }

            // formato richiesto
            $telegramText = "to: {$toText}\n\n{$bodyText}";

            // manda su Telegram (parseHtml = false)
            TelegramService::send($telegramText, false);

            EmailLogService::logSend("Email forwarded to Telegram for: {$toText}");

        } catch (Throwable $e) {
            EmailLogService::logError('telegram_forward_listener', $e, []);
        }
    }
}
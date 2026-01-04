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

            // Estrarre destinatari in modo robusto
            $tos = [];

            if (method_exists($message, 'getTo')) {
                $toArr = $message->getTo();

                if (is_array($toArr) && !empty($toArr)) {
                    foreach ($toArr as $key => $val) {
                        // caso Swift_Message: chiave = email, valore = nome
                        if (is_string($key) && filter_var($key, FILTER_VALIDATE_EMAIL)) {
                            $tos[] = $key;
                            continue;
                        }

                        // caso Symfony Address o oggetto Address
                        if (is_object($val)) {
                            if (method_exists($val, 'getAddress')) {
                                $addr = $val->getAddress();
                                if ($addr) {
                                    $tos[] = $addr;
                                    continue;
                                }
                            }
                            // fallback: cast a string
                            $s = (string) $val;
                            if (filter_var($s, FILTER_VALIDATE_EMAIL)) {
                                $tos[] = $s;
                                continue;
                            }
                        }

                        // caso valore stringa
                        if (is_string($val) && filter_var($val, FILTER_VALIDATE_EMAIL)) {
                            $tos[] = $val;
                            continue;
                        }

                        // fallback: se la chiave è un indice numerico e il valore contiene email in formato testo
                        if (is_string($key) && filter_var($key, FILTER_VALIDATE_EMAIL)) {
                            $tos[] = $key;
                        }
                    }
                }
            } elseif (property_exists($message, 'to')) {
                // fallback generico
                $raw = $message->to;
                if (is_array($raw)) {
                    foreach ($raw as $k => $v) {
                        if (is_string($k) && filter_var($k, FILTER_VALIDATE_EMAIL)) {
                            $tos[] = $k;
                        } elseif (is_string($v) && filter_var($v, FILTER_VALIDATE_EMAIL)) {
                            $tos[] = $v;
                        }
                    }
                } elseif (is_string($raw) && filter_var($raw, FILTER_VALIDATE_EMAIL)) {
                    $tos[] = $raw;
                }
            }

            $toText = $tos ? implode(', ', array_unique($tos)) : '(unknown)';

            // Subject extraction
            $subject = '';
            if (method_exists($message, 'getSubject')) {
                $subject = (string) $message->getSubject();
            }

            // Body extraction (text preferito)
            $body = '';
            if (method_exists($message, 'getTextBody') && $message->getTextBody()) {
                $body = $message->getTextBody();
            } elseif (method_exists($message, 'getHtmlBody') && $message->getHtmlBody()) {
                $body = $message->getHtmlBody();
            } elseif (method_exists($message, 'getBody') && $message->getBody()) {
                $body = $message->getBody();
            } else {
                $body = (string) $message;
            }

            $bodyText = $body ? strip_tags((string) $body) : '(no body)';

            if (!empty($subject)) {
                $bodyText = "Oggetto: {$subject}\n\n{$bodyText}";
            }

            $max = (int) env('TELEGRAM_MAX_EMAIL_PREVIEW', 3800);
            if (mb_strlen($bodyText) > $max) {
                $bodyText = mb_substr($bodyText, 0, $max) . "\n\n(troncato...)";
            }

            $telegramText = "to: {$toText}\n\n{$bodyText}";

            // Invia su Telegram senza parse HTML
            TelegramService::send($telegramText, false);

            EmailLogService::logSend("Email forwarded to Telegram for: {$toText}");

        } catch (Throwable $e) {
            EmailLogService::logError('telegram_forward_listener', $e, []);
        }
    }
}
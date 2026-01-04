<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use App\Services\EmailLogService;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;

class SendQueuedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $backoff = [60];
    public $timeout = 120;

    protected $mailableClass;
    protected $mailableData;
    protected $to;
    protected $logContext;

    public function __construct($mailable, string $to, string $logContext = '')
    {
        $this->queue = 'emails'; // IMPORTANTE: usa la coda 'emails'
        $this->mailableClass = get_class($mailable);
        $this->mailableData = $this->extractMailableData($mailable);
        $this->to = $to;
        $this->logContext = $logContext;
    }

    public function handle(): void
    {
        Log::info("entro in app\Jobs\SendQueuedEmail.php::handle()");
        try {
            $mailable = $this->recreateMailable();

            // Se è attiva la funzione di forward su Telegram, non inviare via Mail
            if (env('TELEGRAM_FORWARD_EMAILS', true)) {
                Log::info("entro in app\Jobs\SendQueuedEmail.php::handle() dentro il if TELEGRAM_FORWARD_EMAILS");
                try {
                    // Cerco di ottenere il contenuto del mailable in ordine di fallback
                    $body = '';

                    if (method_exists($mailable, 'render')) {
                        Log::info("entro in app\Jobs\SendQueuedEmail.php::handle() dentro il if render()");
                        // render() è disponibile sui Mailable Laravel
                        $body = $mailable->render();
                    } elseif (property_exists($mailable, 'body') && !empty($mailable->body)) {
                        Log::info("entro in app\Jobs\SendQueuedEmail.php::handle() dentro il elseif body()");
                        $body = $mailable->body;
                    } else {
                        Log::info("entro in app\Jobs\SendQueuedEmail.php::handle() dentro il else finale");
                        // Proviamo a ottenere subject / view se il mailable implementa envelope()/content()
                        try {
                            if (method_exists($mailable, 'content')) {
                                $content = $mailable->content();
                                if (isset($content->view)) {
                                    // render della view con i dati pubblici se possibile
                                    $data = [];
                                    foreach (get_object_vars($mailable) as $k => $v) {
                                        $data[$k] = $v;
                                    }
                                    $body = view($content->view, $data)->render();
                                }
                            }
                        } catch (\Throwable $e) {
                            // noop
                        }
                    }

                    $bodyText = $body ? strip_tags((string)$body) : '(no body)';

                    Log::info("bodyText estratto: " . $bodyText);
                    // Limite per Telegram (con margine)
                    $max = (int) env('TELEGRAM_MAX_EMAIL_PREVIEW', 3800);
                    if (mb_strlen($bodyText) > $max) {
                        $bodyText = mb_substr($bodyText, 0, $max) . "\n\n(troncato...)";
                    }

                    Log::info("bodyText troncato: " . $bodyText);
                    // Testo esattamente nel formato richiesto
                    $telegramText = "to: {$this->to}\n\n{$bodyText}";

                    // Invia al canale/admin configurato in TelegramService
                    TelegramService::send($telegramText, false);

                    EmailLogService::logSend("Email (forwarded to Telegram) per {$this->to}");

                } catch (\Exception $e) {
                    EmailLogService::logError('telegram_forward', $e, ['to' => $this->to]);
                }

                // Esco: non invoco Mail::send() perché vogliamo solo leggere le mail su Telegram
                return;
            }

            // Default behavior: invia l'email via Mail
            Mail::to($this->to)->send($mailable);
            EmailLogService::logSend("Email inviata a {$this->to}");

        } catch (\Exception $e) {
            EmailLogService::logError('send_queued', $e, ['to' => $this->to]);
            throw $e;
        }
    }

    /**
     * Estrae i dati dal mailable per la serializzazione
     */
    protected function extractMailableData($mailable): array
    {
        $data = [];
        $reflection = new \ReflectionClass($mailable);

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            $value = $property->getValue($mailable);

            // Serializza solo tipi semplici
            if (is_scalar($value) || is_null($value) || is_array($value)) {
                $data[$name] = $value;
            } elseif (is_object($value) && method_exists($value, 'toArray')) {
                $data[$name] = $value->toArray();
            } elseif ($value instanceof \Illuminate\Database\Eloquent\Model) {
                $data[$name] = ['id' => $value->id, '_class' => get_class($value)];
            }
        }

        return $data;
    }

    /**
     * Ricrea il mailable dai dati serializzati
     */
    protected function recreateMailable()
    {
        $class = $this->mailableClass;
        return new $class(...array_values($this->mailableData));
    }
}


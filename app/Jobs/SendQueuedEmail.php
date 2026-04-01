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

    protected $mailable;
    protected $to;
    protected $logContext;

    public function __construct($mailable, string $to, string $logContext = '')
    {
        $this->queue = 'emails'; // IMPORTANTE: usa la coda 'emails'
        $this->mailable = $mailable;
        $this->to = $to;
        $this->logContext = $logContext;
    }

    public function handle(): void
    {
        Log::info("entro in app\Jobs\SendQueuedEmail.php::handle()");
        try {
            $mailable = $this->mailable;

            // Se è attiva la funzione di forward su Telegram, invia la preview
            if (env('TELEGRAM_FORWARD_EMAILS', true)) {
                Log::info("SendQueuedEmail: Eseguo Forward Telegram");
                try {
                    $body = '';
                    if (method_exists($mailable, 'render')) {
                        $body = $mailable->render();
                    } elseif (property_exists($mailable, 'body')) {
                        $body = $mailable->body;
                    }

                    $bodyText = $body ? strip_tags((string)$body) : '(no body available for preview)';
                    
                    // Limite per Telegram
                    $max = (int) env('TELEGRAM_MAX_EMAIL_PREVIEW', 3800);
                    if (mb_strlen($bodyText) > $max) {
                        $bodyText = mb_substr($bodyText, 0, $max) . "\n\n(troncato...)";
                    }

                    $telegramText = "📬 <b>Email per:</b> {$this->to} (" . ($this->logContext ?: 'Nessun contesto') . ")\n\n" . $bodyText;
                    TelegramService::send($telegramText, false);

                } catch (\Exception $e) {
                    Log::error("Errore Forward Telegram: " . $e->getMessage());
                }

                // NON uscire: proseguiamo con l'invio della vera email
            }

            // Invio effettivo della mail
            Log::info("SendQueuedEmail: Invio mail reale a {$this->to}");
            Mail::to($this->to)->send($mailable);
            EmailLogService::logSend("Email inviata a {$this->to}");

        } catch (\Exception $e) {
            EmailLogService::logError('send_queued', $e, ['to' => $this->to]);
            Log::error("Errore critico in SendQueuedEmail: " . $e->getMessage());
            throw $e;
        }
    }
}


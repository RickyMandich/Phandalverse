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
use ReflectionClass;

class SendQueuedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $mailableClass;
    protected $mailableData;
    protected $to;
    protected $logContext;

    /**
     * Costruttore: estrae i dati in modo "Safe" per evitare errori di serializzazione PDO.
     */
    public function __construct($mailable, string $to, string $logContext = '')
    {
        $this->queue = 'emails';
        $this->mailableClass = get_class($mailable);
        $this->to = $to;
        $this->logContext = $logContext;
        
        // Estrazione sicura delle proprietà pubbliche
        $this->mailableData = $this->extractSafeData($mailable);
    }

    public function handle(): void
    {
        Log::info("SendQueuedEmail: Avvio elaborazione job");
        try {
            // Ricostruzione Mailable via Reflection (senza passare dal costruttore)
            $mailable = $this->restoreMailable();

            // 1. Forward su Telegram (Preview)
            if (env('TELEGRAM_FORWARD_EMAILS', true)) {
                Log::info("SendQueuedEmail: Eseguo Forward Telegram");
                try {
                    $body = method_exists($mailable, 'render') ? $mailable->render() : ($mailable->body ?? '');
                    $bodyText = $body ? strip_tags((string)$body) : '(no preview content)';
                    
                    $max = (int) env('TELEGRAM_MAX_EMAIL_PREVIEW', 3800);
                    if (mb_strlen($bodyText) > $max) $bodyText = mb_substr($bodyText, 0, $max) . "...";

                    $telegramText = "📬 <b>Email:</b> {$this->to}\nCtx: {$this->logContext}\n\n" . $bodyText;
                    TelegramService::send($telegramText, false);
                } catch (\Exception $e) {
                    Log::error("Errore Forward Telegram: " . $e->getMessage());
                }
            }

            // 2. Invio Mail Reale
            Log::info("SendQueuedEmail: Invio mail reale a {$this->to}");
            Mail::to($this->to)->send($mailable);
            EmailLogService::logSend("Email inviata a {$this->to}");

        } catch (\Exception $e) {
            EmailLogService::logError('send_queued_critical', $e, ['to' => $this->to]);
            Log::error("Errore critico in SendQueuedEmail: " . $e->getMessage());
            throw $e;
        }
    }

    protected function extractSafeData($obj): array
    {
        $data = [];
        
        // get_object_vars su un oggetto ritorna AUTOMATICAMENTE solo le proprietà PUBBLICHE e NON STATICHE.
        // È il modo più sicuro per evitare di catturare viewDataCallback e simili.
        $vars = get_object_vars($obj);
        
        Log::info("SendQueuedEmail: Estratti campi sicuri", ['fields' => array_keys($vars)]);

        foreach ($vars as $name => $value) {
            // Se è un'eccezione, la convertiamo in un oggetto stub sicuro (senza trace/PDO)
            if ($value instanceof \Throwable) {
                $data[$name] = (object) [
                    '__is_stub_exception' => true,
                    'class' => get_class($value),
                    'message' => $value->getMessage(),
                    'file' => $value->getFile(),
                    'line' => $value->getLine()
                ];
            } else {
                $data[$name] = $value;
            }
        }
        return $data;
    }

    /**
     * Ricrea il mailable iniettando i dati estratti.
     */
    protected function restoreMailable()
    {
        $reflection = new ReflectionClass($this->mailableClass);
        $mailable = $reflection->newInstanceWithoutConstructor();
        
        foreach ($this->mailableData as $key => $value) {
            try {
                // Tentativo di impostazione sicura
                if (is_object($value) && isset($value->__is_stub_exception)) {
                    $mailable->{$key} = $value->class; 
                } else {
                    $mailable->{$key} = $value;
                }
            } catch (\Throwable $e) {
                // Logghiamo l'errore ma proseguiamo: probabilmente una proprietà statica o protetta "fantasma"
                Log::warning("SendQueuedEmail: Impossibile ripristinare proprietà '{$key}'", [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $mailable;
    }
}

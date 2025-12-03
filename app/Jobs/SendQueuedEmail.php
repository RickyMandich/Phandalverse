<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use App\Services\EmailLogService;

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
        try {
            $mailable = $this->recreateMailable();
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


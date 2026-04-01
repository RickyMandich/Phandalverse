<?php

namespace App\Services;

use App\Jobs\SendQueuedEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Services\EmailLogService;

class EmailQueueService
{
    /**
     * Mette in coda un'email
     */
    public static function queue($mailable, $to, string $logContext = '', int $delay = 0)
    {
        if (is_array($to)) {
            foreach ($to as $recipient) {
                self::queueSingle($mailable, $recipient, $logContext, $delay);
            }
            return;
        }

        self::queueSingle($mailable, $to, $logContext, $delay);
    }

    /**
     * Mette in coda una singola email
     */
    protected static function queueSingle($mailable, string $to, string $logContext = '', int $delay = 0)
    {
        try {
            $job = new SendQueuedEmail($mailable, $to, $logContext);

            if ($delay > 0) {
                $job->delay(now()->addSeconds($delay));
            }

            dispatch($job);

            // CRUCIALE: Avvia il processore fire-and-forget
            self::triggerQueueProcessor();

            EmailLogService::logQueue("Email accodata per: {$to}");

        } catch (\Exception $e) {
            EmailLogService::logError('Email Queue', $e, ['to' => $to]);
        }
    }

    /**
     * Avvia il processore coda email via fire-and-forget
     * Si attiva solo se non è già in esecuzione (throttle 30 secondi)
     */
    protected static function triggerQueueProcessor()
    {
        try {
            $lastProcessorRun = \Cache::get('email_processor_last_run', 0);
            $now = time();

            // Avvia solo se non è stato eseguito negli ultimi 30 secondi
            if (($now - $lastProcessorRun) > 30) {
                \Cache::put('email_processor_last_run', $now, 60);

                $url = route('job.processEmailQueue');
                $token = env('JOB_TOKEN');
                
                \Log::info("Triggering Queue Processor", ['url' => $url, 'has_token' => !empty($token)]);

                \App\Http\Controllers\JobController::fireAndForgetGet(
                    $url,
                    ['token' => $token]
                );

                EmailLogService::logProcessor('Processore avviato automaticamente');
            }

        } catch (\Exception $e) {
            EmailLogService::logError('Trigger Queue Processor', $e);
        }
    }

    /**
     * Invia a tutti gli admin
     */
    public static function queueToAdmins($mailable, string $logContext = '')
    {
        try {
            $admins = \App\Models\User::getAdmins();

            if ($admins->isEmpty()) {
                Log::warning('Nessun admin trovato per la notifica');
                return;
            }

            self::queueToUsers($mailable, $admins, $logContext);

        } catch (\Exception $e) {
            EmailLogService::logError('Queue To Admins', $e);
        }
    }

    /**
     * Invia a più utenti
     */
    public static function queueToUsers($mailable, $users, string $logContext = '', int $batchDelay = 0)
    {
        foreach ($users as $user) {
            if (!empty($user->email)) {
                self::queueSingle($mailable, $user->email, $logContext, 0);
            }
        }
    }

    /**
     * Invia immediatamente (bypass coda, solo per emergenze)
     */
    public static function sendImmediate($mailable, string $to, string $logContext = ''): bool
    {
        try {
            Mail::to($to)->send($mailable);
            return true;
        } catch (\Exception $e) {
            EmailLogService::logError('Send Immediate', $e, ['to' => $to]);
            return false;
        }
    }
}


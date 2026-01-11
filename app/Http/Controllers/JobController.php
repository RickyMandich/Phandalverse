<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\EmailLogService;

class JobController extends Controller
{
    /**
     * Esegue una richiesta GET "fire-and-forget" senza aspettare la risposta
     * Usa socket raw per inviare la richiesta e chiudere subito la connessione
     */
    public static function fireAndForgetGet($url, $data = [])
    {
        $query = http_build_query($data);
        $parts = parse_url($url);

        if (!isset($parts['host']) || !isset($parts['path'])) {
            return false;
        }

        $path = $parts['path'];
        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?' . $parts['query'] . '&' . $query;
        } elseif ($query !== '') {
            $path .= '?' . $query;
        }

        // Usa fsockopen per una connessione asincrona
        $fp = fsockopen($parts['host'], $parts['port'] ?? 80, $errno, $errstr, 30);

        if (!$fp) {
            return false;
        }

        $out = "GET " . $path . " HTTP/1.1\r\n";
        $out .= "Host: " . $parts['host'] . "\r\n";
        $out .= "Connection: Close\r\n\r\n";

        fwrite($fp, $out);
        fclose($fp); // Chiude subito, senza aspettare risposta

        return true;
    }

    /**
     * Processa la coda email con rate limiting
     * Questo metodo viene chiamato via fire-and-forget
     */
    public function processEmailQueue(Request $request)
    {
        // Verifica token per sicurezza
        if ($request->input('token') !== env('JOB_TOKEN')) {
            abort(403, 'Unauthorized');
        }

        $logFile = EmailLogService::createLogFile('processor');
        $startTime = time();
        $maxExecutionTime = 240; // 4 minuti limite
        $processedCount = 0;

        try {
            // Elabora i job uno alla volta per evitare race conditions tra processi paralleli
            while (true) {
                // Controlla timeout globale del processore
                if ((time() - $startTime) > $maxExecutionTime) {
                    $remainingJobs = \DB::table('jobs')->where('queue', 'emails')->count();
                    if ($remainingJobs > 0) {
                        self::fireAndForgetGet(route('job.processEmailQueue'), [
                            'token' => env('JOB_TOKEN')
                        ]);
                    }
                    return;
                }

                // Cerca il prossimo job disponibile (non riservato o riservato da troppo tempo)
                $jobRecord = \DB::table('jobs')
                    ->where('queue', 'emails')
                    ->where(function ($q) {
                        $q->whereNull('reserved_at')
                            ->orWhere('reserved_at', '<=', time() - 300); // Retry se bloccato da > 5 min
                    })
                    ->where('available_at', '<=', time())
                    ->orderBy('available_at', 'asc')
                    ->first();

                if (!$jobRecord) {
                    break; // Nessun job rimasto
                }

                // Riserva il job immediatamente
                \DB::table('jobs')->where('id', $jobRecord->id)->update([
                    'reserved_at' => time(),
                    'attempts' => $jobRecord->attempts + 1
                ]);

                // Rate limiting: 1 secondo tra ogni email (per non saturare Telegram/Mail)
                if ($processedCount > 0) {
                    sleep(1);
                }

                try {
                    $payload = json_decode($jobRecord->payload, true);
                    $jobClass = $payload['displayName'] ?? null;

                    if ($jobClass === 'App\\Jobs\\SendQueuedEmail') {
                        $jobData = unserialize($payload['data']['command']);

                        // Esegui l'invio email (o il forward su Telegram se configurato)
                        $jobData->handle();

                        // Rimuovi dalla coda dopo successo
                        \DB::table('jobs')->where('id', $jobRecord->id)->delete();
                        $processedCount++;
                    } else {
                        // Job ignoto, rimuovi per sicurezza o segna come fallito
                        \DB::table('jobs')->where('id', $jobRecord->id)->delete();
                    }

                } catch (\Exception $e) {
                    // Sposta in failed_jobs
                    \DB::table('jobs')->where('id', $jobRecord->id)->delete();
                    \DB::table('failed_jobs')->insert([
                        'uuid' => (string) Str::uuid(),
                        'connection' => 'database',
                        'queue' => 'emails',
                        'payload' => $jobRecord->payload,
                        'exception' => (string) $e,
                        'failed_at' => now()
                    ]);
                    EmailLogService::logError('Email Job Error', $e, ['job_id' => $jobRecord->id]);
                }
            }

            EmailLogService::logProcessor("Processati {$processedCount} job in questa sessione.");


            // Controlla se ci sono altri job
            $remainingJobs = \DB::table('jobs')->where('queue', 'emails')->count();
            if ($remainingJobs > 0) {
                // Riavvia automaticamente
                self::fireAndForgetGet(route('job.processEmailQueue'), [
                    'token' => env('JOB_TOKEN')
                ]);
            }

        } catch (\Exception $e) {
            EmailLogService::logError('Email Queue Processor', $e);
        }
    }
}


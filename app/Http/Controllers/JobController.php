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
    public static function fireAndForgetGet($url, $data = []) {
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
            // Recupera i job email dalla tabella jobs
            $pendingJobs = \DB::table('jobs')
                ->where('queue', 'emails')
                ->orderBy('available_at', 'asc')
                ->limit(50)
                ->get();

            EmailLogService::logProcessor("Job trovati: {$pendingJobs->count()}", 'INFO', $logFile);

            if ($pendingJobs->isEmpty()) {
                return;
            }

            foreach ($pendingJobs as $jobRecord) {
                // Controlla timeout
                if ((time() - $startTime) > $maxExecutionTime) {
                    // Riavvia il processore per i job rimanenti
                    $remainingJobs = \DB::table('jobs')->where('queue', 'emails')->count();
                    if ($remainingJobs > 0) {
                        self::fireAndForgetGet(route('job.processEmailQueue'), [
                            'token' => env('JOB_TOKEN')
                        ]);
                    }
                    return;
                }

                // Rate limiting: 1 secondo tra ogni email
                sleep(1);

                try {
                    $payload = json_decode($jobRecord->payload, true);
                    $jobClass = $payload['displayName'] ?? null;

                    if ($jobClass === 'App\\Jobs\\SendQueuedEmail') {
                        $jobData = unserialize($payload['data']['command']);

                        // Esegui l'invio email
                        $jobData->handle();

                        // Rimuovi dalla coda
                        \DB::table('jobs')->where('id', $jobRecord->id)->delete();
                        $processedCount++;
                    }

                } catch (\Exception $e) {
                    // Gestisci errore: sposta in failed_jobs
                    \DB::table('jobs')->where('id', $jobRecord->id)->delete();
                    \DB::table('failed_jobs')->insert([
                        'uuid' => Str::uuid(),
                        'connection' => 'database',
                        'queue' => 'emails',
                        'payload' => $jobRecord->payload,
                        'exception' => $e->getMessage(),
                        'failed_at' => now()
                    ]);
                }
            }

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


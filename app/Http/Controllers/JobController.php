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
        // Forza HTTP per il loopback interno (evita il blocco del tunnel SSL su Altervista)
        $url = str_replace('https://', 'http://', $url);
        
        $query = http_build_query($data);
        $fullUrl = $url . (str_contains($url, '?') ? '&' : '?') . $query;

        \Log::info("Attempting Fire-and-Forget", ['url' => $fullUrl]);

        // --- Metodo 1: cURL (Più affidabile su PHP moderno) ---
        if (function_exists('curl_init')) {
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $fullUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
                
                // USER AGENT REALE (Fondamentale su Altervista per evitare 403)
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
                
                // DISABILITA PROXY PER IL LOOPBACK (Fix per Altervista 403)
                curl_setopt($ch, CURLOPT_PROXY, "");
                curl_setopt($ch, CURLOPT_NOPROXY, "*");
                
                // Disabilitiamo verifica SSL per i trigger interni
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                
                $result = curl_exec($ch);
                $info = curl_getinfo($ch);
                $error = curl_error($ch);
                curl_close($ch);
                
                \Log::info("Fire-and-Forget via cURL completato", [
                    'http_code' => $info['http_code'],
                    'error' => $error ?: 'none'
                ]);

                if ($info['http_code'] >= 200 && $info['http_code'] < 400) {
                    return true;
                }
                
                \Log::warning("Fire-and-Forget via cURL non ha restituito un successo (Code: {$info['http_code']}), provo fallback...");
            } catch (\Exception $e) {
                \Log::warning("Fire-and-Forget via cURL fallito: " . $e->getMessage());
            }
        }

        // --- Metodo 2: fsockopen (Fallback classico) ---
        $parts = parse_url($url);
        if (!isset($parts['host'])) return false;

        $scheme = $parts['scheme'] ?? 'http';
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $host = ($scheme === 'https' ? 'ssl://' : '') . $parts['host'];
        $path = $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '') . (isset($parts['query']) ? '&' : '?') . $query;

        $fp = @fsockopen($host, $port, $errno, $errstr, 2);
        if ($fp) {
            $out = "GET " . $path . " HTTP/1.1\r\n";
            $out .= "Host: " . $parts['host'] . "\r\n";
            $out .= "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36\r\n";
            $out .= "Connection: Close\r\n\r\n";
            fwrite($fp, $out);
            fclose($fp);
            \Log::info("Fire-and-Forget via fsockopen completato");
            return true;
        }

        \Log::error("Fire-and-Forget TOTAL FAILURE: Impossibile connettersi a {$host}:{$port} - {$errstr} ({$errno})");
        return false;
    }

    /**
     * Elabora il prossimo job (o più) dalla coda email.
     * Metodo statico per essere chiamato sia dal Middleware che dal Controller.
     */
    public static function processNextJob(int $limit = 1): int
    {
        // Rate limiting di sicurezza tra esecuzioni (evita sovrapposizioni troppe vicine)
        $lastRun = \Cache::get('email_queue_last_run', 0);
        $now = time();
        
        // Se l'ultima esecuzione è avvenuta meno di 1 secondo fa, saltiamo
        if (($now - $lastRun) < 1) {
            return 0;
        }

        $processedCount = 0;
        try {
            for ($i = 0; $i < $limit; $i++) {
                $jobRecord = \DB::table('jobs')
                    ->where('queue', 'emails')
                    ->where(function ($q) {
                        $q->whereNull('reserved_at')
                          ->orWhere('reserved_at', '<=', time() - 300);
                    })
                    ->where('available_at', '<=', time())
                    ->orderBy('available_at', 'asc')
                    ->first();

                if (!$jobRecord) break;

                // Riserva il job
                \DB::table('jobs')->where('id', $jobRecord->id)->update([
                    'reserved_at' => time(),
                    'attempts' => $jobRecord->attempts + 1
                ]);

                // Aggiorna cache last run prima dell'invio (per bloccare altre istanze parallele)
                \Cache::put('email_queue_last_run', time(), 60);

                try {
                    $payload = json_decode($jobRecord->payload, true);
                    $jobClass = $payload['displayName'] ?? null;

                    if ($jobClass === 'App\\Jobs\\SendQueuedEmail') {
                        $jobData = unserialize($payload['data']['command']);
                        
                        // Esegui l'invio (AltervistaTransport si occuperà del log finale)
                        $jobData->handle(); 
                        
                        \DB::table('jobs')->where('id', $jobRecord->id)->delete();
                        $processedCount++;
                    } else {
                        // Job non supportato o corrotto
                        \DB::table('jobs')->where('id', $jobRecord->id)->delete();
                    }
                } catch (\Exception $e) {
                    // Sposta in failed_jobs
                    \DB::table('jobs')->where('id', $jobRecord->id)->delete();
                    \DB::table('failed_jobs')->insert([
                        'uuid' => (string) \Illuminate\Support\Str::uuid(),
                        'connection' => 'database',
                        'queue' => 'emails',
                        'payload' => $jobRecord->payload,
                        'exception' => (string) $e,
                        'failed_at' => now()
                    ]);
                    EmailLogService::logError('Job Middleware Error', $e, ['job_id' => $jobRecord->id ?? 'unk']);
                }

                // Piccolo sleep tra i job se ne stiamo processando più di uno
                if ($i < ($limit - 1)) {
                    sleep(1);
                }
            }
        } catch (\Exception $e) {
             \Log::error("Errore critico processore parassita: " . $e->getMessage());
        }

        return $processedCount;
    }

    /**
     * Processa la coda email con rate limiting
     * Questo metodo viene chiamato via fire-and-forget
     */
    public function processEmailQueue(Request $request)
    {
        $tokenRecv = $request->input('token');
        $tokenEnv = env('JOB_TOKEN');
        $match = ($tokenRecv === $tokenEnv);

        \Log::info("JobController: Ricevuta richiesta per processEmailQueue", [
            'has_token' => $request->has('token'),
            'token_match' => $match,
            'token_expected_len' => strlen($tokenEnv ?? ''),
            'token_received_len' => strlen($tokenRecv ?? ''),
            'ip' => $request->ip(),
            'ua' => $request->userAgent()
        ]);

        // Verifica token per sicurezza
        if (!$match) {
            abort(403, 'Unauthorized');
        }

        try {
            // Usa il metodo statico condiviso per elaborare un blocco di job.
            // Impostiamo un limite per la chiamata diretta.
            $processedCount = self::processNextJob(30); 
            
            return response()->json([
                'status' => 'success',
                'processed' => $processedCount
            ]);
        } catch (\Exception $e) {
            EmailLogService::logError('Email Queue Processor HTTP', $e);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}


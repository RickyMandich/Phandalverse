<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\SystemError;

// Carica le variabili non sensibili (es. APP_VERSION_*) da .env-overrides,
// file tracciato in Git a differenza di .env. Va fatto PRIMA che Laravel
// processi il suo .env principale: il repository usato da Laravel per
// leggere il .env e' immutabile e non sovrascrive variabili gia' presenti
// in $_ENV/$_SERVER, quindi impostandole qui vincono su quelle (se presenti)
// nel .env vero e proprio.
\Dotenv\Dotenv::createMutable(dirname(__DIR__), '.env-overrides')->safeLoad();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust proxies for HTTPS detection on hostings like Altervista
        $middleware->trustProxies(at: '*');

        // Registra l'alias per il middleware admin
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'master' => \App\Http\Middleware\MasterMiddleware::class,
            'master_utils' => \App\Http\Middleware\MasterUtilsMiddleware::class,
        ]);

        // Aggiungi middleware per tracciare statistiche e processare la coda email
        $middleware->web(append: [
            \App\Http\Middleware\TrackStatistics::class,
            \App\Http\Middleware\ProcessEmailQueueMiddleware::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            '/telegram/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // ========== REPORTABLE: Salva errore e invia notifiche ==========
        $exceptions->reportable(function (Throwable $e) {
            $systemError = null;
            $requestUrl = null;
            $requestMethod = null;
            $userAgent = null;
            $userId = null;

            try {
                $requestUrl = request()->fullUrl();
                $requestMethod = request()->method();
                $userAgent = request()->userAgent();
            } catch (\Throwable $reqEx) {
                // Ignora errori nel recupero della request
            }

            try {
                $userId = Auth::id();
            } catch (\Throwable $authEx) {
                // Se Auth non è ancora inizializzato, userId resta null
            }

            // 1. SALVA ERRORE NEL DATABASE
            try {
                $systemError = SystemError::create([
                    'exception_class' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'request_url' => $requestUrl,
                    'request_method' => $requestMethod,
                    'user_agent' => $userAgent,
                    'user_id' => $userId,
                    'status' => 'new',
                ]);
            } catch (\Throwable $dbException) {
                // Fallback silenzioso se DB non disponibile
            }

            // 2. NOTIFICA AGLI ADMIN: usa Telegram invece delle email (le email vengono spesso scritte solo nei log)
            try {
                if (env('TELEGRAM_BOT_TOKEN')) {
                    \App\Services\TelegramService::notifyError($e, $requestUrl);
                } else {
                    // Se Telegram non configurato, scrivi un warning nel log
                    Log::warning('Telegram non configurato: TELEGRAM_BOT_TOKEN mancante, notifica errore non inviata');
                }
            } catch (\Throwable $telEx) {
                // Ignora errore telegram
            }
        });

        // ========== RENDERABLE: Debug differenziato per tipo utente ==========
        $exceptions->renderable(function (Throwable $e, $request) {
            $isAdmin = false;
            try {
                $isAdmin = Auth::check() && Auth::user()?->isAdmin();
            } catch (\Throwable $authEx) {
                $isAdmin = false;
            }

            // Se l'utente è admin, mostra debug completo
            if ($isAdmin) {
                config(['app.debug' => true]);

                return response()->view('errors.admin-debug', [
                    'exception' => $e,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'request' => $request,
                ], 503);
            }

            // Per utenti normali, nascondi i dettagli
            config(['app.debug' => false]);
            return null; // Usa il comportamento default di Laravel
        });
    })->create();

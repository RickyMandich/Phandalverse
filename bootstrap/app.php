<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\SystemError;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Registra l'alias per il middleware admin
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'master' => \App\Http\Middleware\MasterMiddleware::class,
            'master_utils' => \App\Http\Middleware\MasterUtilsMiddleware::class,
        ]);

        // Aggiungi middleware per tracciare statistiche su tutte le richieste web
        $middleware->web(append: [
            \App\Http\Middleware\TrackStatistics::class,
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

            try {
                $requestUrl = request()->fullUrl();
                $requestMethod = request()->method();
                $userAgent = request()->userAgent();
            } catch (\Exception $reqEx) {
                // Ignora errori nel recupero della request
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
                    'user_id' => Auth::id(),
                    'status' => 'new',
                ]);
            } catch (\Exception $dbException) {
                Log::error("Impossibile salvare errore nel database: " . $dbException->getMessage());
            }

            // 2. NOTIFICA AGLI ADMIN: usa Telegram invece delle email (le email vengono spesso scritte solo nei log)
            try {
                if (env('TELEGRAM_BOT_TOKEN')) {
                    \App\Services\TelegramService::notifyError($e, $requestUrl);
                } else {
                    // Se Telegram non configurato, scrivi un warning nel log
                    Log::warning('Telegram non configurato: TELEGRAM_BOT_TOKEN mancante, notifica errore non inviata');
                }
            } catch (\Exception $telEx) {
                Log::error("Impossibile inviare notifica Telegram: " . $telEx->getMessage());
            }
        });

        // ========== RENDERABLE: Debug differenziato per tipo utente ==========
        $exceptions->renderable(function (Throwable $e, $request) {
            // Se l'utente è admin, mostra debug completo
            if (Auth::isAdmin()) {
                config(['app.debug' => true]);

                return response()->view('errors.admin-debug', [
                    'exception' => $e,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'request' => $request,
                ], 500);
            }

            // Per utenti normali, nascondi i dettagli
            config(['app.debug' => false]);
            return null; // Usa il comportamento default di Laravel
        });
    })->create();

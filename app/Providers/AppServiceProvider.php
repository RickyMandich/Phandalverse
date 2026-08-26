<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * ID univoco della sessione di esecuzione corrente
     */
    public static ?string $executionId = null;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS for all generated links in production
        URL::forceScheme('https');

        // ========== CUSTOM AUTH PROVIDER ==========
        Auth::provider('custom', function ($app, array $config) {
            return new \App\Auth\CustomUserProvider($app['hash'], $config['model']);
        });

        // ========== CUSTOM MAIL TRANSPORT PER ALTERVISTA ==========
        \Illuminate\Support\Facades\Mail::extend('altervista', function () {
            return new \App\Mail\Transport\AltervistaTransport();
        });

        // ========== LOG EXECUTION MARKERS ==========
        try {
            // Genera un ID univoco per questa esecuzione
            self::$executionId = Str::uuid()->toString();
            $requestInfo = $this->getRequestInfo();

            // Scrivi marker di INIZIO esecuzione
            Log::channel('single')->info("▶▶▶ EXECUTION_START [{$this->getExecutionId()}] {$requestInfo}");

            // Registra shutdown function per marker di FINE
            register_shutdown_function(function () {
                try {
                    $error = error_get_last();
                    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                        // Se c'è stato un fatal error, marca come crash
                        Log::channel('single')->error("◼◼◼ EXECUTION_CRASH [{$this->getExecutionId()}] Fatal: {$error['message']}");
                    } else {
                        // Fine normale
                        Log::channel('single')->info("◀◀◀ EXECUTION_END [{$this->getExecutionId()}]");
                    }
                } catch (\Throwable $e) {
                    // Ignore shutdown logging errors
                }
            });
        } catch (\Throwable $e) {
            // Ignore log marker errors if storage/logs is not writable
        }
    }

    /**
     * Ottiene l'ID di esecuzione corrente
     */
    public function getExecutionId(): string
    {
        return self::$executionId ?? 'unknown';
    }

    /**
     * Ottiene informazioni sulla request corrente
     */
    private function getRequestInfo(): string
    {
        try {
            $method = request()->method();
            $url = request()->path();
            return "{$method} /{$url}";
        } catch (\Exception $e) {
            return 'CLI/Console';
        }
    }
}

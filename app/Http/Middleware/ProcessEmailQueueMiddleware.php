<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Controllers\JobController;

class ProcessEmailQueueMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Terminate the request/response cycle.
     * Questo metodo viene eseguito DOPO che la risposta è stata inviata al browser.
     * Su Altervista (con PHP-FPM) permette di processare la coda senza far aspettare l'utente.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Processiamo la coda solo per richieste web standard (GET) e non AJAX
        // per evitare di sovraccaricare il server su ogni singola piccola chiamata.
        if ($request->isMethod('GET') && !$request->expectsJson() && !str_contains($request->path(), 'admin/logs')) {
            // Tentiamo di processare il prossimo job in sospeso
            JobController::processNextJob(1); 
        }
    }
}

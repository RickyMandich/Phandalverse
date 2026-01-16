<?php

namespace App\Http\Middleware;

use App\Models\Statistic;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TrackStatistics
{
    /**
     * Handle an incoming request and track statistics.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Record start time for performance tracking
        $startTime = microtime(true);

        // Process the request
        $response = $next($request);

        // Calculate response time in milliseconds
        $responseTime = (microtime(true) - $startTime) * 1000;

        // Attempt to save statistics (don't break app if this fails)
        try {
            Statistic::create([
                'user_id' => Auth::id(), // null if not logged in
                'ip_address' => $request->ip(),
                'url' => $request->fullUrl(),
                'http_method' => $request->method(),
                'user_agent' => $request->userAgent(),
                'referrer' => $request->header('referer'),
                'response_status' => $response->getStatusCode(),
                'response_time' => round($responseTime, 2),
            ]);
        } catch (\Exception $e) {
            // Log error but don't interrupt the application
            Log::error('Failed to save statistics: ' . $e->getMessage());
        }

        return $response;
    }
}

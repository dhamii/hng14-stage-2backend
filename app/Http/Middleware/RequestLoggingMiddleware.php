<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestLoggingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $response = $next($request);
        $duration = round((microtime(true) - $start) * 1000, 2);

        Log::info('request_log', [
            'method' => $request->method(),
            'endpoint' => $request->path(),
            'status' => $response->getStatusCode(),
            'response_time_ms' => $duration,
            'user_id' => $request->user()?->id,
        ]);

        return $response;
    }
}

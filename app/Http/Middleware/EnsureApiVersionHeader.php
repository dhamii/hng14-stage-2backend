<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiVersionHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('X-API-Version') !== '1') {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing or invalid X-API-Version header',
            ], 400);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccessTokenAbility
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (!$token || !$token->can('access')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid access token',
            ], 401);
        }

        return $next($request);
    }
}

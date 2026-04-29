<?php

namespace App\Http\Controllers;

use App\Models\OAuthState;
use App\Models\User;
use App\Services\GitHubOAuthService;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function redirectToGitHub(Request $request): RedirectResponse|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code_challenge' => 'required|string',
            'code_challenge_method' => 'nullable|in:S256',
            'redirect_uri' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Invalid OAuth request'], 422);
        }

        $state = Str::random(40);
        OAuthState::create([
            'state' => $state,
            'code_challenge' => $request->string('code_challenge')->value(),
            'code_challenge_method' => $request->get('code_challenge_method', 'S256'),
            'redirect_uri' => $request->string('redirect_uri')->value(),
            'expires_at' => now()->addMinutes(10),
        ]);

        $query = http_build_query([
            'client_id' => config('services.github.client_id'),
            'redirect_uri' => config('services.github.callback_url'),
            'scope' => 'read:user user:email',
            'state' => $state,
            'code_challenge' => $request->string('code_challenge')->value(),
            'code_challenge_method' => $request->get('code_challenge_method', 'S256'),
        ]);

        return redirect()->away("https://github.com/login/oauth/authorize?{$query}");
    }

    public function handleCallback(Request $request, GitHubOAuthService $githubOAuthService, TokenService $tokenService): JsonResponse|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'state' => 'required|string',
            'code_verifier' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Invalid callback payload'], 422);
        }

        $oauthState = OAuthState::where('state', $request->state)->first();

        if (!$oauthState || $oauthState->expires_at->isPast()) {
            return response()->json(['status' => 'error', 'message' => 'OAuth state expired or invalid'], 400);
        }

        $expectedChallenge = rtrim(strtr(base64_encode(hash('sha256', $request->code_verifier, true)), '+/', '-_'), '=');
        if (!hash_equals($oauthState->code_challenge, $expectedChallenge)) {
            return response()->json(['status' => 'error', 'message' => 'PKCE verification failed'], 400);
        }

        $tokenData = $githubOAuthService->exchangeCode($request->code, $request->code_verifier, config('services.github.callback_url'));
        $githubUser = $githubOAuthService->fetchUser($tokenData['access_token']);

        $user = User::updateOrCreate(
            ['github_id' => (string) $githubUser['id']],
            [
                'username' => $githubUser['login'],
                'email' => $githubUser['email'] ?? null,
                'avatar_url' => $githubUser['avatar_url'] ?? null,
                'last_login_at' => now(),
                'is_active' => true,
            ]
        );

        $oauthState->delete();
        $tokens = $tokenService->issuePair($user);

        return response()->json([
            'status' => 'success',
            'message' => 'Authenticated',
            'user' => $user,
            'tokens' => $tokens,
        ]);
    }

    public function refresh(Request $request, TokenService $tokenService): JsonResponse
    {
        $request->validate(['refresh_token' => 'required|string']);
        $token = $tokenService->resolveRefreshToken($request->refresh_token);

        if (!$token) {
            return response()->json(['status' => 'error', 'message' => 'Invalid refresh token'], 401);
        }

        $user = $token->tokenable;
        $token->delete();
        $tokens = $tokenService->issuePair($user);

        return response()->json([
            'status' => 'success',
            'message' => 'Token refreshed',
            'tokens' => $tokens,
        ]);
    }

    public function logout(Request $request, TokenService $tokenService): JsonResponse
    {
        $request->validate(['refresh_token' => 'required|string']);
        $token = $tokenService->resolveRefreshToken($request->refresh_token);

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out',
        ]);
    }
}

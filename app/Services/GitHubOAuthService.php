<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitHubOAuthService
{
    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): array
    {
        $payload = [
            'client_id' => config('services.github.client_id'),
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'code_verifier' => $codeVerifier,
        ];

        if (config('services.github.client_secret')) {
            $payload['client_secret'] = config('services.github.client_secret');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->post('https://github.com/login/oauth/access_token', $payload);

        if ($response->failed()) {
            throw new RuntimeException('Unable to exchange GitHub OAuth code');
        }

        $data = $response->json();

        if (empty($data['access_token'])) {
            throw new RuntimeException('GitHub OAuth token response was invalid');
        }

        return $data;
    }

    public function fetchUser(string $githubAccessToken): array
    {
        $userResponse = Http::withToken($githubAccessToken)
            ->acceptJson()
            ->get('https://api.github.com/user');

        if ($userResponse->failed()) {
            throw new RuntimeException('Unable to fetch GitHub user profile');
        }

        $emailResponse = Http::withToken($githubAccessToken)
            ->acceptJson()
            ->get('https://api.github.com/user/emails');

        $emails = $emailResponse->ok() ? $emailResponse->json() : [];
        $primaryEmail = collect($emails)->firstWhere('primary', true);

        $user = $userResponse->json();
        $user['email'] = $user['email'] ?: ($primaryEmail['email'] ?? null);

        return $user;
    }
}

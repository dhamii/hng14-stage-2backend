<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\PersonalAccessToken;

class TokenService
{
    public const ACCESS_TTL_MINUTES = 3;
    public const REFRESH_TTL_MINUTES = 5;

    public function issuePair(User $user): array
    {
        $accessExpiresAt = CarbonImmutable::now()->addMinutes(self::ACCESS_TTL_MINUTES);
        $refreshExpiresAt = CarbonImmutable::now()->addMinutes(self::REFRESH_TTL_MINUTES);

        $accessToken = $user->createToken(
            'access-token',
            ['access'],
            $accessExpiresAt
        )->plainTextToken;

        $refreshToken = $user->createToken(
            'refresh-token',
            ['refresh'],
            $refreshExpiresAt
        )->plainTextToken;

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'access_expires_at' => $accessExpiresAt->toIso8601String(),
            'refresh_expires_at' => $refreshExpiresAt->toIso8601String(),
        ];
    }

    public function resolveRefreshToken(string $rawToken): ?PersonalAccessToken
    {
        $token = PersonalAccessToken::findToken($rawToken);

        if (!$token || !$token->can('refresh')) {
            return null;
        }

        if ($token->expires_at && $token->expires_at->isPast()) {
            $token->delete();
            return null;
        }

        return $token;
    }
}

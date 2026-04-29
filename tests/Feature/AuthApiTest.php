<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_rotates_refresh_token(): void
    {
        $user = User::factory()->create();
        $pair = app(\App\Services\TokenService::class)->issuePair($user);

        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $pair['refresh_token'],
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'message',
                'tokens' => ['access_token', 'refresh_token', 'token_type', 'access_expires_at', 'refresh_expires_at'],
            ]);
    }

    public function test_logout_invalidates_refresh_token(): void
    {
        $user = User::factory()->create();
        $pair = app(\App\Services\TokenService::class)->issuePair($user);

        $this->postJson('/api/auth/logout', ['refresh_token' => $pair['refresh_token']])
            ->assertOk();

        $this->postJson('/api/auth/refresh', ['refresh_token' => $pair['refresh_token']])
            ->assertStatus(401);
    }
}

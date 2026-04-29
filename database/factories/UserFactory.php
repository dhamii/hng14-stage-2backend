<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'github_id' => (string) fake()->unique()->numberBetween(10000, 999999),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'avatar_url' => fake()->imageUrl(),
            'role' => 'analyst',
            'is_active' => true,
            'last_login_at' => now(),
        ];
    }
}

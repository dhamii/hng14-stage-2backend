<?php

namespace Database\Factories;

use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->name(),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'gender_probability' => $this->faker->randomFloat(2, 0.5, 1.0),
            'age' => $this->faker->numberBetween(5, 90),
            'age_group' => $this->faker->randomElement(['child', 'teenager', 'adult', 'senior']),
            'country_id' => $this->faker->countryCode(),
            'country_name' => $this->faker->country(),
            'country_probability' => $this->faker->randomFloat(2, 0.5, 1.0),
        ];
    }
}

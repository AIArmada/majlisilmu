<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => $this->faker->randomElement(['google', 'facebook', 'github']),
            'provider_id' => (string) $this->faker->numberBetween(100000, 999999999),
            'avatar_url' => $this->faker->optional()->imageUrl(256, 256),
        ];
    }
}

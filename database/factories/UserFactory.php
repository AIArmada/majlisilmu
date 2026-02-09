<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hasPhone = fake()->boolean(70); // 70% chance of having a phone

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'phone' => $hasPhone ? fake()->unique()->phoneNumber() : null,
            'phone_verified_at' => $hasPhone ? fake()->boolean(80) ? now() : null : null,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user only has a phone number (no email).
     */
    public function phoneOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
            'email_verified_at' => null,
            'phone' => fake()->unique()->phoneNumber(),
            'phone_verified_at' => now(),
        ]);
    }

    /**
     * Indicate that the user only has an email (no phone).
     */
    public function emailOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => null,
            'phone_verified_at' => null,
        ]);
    }
}

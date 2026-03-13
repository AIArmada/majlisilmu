<?php

namespace Database\Factories;

use App\Models\Reference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reference>
 */
class ReferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'author' => $this->faker->name(),
            'type' => $this->faker->randomElement(['kitab', 'book', 'article']),
            'publication_year' => $this->faker->year(),
            'publisher' => $this->faker->company(),
            'description' => $this->faker->paragraph(),
            'is_canonical' => $this->faker->boolean(),
            'status' => 'verified',
            'is_active' => true,
        ];
    }

    /**
     * Create a pending (unverified) reference.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Create a verified reference.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'verified',
        ]);
    }
}

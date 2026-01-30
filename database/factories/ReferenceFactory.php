<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reference>
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
            'external_link' => $this->faker->url(),
            'is_canonical' => $this->faker->boolean(),
        ];
    }
}

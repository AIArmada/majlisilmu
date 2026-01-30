<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventType>
 */
class EventTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->words(2, true);
        return [
            'name' => ucfirst($name),
            'slug' => \Illuminate\Support\Str::slug($name).'-'.\Illuminate\Support\Str::random(5),
            'order_column' => 0,
            'is_active' => true,
        ];
    }
}

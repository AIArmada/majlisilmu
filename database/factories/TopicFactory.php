<?php

namespace Database\Factories;

use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Topic>
 */
class TopicFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(rand(2, 4), true);

        return [
            'parent_id' => null,
            'name' => ucwords($name),
            'slug' => Str::slug($name).'-'.Str::random(5),
            'is_official' => fake()->boolean(30),
            'order_column' => fake()->numberBetween(0, 100),
            'status' => 'verified',
            'is_active' => true,
        ];
    }

    /**
     * Mark the topic as official.
     */
    public function official(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_official' => true,
        ]);
    }

    /**
     * Mark the topic as community (non-official).
     */
    public function community(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_official' => false,
        ]);
    }

    /**
     * Set a parent topic.
     */
    public function childOf(Topic $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
        ]);
    }

    /**
     * Create as a root topic.
     */
    public function root(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => null,
        ]);
    }

    /**
     * Create with children.
     */
    public function withChildren(int $count = 3): static
    {
        return $this->afterCreating(function (Topic $topic) use ($count) {
            Topic::factory()
                ->count($count)
                ->childOf($topic)
                ->create();
        });
    }
}

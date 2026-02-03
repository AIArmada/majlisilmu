<?php

namespace Database\Factories;

use App\Enums\TagType;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(rand(1, 3), true);

        return [
            'name' => ['en' => ucwords($name), 'ms' => ucwords($name)],
            'slug' => ['en' => Str::slug($name), 'ms' => Str::slug($name)],
            'type' => fake()->randomElement(TagType::cases())->value,
            'order_column' => null,
        ];
    }

    /**
     * Set specific tag type.
     */
    public function ofType(TagType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type->value,
        ]);
    }

    /**
     * Create a domain tag.
     */
    public function domain(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TagType::Domain->value,
        ]);
    }

    /**
     * Create a discipline tag.
     */
    public function discipline(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TagType::Discipline->value,
        ]);
    }

    /**
     * Create a source tag.
     */
    public function source(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TagType::Source->value,
        ]);
    }

    /**
     * Create an issue tag.
     */
    public function issue(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TagType::Issue->value,
        ]);
    }
}

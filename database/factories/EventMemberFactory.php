<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventMember>
 */
class EventMemberFactory extends Factory
{
    protected $model = EventMember::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'role' => fake()->randomElement(['organizer', 'co-organizer', 'moderator', 'volunteer', 'member']),
            'joined_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    /**
     * Indicate that the member is an organizer.
     */
    public function organizer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'organizer',
        ]);
    }

    /**
     * Indicate that the member is a co-organizer.
     */
    public function coOrganizer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'co-organizer',
        ]);
    }

    /**
     * Indicate that the member is a moderator.
     */
    public function moderator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'moderator',
        ]);
    }

    /**
     * Indicate that the member is a volunteer.
     */
    public function volunteer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'volunteer',
        ]);
    }
}

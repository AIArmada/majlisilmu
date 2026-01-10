<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => fake()->boolean(80) ? User::factory() : null,
            'entity_type' => 'event',
            'entity_id' => Event::factory(),
            'action' => fake()->randomElement(['created', 'updated', 'approved', 'rejected']),
            'before' => fake()->boolean(50) ? ['status' => 'pending'] : null,
            'after' => ['status' => fake()->randomElement(['approved', 'rejected', 'pending'])],
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\DawahShareLink;
use App\Models\DawahShareShareEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DawahShareShareEvent>
 */
class DawahShareShareEventFactory extends Factory
{
    protected $model = DawahShareShareEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'link_id' => DawahShareLink::factory(),
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(['whatsapp', 'telegram', 'line', 'facebook', 'x', 'instagram', 'tiktok', 'email']),
            'event_type' => 'outbound_click',
            'metadata' => [],
            'occurred_at' => now(),
        ];
    }
}

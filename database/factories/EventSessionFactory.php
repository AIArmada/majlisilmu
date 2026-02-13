<?php

namespace Database\Factories;

use App\Enums\SessionStatus;
use App\Enums\TimingMode;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventSession>
 */
class EventSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('now', '+2 months');

        return [
            'event_id' => Event::factory(),
            'recurrence_rule_id' => null,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+2 hours'),
            'timezone' => 'Asia/Kuala_Lumpur',
            'status' => SessionStatus::Scheduled,
            'is_generated' => false,
            'capacity' => fake()->optional()->numberBetween(20, 300),
            'timing_mode' => TimingMode::Absolute,
            'prayer_reference' => null,
            'prayer_offset' => null,
            'prayer_display_text' => null,
        ];
    }
}

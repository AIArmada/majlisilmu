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
        $sessionTimezone = 'Asia/Kuala_Lumpur';
        $startsAtLocal = \Illuminate\Support\Carbon::instance(
            fake()->dateTimeBetween('now', '+2 months', $sessionTimezone)
        )->setTimezone($sessionTimezone);
        $endsAtLocal = $startsAtLocal->copy()->addHours(2);

        return [
            'event_id' => Event::factory(),
            'recurrence_rule_id' => null,
            'starts_at' => $startsAtLocal->copy()->utc(),
            'ends_at' => $endsAtLocal->copy()->utc(),
            'timezone' => $sessionTimezone,
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

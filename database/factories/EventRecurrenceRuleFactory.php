<?php

namespace Database\Factories;

use App\Enums\RecurrenceFrequency;
use App\Enums\ScheduleState;
use App\Enums\TimingMode;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventRecurrenceRule>
 */
class EventRecurrenceRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'frequency' => RecurrenceFrequency::Weekly,
            'interval' => 1,
            'by_weekdays' => [1, 3, 5],
            'by_month_day' => null,
            'start_date' => now()->toDateString(),
            'until_date' => now()->addMonths(3)->toDateString(),
            'occurrence_count' => null,
            'starts_time' => '20:00:00',
            'ends_time' => '22:00:00',
            'timezone' => 'Asia/Kuala_Lumpur',
            'timing_mode' => TimingMode::Absolute,
            'prayer_reference' => null,
            'prayer_offset' => null,
            'prayer_display_text' => null,
            'status' => ScheduleState::Active,
            'generated_until' => null,
        ];
    }
}

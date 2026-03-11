<?php

namespace Database\Factories;

use App\Enums\EventParticipantRole;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Speaker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventParticipant>
 */
class EventParticipantFactory extends Factory
{
    protected $model = EventParticipant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'speaker_id' => Speaker::factory(),
            'role' => EventParticipantRole::Speaker,
            'name' => null,
            'order_column' => 1,
            'is_public' => true,
            'notes' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ModerationReview>
 */
class ModerationReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'reviewer_id' => User::factory(),
            'decision' => fake()->randomElement(['approved', 'rejected', 'needs_changes']),
            'note' => fake()->optional()->sentence(),
            'reason_code' => fake()->optional()->randomElement([
                'donation_changed',
                'time_changed',
                'venue_changed',
                'speaker_changed',
                'details_incomplete',
            ]),
        ];
    }
}

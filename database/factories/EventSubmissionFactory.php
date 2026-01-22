<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventSubmission>
 */
class EventSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $submitter = User::factory();

        return [
            'event_id' => Event::factory(),
            'submitted_by' => $submitter,
            'submitter_name' => fake()->name(),
        ];
    }
}

<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventSubmission;
use App\Models\User;
use Illuminate\Database\Seeder;

class EventSubmissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (EventSubmission::query()->exists()) {
            return;
        }

        $users = User::query()->get();

        Event::query()->get()->each(function (Event $event) use ($users): void {
            $isPublic = random_int(0, 4) === 0;
            $submitter = $isPublic || $users->isEmpty() ? null : $users->random();

            $submission = EventSubmission::factory()->create([
                'event_id' => $event->id,
                'submitted_by' => $submitter?->id,
                'submitter_name' => $submitter ? null : fake()->name(),
            ]);

            if (! $submitter) {
                $submission->contacts()->create([
                    'type' => 'main',
                    'category' => 'email',
                    'value' => fake()->safeEmail(),
                ]);
            }
        });
    }
}

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

        EventSubmission::unsetEventDispatcher();

        try {
            \Illuminate\Support\Facades\DB::transaction(function (): void {
                $eventIds = Event::query()->pluck('id')->toArray();
                $userIds = User::query()->pluck('id')->toArray();

                $submissionsToInsert = [];
                $contactsToInsert = [];

                foreach ($eventIds as $eventId) {
                    $isPublic = random_int(0, 4) === 0;
                    $submitterId = (! $isPublic && ! empty($userIds)) ? $userIds[array_rand($userIds)] : null;

                    $submissionId = (string) \Illuminate\Support\Str::uuid();

                    $submissionsToInsert[] = array_merge(
                        EventSubmission::factory()->make([
                            'event_id' => $eventId,
                            'submitted_by' => $submitterId,
                            'submitter_name' => $submitterId ? null : fake()->name(),
                        ])->toArray(),
                        [
                            'id' => $submissionId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                    // Add contact for public submissions (no user)
                    if (! $submitterId) {
                        $contactsToInsert[] = [
                            'id' => (string) \Illuminate\Support\Str::uuid(),
                            'contactable_type' => 'event_submission',
                            'contactable_id' => $submissionId,
                            'type' => 'main',
                            'category' => 'email',
                            'value' => fake()->safeEmail(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                // Bulk insert submissions
                foreach (array_chunk($submissionsToInsert, 200) as $chunk) {
                    EventSubmission::insert($chunk);
                }

                // Bulk insert contacts
                if ($contactsToInsert !== []) {
                    foreach (array_chunk($contactsToInsert, 200) as $chunk) {
                        \Illuminate\Support\Facades\DB::table('contacts')->insert($chunk);
                    }
                }
            });
        } finally {
            EventSubmission::setEventDispatcher(app('events'));
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\ModerationReview;
use App\Models\User;
use Illuminate\Database\Seeder;

class ModerationReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (ModerationReview::query()->exists()) {
            return;
        }

        $reviewers = User::query()->get();

        if ($reviewers->isEmpty()) {
            return;
        }

        Event::query()
            ->whereIn('status', ['approved', 'rejected', 'pending'])
            ->get()
            ->each(function (Event $event) use ($reviewers): void {
                $decision = match ($event->status) {
                    'approved' => 'approved',
                    'rejected' => 'rejected',
                    default => 'needs_changes',
                };

                ModerationReview::factory()->create([
                    'event_id' => $event->id,
                    'reviewer_id' => $reviewers->random()->id,
                    'decision' => $decision,
                ]);
            });
    }
}

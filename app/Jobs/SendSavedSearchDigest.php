<?php

namespace App\Jobs;

use App\Enums\NotificationPreferenceKey;
use App\Models\SavedSearch;
use App\Notifications\SavedSearchDigestNotification;
use App\Services\EventSearchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SendSavedSearchDigest implements ShouldQueue
{
    use Queueable;

    /**
     * The frequency type: 'daily' or 'weekly'.
     */
    protected string $frequency;

    /**
     * Create a new job instance.
     */
    public function __construct(string $frequency = 'daily')
    {
        $this->frequency = $frequency;
    }

    /**
     * Execute the job.
     */
    public function handle(EventSearchService $searchService): void
    {
        $savedSearches = SavedSearch::query()
            ->where('notify', $this->frequency)
            ->with('user')
            ->cursor();

        $since = $this->frequency === 'weekly'
            ? Carbon::now()->subWeek()
            : Carbon::now()->subDay();

        $processed = 0;
        $notified = 0;

        foreach ($savedSearches as $savedSearch) {
            $processed++;

            if (! $savedSearch->user) {
                continue;
            }

            if (! $savedSearch->user->shouldReceiveNotificationFor(
                NotificationPreferenceKey::SavedSearchDigest->value,
                $this->frequency
            )) {
                continue;
            }

            try {
                $filters = $savedSearch->filters ?? [];

                // Search for new events matching this saved search
                if ($savedSearch->lat && $savedSearch->lng && $savedSearch->radius_km) {
                    $events = $searchService->searchNearby(
                        lat: $savedSearch->lat,
                        lng: $savedSearch->lng,
                        radiusKm: $savedSearch->radius_km,
                        filters: $filters,
                        perPage: 10
                    );
                } else {
                    $events = $searchService->search(
                        query: $savedSearch->query,
                        filters: $filters,
                        perPage: 10
                    );
                }

                // Filter to only events created since last digest
                $newEvents = collect($events->items())->filter(function ($event) use ($since) {
                    return $event->created_at >= $since || $event->updated_at >= $since;
                });

                if ($newEvents->isEmpty()) {
                    continue;
                }

                // Send notification
                $savedSearch->user->notify(
                    new SavedSearchDigestNotification($savedSearch, $newEvents)
                );

                $notified++;
            } catch (\Exception $e) {
                Log::error('Failed to send saved search digest', [
                    'saved_search_id' => $savedSearch->id,
                    'user_id' => $savedSearch->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Saved search digest completed', [
            'frequency' => $this->frequency,
            'processed' => $processed,
            'notified' => $notified,
        ]);
    }
}

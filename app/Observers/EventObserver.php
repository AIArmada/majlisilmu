<?php

namespace App\Observers;

use App\Enums\TimingMode;
use App\Models\Event;
use App\Services\PrayerTimeService;
use App\Support\Cache\PublicListingsCache;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

class EventObserver
{
    public function __construct(
        protected PrayerTimeService $prayerTimeService,
        protected PublicListingsCache $publicListingsCache
    ) {}

    public function creating(Event $event): void
    {
        $this->calculatePrayerRelativeTime($event);
    }

    /**
     * Handle the Event "updating" event.
     */
    public function updating(Event $event): void
    {
        // Only recalculate if timing-related fields changed
        if (
            $event->isDirty([
                'timing_mode',
                'prayer_reference',
                'prayer_offset',
                'venue_id',
            ])
        ) {
            $this->calculatePrayerRelativeTime($event);
        }
    }

    public function created(Event $event): void
    {
        $this->publicListingsCache->bustMajlisListing();
    }

    public function updated(Event $event): void
    {
        $this->publicListingsCache->bustMajlisListing();

        $changedFields = collect(array_keys($event->getChanges()))
            ->reject(static fn (string $field): bool => in_array($field, ['updated_at', 'saves_count', 'interests_count', 'going_count', 'registrations_count', 'published_at'], true))
            ->values()
            ->all();

        if ($changedFields === []) {
            return;
        }

        app(\App\Services\Notifications\EventNotificationService::class)
            ->notifyMaterialEventChange($event->fresh(), $changedFields);
    }

    public function deleted(Event $event): void
    {
        $this->publicListingsCache->bustMajlisListing();
    }

    /**
     * Calculate and set the starts_at time for prayer-relative events.
     */
    protected function calculatePrayerRelativeTime(Event $event): void
    {
        // Only process prayer-relative events
        if ($event->timing_mode !== TimingMode::PrayerRelative) {
            return;
        }

        // Ensure we have prayer reference and offset
        $prayerReference = $event->prayer_reference;
        $prayerOffset = $event->prayer_offset;

        if (! $prayerReference instanceof \App\Enums\PrayerReference || ! $prayerOffset instanceof \App\Enums\PrayerOffset) {
            Log::warning('Prayer-relative event missing prayer_reference or prayer_offset', [
                'event_id' => $event->id,
            ]);

            return;
        }

        // Get coordinates for prayer time calculation
        $coords = $this->getCoordinates($event);

        if ($coords === null) {
            Log::warning('Cannot calculate prayer time: no coordinates available', [
                'event_id' => $event->id,
            ]);

            return;
        }

        // Determine the event date in event timezone from the current starts_at payload first, then fallback to now.
        $eventTimezone = $event->timezone ?? 'Asia/Kuala_Lumpur';
        $startsAt = $event->starts_at;
        $rawStartsAt = $event->getAttributes()['starts_at'] ?? null;

        if ($startsAt instanceof \DateTimeInterface) {
            $eventDate = Carbon::instance($startsAt)->setTimezone($eventTimezone);
        } elseif (is_string($startsAt) && trim($startsAt) !== '') {
            $eventDate = Carbon::parse($startsAt, $eventTimezone);
        } elseif (is_string($rawStartsAt) && trim($rawStartsAt) !== '') {
            $eventDate = Carbon::parse($rawStartsAt, $eventTimezone);
        } else {
            $eventDate = Carbon::now($eventTimezone);
        }

        // Calculate the actual start time
        $calculatedTime = $this->prayerTimeService->calculateStartTime(
            $eventDate,
            $prayerReference,
            $prayerOffset,
            $coords['lat'],
            $coords['lng'],
            $eventTimezone
        );

        if ($calculatedTime instanceof CarbonInterface) {
            // Persist starts_at in UTC for storage consistency.
            $event->starts_at = \Illuminate\Support\Carbon::instance($calculatedTime)->utc();

            // Update display text if not already set
            if (empty($event->prayer_display_text)) {
                $event->prayer_display_text = $prayerOffset->displayText($prayerReference);
            }

            Log::info('Calculated prayer-relative start time', [
                'event_id' => $event->id,
                'prayer' => $prayerReference->value,
                'offset' => $prayerOffset->value,
                'calculated_time' => $calculatedTime->toIso8601String(),
            ]);
        } else {
            Log::warning('Failed to calculate prayer-relative start time', [
                'event_id' => $event->id,
                'prayer' => $prayerReference->value,
            ]);
        }
    }

    /**
     * Get coordinates for prayer time calculation.
     *
     * @return array{lat: float, lng: float}|null
     */
    protected function getCoordinates(Event $event): ?array
    {
        // Load venue if not loaded (with address)
        if ($event->venue_id && ! $event->relationLoaded('venue')) {
            $event->load('venue.address');
        }

        $venueAddress = $event->venue?->addressModel;

        if ($venueAddress instanceof \App\Models\Address
            && $venueAddress->lat !== null
            && $venueAddress->lng !== null) {
            return [
                'lat' => (float) $venueAddress->lat,
                'lng' => (float) $venueAddress->lng,
            ];
        }

        // Default to Kuala Lumpur coordinates if nothing else available
        return [
            'lat' => 3.1390,
            'lng' => 101.6869,
        ];
    }
}

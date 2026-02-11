<?php

namespace App\Observers;

use App\Enums\TimingMode;
use App\Models\Event;
use App\Services\PrayerTimeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EventObserver
{
    public function __construct(
        protected PrayerTimeService $prayerTimeService
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
        if (! $event->prayer_reference || ! $event->prayer_offset) {
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

        // Determine the event date
        $eventDate = $event->starts_at ?? Carbon::now($event->timezone ?? 'Asia/Kuala_Lumpur');

        // Calculate the actual start time
        $calculatedTime = $this->prayerTimeService->calculateStartTime(
            $eventDate,
            $event->prayer_reference,
            $event->prayer_offset,
            $coords['lat'],
            $coords['lng'],
            $event->timezone ?? 'Asia/Kuala_Lumpur'
        );

        if ($calculatedTime instanceof \Carbon\Carbon) {
            $event->starts_at = $calculatedTime;

            // Update display text if not already set
            if (empty($event->prayer_display_text)) {
                $event->prayer_display_text = $event->prayer_offset->displayText($event->prayer_reference);
            }

            Log::info('Calculated prayer-relative start time', [
                'event_id' => $event->id,
                'prayer' => $event->prayer_reference->value,
                'offset' => $event->prayer_offset->value,
                'calculated_time' => $calculatedTime->toIso8601String(),
            ]);
        } else {
            Log::warning('Failed to calculate prayer-relative start time', [
                'event_id' => $event->id,
                'prayer' => $event->prayer_reference->value,
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

        // Use venue coordinates via address
        if ($event->venue && $event->venue->address && $event->venue->address->lat && $event->venue->address->lng) {
            return [
                'lat' => (float) $event->venue->address->lat,
                'lng' => (float) $event->venue->address->lng,
            ];
        }

        // Default to Kuala Lumpur coordinates if nothing else available
        return [
            'lat' => 3.1390,
            'lng' => 101.6869,
        ];
    }
}

<?php

namespace App\View\Components;

use App\Models\Event;
use Illuminate\View\Component;
use Illuminate\View\View;

class EventJsonLd extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public Event $event
    ) {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.event-json-ld');
    }

    /**
     * Generate JSON-LD structured data for the event.
     * Schema.org Event type per documentation B9b.
     *
     * @return array<string, mixed>
     */
    public function jsonLd(): array
    {
        $event = $this->event;

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event->title,
            'description' => $event->description ?? '',
            'startDate' => $event->starts_at?->toIso8601String(),
            'endDate' => $event->ends_at?->toIso8601String(),
            'eventStatus' => $this->getEventStatus(),
            'eventAttendanceMode' => $this->getAttendanceMode(),
            'url' => route('events.show', $event->slug),
        ];

        // Location
        if ($event->venue) {
            $jsonLd['location'] = [
                '@type' => 'Place',
                'name' => $event->venue->name,
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $event->venue->address_line1 ?? '',
                    'addressLocality' => $event->venue->city ?? '',
                    'addressRegion' => $event->state?->name ?? '',
                    'addressCountry' => 'MY',
                ],
            ];

            $venueAddress = $event->venue->addressModel;

            if ($venueAddress?->lat && $venueAddress?->lng) {
                $jsonLd['location']['geo'] = [
                    '@type' => 'GeoCoordinates',
                    'latitude' => $venueAddress->lat,
                    'longitude' => $venueAddress->lng,
                ];
            }
        } elseif ($event->institution) {
            $jsonLd['location'] = [
                '@type' => 'Place',
                'name' => $event->institution->name,
            ];
        }

        // Organizer
        if ($event->institution) {
            $jsonLd['organizer'] = [
                '@type' => 'Organization',
                'name' => $event->institution->name,
                'url' => route('institutions.show', $event->institution->slug),
            ];
        }

        // Performers (Speakers)
        if ($event->speakers->isNotEmpty()) {
            $jsonLd['performer'] = $event->speakers->map(fn ($speaker) => [
                '@type' => 'Person',
                'name' => $speaker->name,
                'url' => route('speakers.show', $speaker->slug),
            ])->toArray();
        }

        // Image
        if ($event->image_url ?? false) {
            $jsonLd['image'] = $event->image_url;
        }

        // Offers (free admission)
        $jsonLd['offers'] = [
            '@type' => 'Offer',
            'price' => '0',
            'priceCurrency' => 'MYR',
            'availability' => $this->getAvailability(),
            'url' => route('events.show', $event->slug),
        ];

        // Keywords/About from tags
        if ($event->tags->isNotEmpty()) {
            $jsonLd['about'] = $event->tags->map(fn ($tag) => [
                '@type' => 'Thing',
                'name' => $tag->name,
            ])->toArray();
        }

        // In language
        $jsonLd['inLanguage'] = match ($event->language) {
            'malay' => 'ms',
            'english' => 'en',
            'arabic' => 'ar',
            'mixed' => ['ms', 'en'],
            default => 'ms',
        };

        return $jsonLd;
    }

    /**
     * Get Schema.org event status.
     */
    protected function getEventStatus(): string
    {
        if ($this->event->status?->equals(\App\States\EventStatus\Rejected::class)) { // Or cancelled if you have a Cancelled state
            return 'https://schema.org/EventCancelled';
        }

        return 'https://schema.org/EventScheduled';
    }

    /**
     * Get Schema.org attendance mode.
     */
    protected function getAttendanceMode(): string
    {
        if ($this->event->live_url) {
            return 'https://schema.org/MixedEventAttendanceMode';
        }

        return 'https://schema.org/OfflineEventAttendanceMode';
    }

    /**
     * Get Schema.org availability.
     */
    protected function getAvailability(): string
    {
        $event = $this->event;

        if ($event->status?->equals(\App\States\EventStatus\Rejected::class)) {
            return 'https://schema.org/Discontinued';
        }

        if ($event->settings?->registration_required && $event->settings?->capacity) {
            if ($event->registrations_count >= $event->settings->capacity) {
                return 'https://schema.org/SoldOut';
            }
        }

        return 'https://schema.org/InStock';
    }
}

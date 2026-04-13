<?php

namespace App\Data\Api\EventEngagement;

use App\Models\Event;
use App\Models\Speaker;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\LaravelData\Data;

class EventEngagementListItemData extends Data
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $institution
     * @param  array<string, mixed>|null  $venue
     * @param  list<array<string, mixed>>  $speakers
     * @param  array<string, mixed>|null  $pivot
     */
    public function __construct(
        public array $attributes,
        public ?array $institution,
        public ?array $venue,
        public array $speakers,
        public ?array $pivot,
    ) {}

    public static function fromModel(Event $event): self
    {
        $pivot = $event->relationLoaded('pivot') ? $event->getRelation('pivot') : null;

        return new self(
            attributes: $event->attributesToArray(),
            institution: $event->relationLoaded('institution') ? $event->institution?->toArray() : null,
            venue: $event->relationLoaded('venue') ? $event->venue?->toArray() : null,
            speakers: $event->relationLoaded('speakers')
                ? $event->speakers
                    ->map(fn (Speaker $speaker): array => $speaker->toArray())
                    ->values()
                    ->all()
                : [],
            pivot: $pivot instanceof Pivot ? $pivot->toArray() : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return array_merge($this->attributes, [
            'institution' => $this->institution,
            'venue' => $this->venue,
            'speakers' => $this->speakers,
            'pivot' => $this->pivot,
        ]);
    }
}

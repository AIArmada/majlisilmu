<?php

declare(strict_types=1);

namespace App\Support\ApiDocumentation\Schemas;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Contracts\Support\Arrayable;

#[SchemaName('InstitutionDetailPage')]
final readonly class InstitutionDetailPage implements Arrayable
{
    /**
     * @param  list<EventSummary>  $upcoming_events
     * @param  list<EventSummary>  $past_events
     */
    public function __construct(
        public Institution $institution,
        public array $upcoming_events,
        public int $upcoming_total,
        public array $past_events,
        public int $past_total,
    ) {}

    /**
     * @return array{institution: Institution, upcoming_events: list<EventSummary>, upcoming_total: int, past_events: list<EventSummary>, past_total: int}
     */
    public function toArray(): array
    {
        return [
            'institution' => $this->institution->toArray(),
            'upcoming_events' => array_map(static fn (EventSummary $event): array => $event->toArray(), $this->upcoming_events),
            'upcoming_total' => $this->upcoming_total,
            'past_events' => array_map(static fn (EventSummary $event): array => $event->toArray(), $this->past_events),
            'past_total' => $this->past_total,
        ];
    }
}

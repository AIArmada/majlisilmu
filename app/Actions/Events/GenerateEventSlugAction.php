<?php

namespace App\Actions\Events;

use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateEventSlugAction
{
    use AsAction;

    public function handle(
        string $title,
        CarbonInterface|string|null $date = null,
        ?string $timezone = null,
        ?string $ignoreEventId = null,
    ): string {
        $normalizedTitle = trim($title);
        $titleSlug = Str::slug($normalizedTitle);

        if ($titleSlug === '') {
            $titleSlug = 'event';
        }

        $dateSuffix = $this->dateSuffix($date, $timezone);
        $sequence = $this->nextSequenceForExactTitle($normalizedTitle, $dateSuffix, $ignoreEventId);

        do {
            $candidateParts = [$titleSlug];

            if ($sequence > 1) {
                $candidateParts[] = (string) $sequence;
            }

            if ($dateSuffix !== '') {
                $candidateParts[] = $dateSuffix;
            }

            $candidate = implode('-', $candidateParts);
            $sequence++;
        } while ($this->slugExists($candidate, $ignoreEventId));

        return $candidate;
    }

    public function forEvent(Event $event): string
    {
        return $this->handle(
            $event->title,
            $event->starts_at,
            is_string($event->timezone) ? $event->timezone : null,
            (string) $event->getKey(),
        );
    }

    private function nextSequenceForExactTitle(string $title, string $dateSuffix, ?string $ignoreEventId): int
    {
        $matchingEvents = Event::query()
            ->where('events.title', $title)
            ->get()
            ->filter(function (Event $event) use ($dateSuffix): bool {
                return $this->dateSuffixForEvent($event) === $dateSuffix;
            });

        if ($ignoreEventId !== null && $ignoreEventId !== '') {
            $existingSequence = $this->existingEventSequence($matchingEvents, $ignoreEventId);

            if ($existingSequence !== null) {
                return $existingSequence;
            }

            $matchingEvents = $matchingEvents
                ->reject(fn (Event $event): bool => (string) $event->getKey() === $ignoreEventId)
                ->values();
        }

        $matchingCount = $matchingEvents->count();

        return $matchingCount > 0 ? $matchingCount + 1 : 1;
    }

    /**
     * @param  Collection<int, Event>  $matchingEvents
     */
    private function existingEventSequence(Collection $matchingEvents, string $eventId): ?int
    {
        $orderedEvents = $matchingEvents
            ->sort(function (Event $left, Event $right): int {
                $leftCreatedAt = $left->created_at?->getTimestamp() ?? 0;
                $rightCreatedAt = $right->created_at?->getTimestamp() ?? 0;

                if ($leftCreatedAt !== $rightCreatedAt) {
                    return $leftCreatedAt <=> $rightCreatedAt;
                }

                return strcmp((string) $left->getKey(), (string) $right->getKey());
            })
            ->values();

        $existingIndex = $orderedEvents->search(
            fn (Event $event): bool => (string) $event->getKey() === $eventId,
        );

        if (! is_int($existingIndex)) {
            return null;
        }

        return $existingIndex + 1;
    }

    private function dateSuffix(CarbonInterface|string|null $date, ?string $timezone): string
    {
        $resolvedDate = $this->resolveDate($date, $timezone);

        return $resolvedDate?->format('j-n-y') ?? '';
    }

    private function dateSuffixForEvent(Event $event): string
    {
        if (! $event->starts_at instanceof CarbonInterface) {
            return '';
        }

        $timezone = is_string($event->timezone) && $event->timezone !== ''
            ? $event->timezone
            : (string) config('app.timezone', 'UTC');

        return $event->starts_at->copy()->timezone($timezone)->format('j-n-y');
    }

    private function resolveDate(CarbonInterface|string|null $date, ?string $timezone): ?Carbon
    {
        if ($date instanceof CarbonInterface) {
            return Carbon::instance($date)->setTimezone(
                $timezone ?: ($date->getTimezone()->getName() ?: (string) config('app.timezone', 'UTC')),
            );
        }

        if (! is_string($date) || trim($date) === '') {
            return null;
        }

        return Carbon::parse($date, $timezone ?: (string) config('app.timezone', 'UTC'));
    }

    private function slugExists(string $slug, ?string $ignoreEventId): bool
    {
        return Event::query()
            ->where('slug', $slug)
            ->when(
                $ignoreEventId !== null && $ignoreEventId !== '',
                fn ($query) => $query->where('events.id', '!=', $ignoreEventId),
            )
            ->exists();
    }
}

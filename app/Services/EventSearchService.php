<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Venue;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class EventSearchService
{
    /**
     * @return array<int|string, mixed>
     */
    protected function cardRelationships(): array
    {
        return [
            'media' => fn ($query) => $query
                ->where('collection_name', 'poster')
                ->ordered(),
            'institution.media' => fn ($query) => $query
                ->where('collection_name', 'logo')
                ->ordered(),
            'venue.address.state',
            'venue.address.district',
            'speakers.media' => fn ($query) => $query
                ->where('collection_name', 'avatar')
                ->ordered(),
        ];
    }

    /**
     * Search events using Typesense if available, otherwise fallback to database.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Event>
     */
    public function search(
        ?string $query = null,
        array $filters = [],
        int $perPage = 20,
        string $sort = 'time'
    ): LengthAwarePaginator {
        if (in_array($query, [null, '', '0'], true) && $filters === [] && $perPage === 12 && $sort === 'time') {
            return cache()->remember('default_events_search', 60, fn () => $this->performSearch($query, $filters, $perPage, $sort));
        }

        return $this->performSearch($query, $filters, $perPage, $sort);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Event>
     */
    protected function performSearch(
        ?string $query = null,
        array $filters = [],
        int $perPage = 20,
        string $sort = 'time'
    ): LengthAwarePaginator {
        if (config('scout.driver') === 'typesense') {
            try {
                return $this->searchWithTypesense($query, $filters, $perPage, $sort);
            } catch (\Exception $e) {
                Log::warning('Typesense search failed, falling back to database', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->searchWithDatabase($query, $filters, $perPage, $sort);
    }

    /**
     * Search with Typesense via Laravel Scout.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Event>
     */
    protected function searchWithTypesense(
        ?string $query,
        array $filters,
        int $perPage,
        string $sort
    ): LengthAwarePaginator {
        $search = Event::search($query ?? '')
            ->query(fn (Builder $builder) => $builder->with($this->cardRelationships()));

        $sortBy = match ($sort) {
            'relevance' => '_text_match:desc,starts_at:asc',
            'distance' => 'starts_at:asc',
            default => 'starts_at:asc',
        };

        $search->options([
            'filter_by' => implode(' && ', $this->buildTypesenseFilterParts($filters)),
            'sort_by' => $sortBy,
        ]);

        return $search->paginate($perPage);
    }

    /**
     * Search with database fallback.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Event>
     */
    protected function searchWithDatabase(
        ?string $query,
        array $filters,
        int $perPage,
        string $sort
    ): LengthAwarePaginator {
        $queryBuilder = $this->buildDatabaseQuery($query, $filters)
            ->with($this->cardRelationships());

        $operator = $this->databaseLikeOperator();
        $descriptionExpression = $this->searchableDescriptionExpression();

        if ($sort === 'relevance' && $query) {
            $queryBuilder->orderByRaw(
                "CASE
                    WHEN title {$operator} ? THEN 1
                    WHEN {$descriptionExpression} {$operator} ? THEN 2
                    ELSE 3
                END, starts_at ASC",
                ["%{$query}%", "%{$query}%"]
            );
        } else {
            $queryBuilder->orderBy('starts_at', 'asc');
        }

        return $queryBuilder->paginate($perPage);
    }

    /**
     * Geo search for "near me" functionality.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Event>
     */
    public function searchNearby(
        float $lat,
        float $lng,
        int $radiusKm = 50,
        array $filters = [],
        int $perPage = 20
    ): LengthAwarePaginator {
        if (config('scout.driver') === 'typesense') {
            try {
                $search = Event::search('');

                $search->query(fn (Builder $builder) => $builder->with($this->cardRelationships()));

                $filterBy = implode(' && ', [
                    "location:({$lat}, {$lng}, {$radiusKm} km)",
                    ...$this->buildTypesenseFilterParts($filters),
                ]);

                $search->options([
                    'filter_by' => $filterBy,
                    'sort_by' => "location({$lat}, {$lng}):asc",
                ]);

                return $search->paginate($perPage);
            } catch (\Exception $e) {
                Log::warning('Typesense geo search failed', ['error' => $e->getMessage()]);
            }
        }

        return $this->searchNearbyWithDatabase($lat, $lng, $radiusKm, $filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    protected function buildTypesenseFilterParts(array $filters): array
    {
        $timeScope = $this->normalizeTimeScope($filters['time_scope'] ?? null);

        $filterParts = [
            'is_active:=true',
            'status:[approved, pending]',
            'visibility:public',
        ];

        $startsAfterTimestamp = $this->startsAfterTimestamp($filters, $timeScope);

        if ($startsAfterTimestamp !== null) {
            $filterParts[] = 'starts_at:>='.$startsAfterTimestamp;
        }

        $startsBeforeTimestamp = $this->startsBeforeTimestamp($filters, $timeScope);

        if ($startsBeforeTimestamp !== null) {
            $filterParts[] = 'starts_at:<='.$startsBeforeTimestamp;
        }

        if (! empty($filters['state_id'])) {
            $filterParts[] = 'state_id:='.$filters['state_id'];
        }

        if (! empty($filters['district_id'])) {
            $filterParts[] = 'district_id:='.$filters['district_id'];
        }

        if (! empty($filters['subdistrict_id'])) {
            $filterParts[] = 'subdistrict_id:='.$filters['subdistrict_id'];
        }

        if (! empty($filters['language'])) {
            $filterParts[] = 'language:='.$filters['language'];
        }

        if (! empty($filters['event_type'])) {
            $eventTypes = $this->normalizeArrayFilter($filters['event_type']);

            if ($eventTypes !== []) {
                $filterParts[] = 'event_type:['.implode(',', $eventTypes).']';
            }
        }

        if (! empty($filters['genre'])) {
            $eventTypes = $this->normalizeArrayFilter($filters['genre']);

            if ($eventTypes !== []) {
                $filterParts[] = 'event_type:['.implode(',', $eventTypes).']';
            }
        }

        if (! empty($filters['gender'])) {
            $filterParts[] = 'gender:='.$filters['gender'];
        }

        if (! empty($filters['age_group'])) {
            $ageGroups = $this->normalizeArrayFilter($filters['age_group']);

            if ($ageGroups !== []) {
                $filterParts[] = 'age_group:['.implode(',', $ageGroups).']';
            }
        }

        if (! empty($filters['audience'])) {
            $ageGroups = $this->normalizeArrayFilter($filters['audience']);

            if ($ageGroups !== []) {
                $filterParts[] = 'audience:['.implode(',', $ageGroups).']';
            }
        }

        $childrenAllowed = $this->normalizeBooleanFilter($filters['children_allowed'] ?? null);

        if ($childrenAllowed !== null) {
            $filterParts[] = 'children_allowed:='.($childrenAllowed ? 'true' : 'false');
        }

        if (! empty($filters['institution_id'])) {
            $filterParts[] = 'institution_id:='.$filters['institution_id'];
        }

        if (! empty($filters['speaker_ids'])) {
            $speakerIds = $this->normalizeArrayFilter($filters['speaker_ids']);

            if ($speakerIds !== []) {
                $filterParts[] = 'speaker_ids:['.implode(',', $speakerIds).']';
            }
        }

        if (! empty($filters['topic_ids'])) {
            $topicIds = $this->normalizeArrayFilter($filters['topic_ids']);

            if ($topicIds !== []) {
                $filterParts[] = 'topic_ids:['.implode(',', $topicIds).']';
            }
        }

        return $filterParts;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Event>
     */
    protected function buildDatabaseQuery(?string $query, array $filters): Builder
    {
        $timeScope = $this->normalizeTimeScope($filters['time_scope'] ?? null);

        $queryBuilder = Event::query()
            ->where('is_active', true)
            ->whereIn('status', ['approved', 'pending'])
            ->where('visibility', 'public');

        $startsAfter = $this->startsAfterDateTime($filters, $timeScope);

        if ($startsAfter instanceof \Carbon\CarbonInterface) {
            $queryBuilder->where('starts_at', '>=', $startsAfter);
        }

        $startsBefore = $this->startsBeforeDateTime($filters, $timeScope);

        if ($startsBefore instanceof \Carbon\CarbonInterface) {
            $queryBuilder->where('starts_at', '<=', $startsBefore);
        }

        if (filled($query)) {
            $operator = strtolower($this->databaseLikeOperator());
            $descriptionExpression = $this->searchableDescriptionExpression();

            $queryBuilder->where(function (Builder $nestedQuery) use ($descriptionExpression, $query, $operator): void {
                $nestedQuery
                    ->where('title', $operator, "%{$query}%")
                    ->orWhereRaw("{$descriptionExpression} {$operator} ?", ["%{$query}%"]);
            });
        }

        if (! empty($filters['state_id'])) {
            $queryBuilder->whereHas('venue.address', function (Builder $addressQuery) use ($filters) {
                $addressQuery->where('state_id', $filters['state_id']);
            });
        }

        if (! empty($filters['district_id'])) {
            $queryBuilder->whereHas('venue.address', function (Builder $addressQuery) use ($filters) {
                $addressQuery->where('district_id', $filters['district_id']);
            });
        }

        if (! empty($filters['subdistrict_id'])) {
            $queryBuilder->whereHas('venue.address', function (Builder $addressQuery) use ($filters) {
                $addressQuery->where('subdistrict_id', $filters['subdistrict_id']);
            });
        }

        if (! empty($filters['language'])) {
            $queryBuilder->whereHas('languages', function (Builder $languageQuery) use ($filters) {
                $languageQuery->where('code', $filters['language']);
            });
        }

        $eventTypes = $this->normalizeArrayFilter($filters['event_type'] ?? null);

        if ($eventTypes !== []) {
            $queryBuilder->where(function (Builder $eventTypeQuery) use ($eventTypes) {
                foreach ($eventTypes as $eventType) {
                    $eventTypeQuery->orWhereJsonContains('event_type', $eventType);
                }
            });
        }

        $genres = $this->normalizeArrayFilter($filters['genre'] ?? null);

        if ($genres !== []) {
            $queryBuilder->where(function (Builder $genreQuery) use ($genres) {
                foreach ($genres as $genre) {
                    $genreQuery->orWhereJsonContains('event_type', $genre);
                }
            });
        }

        if (! empty($filters['gender'])) {
            $queryBuilder->where('gender', $filters['gender']);
        }

        $ageGroups = $this->normalizeArrayFilter($filters['age_group'] ?? null);

        if ($ageGroups !== []) {
            $queryBuilder->where(function (Builder $ageGroupQuery) use ($ageGroups) {
                foreach ($ageGroups as $ageGroup) {
                    $ageGroupQuery->orWhereJsonContains('age_group', $ageGroup);
                }
            });
        }

        $audiences = $this->normalizeArrayFilter($filters['audience'] ?? null);

        if ($audiences !== []) {
            $queryBuilder->where(function (Builder $audienceQuery) use ($audiences) {
                foreach ($audiences as $audience) {
                    $audienceQuery->orWhereJsonContains('age_group', $audience);
                }
            });
        }

        $childrenAllowed = $this->normalizeBooleanFilter($filters['children_allowed'] ?? null);

        if ($childrenAllowed !== null) {
            $queryBuilder->where('children_allowed', $childrenAllowed);
        }

        if (! empty($filters['institution_id'])) {
            $queryBuilder->where('institution_id', $filters['institution_id']);
        }

        $speakerIds = $this->normalizeArrayFilter($filters['speaker_ids'] ?? null);

        if ($speakerIds !== []) {
            $queryBuilder->whereHas('speakers', function (Builder $speakerQuery) use ($speakerIds) {
                $speakerQuery->whereIn('speakers.id', $speakerIds);
            });
        }

        $topicIds = $this->normalizeArrayFilter($filters['topic_ids'] ?? null);

        if ($topicIds !== []) {
            $queryBuilder->whereHas('tags', function (Builder $tagQuery) use ($topicIds) {
                $tagQuery
                    ->whereIn('tags.id', $topicIds)
                    ->whereIn('tags.type', ['discipline', 'issue'])
                    ->whereIn('tags.status', ['verified', 'pending']);
            });
        }

        return $queryBuilder;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Event>
     */
    protected function searchNearbyWithDatabase(
        float $lat,
        float $lng,
        int $radiusKm,
        array $filters,
        int $perPage
    ): LengthAwarePaginator {
        $distanceSql = '(6371 * acos(cos(radians(?)) * cos(radians(coalesce(venue_addresses.lat, event_addresses.lat))) * cos(radians(coalesce(venue_addresses.lng, event_addresses.lng)) - radians(?)) + sin(radians(?)) * sin(radians(coalesce(venue_addresses.lat, event_addresses.lat)))))';
        $venueMorphType = (new Venue)->getMorphClass();
        $eventMorphType = (new Event)->getMorphClass();

        $queryBuilder = $this->buildDatabaseQuery(null, $filters)
            ->leftJoin('addresses as venue_addresses', function ($join) use ($venueMorphType) {
                $join->on('venue_addresses.addressable_id', '=', 'events.venue_id')
                    ->where('venue_addresses.addressable_type', $venueMorphType);
            })
            ->leftJoin('addresses as event_addresses', function ($join) use ($eventMorphType) {
                $join->on('event_addresses.addressable_id', '=', 'events.id')
                    ->where('event_addresses.addressable_type', $eventMorphType);
            })
            ->whereRaw('coalesce(venue_addresses.lat, event_addresses.lat) is not null')
            ->whereRaw('coalesce(venue_addresses.lng, event_addresses.lng) is not null')
            ->select('events.*')
            ->selectRaw("{$distanceSql} as distance_km", [$lat, $lng, $lat])
            ->whereRaw("{$distanceSql} <= ?", [$lat, $lng, $lat, $radiusKm])
            ->with($this->cardRelationships())
            ->orderBy('distance_km', 'asc')
            ->orderBy('starts_at', 'asc');

        return $queryBuilder->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function startsAfterDateTime(array $filters, string $timeScope = 'upcoming'): ?CarbonInterface
    {
        $startsAfter = $this->parseDateFilter($filters['starts_after'] ?? null, false);

        if ($startsAfter instanceof \Carbon\CarbonInterface) {
            if ($timeScope === 'upcoming' && now()->greaterThan($startsAfter)) {
                return now();
            }

            return $startsAfter;
        }

        if ($timeScope === 'upcoming') {
            return now();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function startsBeforeDateTime(array $filters, string $timeScope = 'upcoming'): ?CarbonInterface
    {
        $startsBefore = $this->parseDateFilter($filters['starts_before'] ?? null, true);

        if ($startsBefore instanceof \Carbon\CarbonInterface) {
            return $startsBefore;
        }

        if ($timeScope === 'past') {
            return now();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function startsAfterTimestamp(array $filters, string $timeScope = 'upcoming'): ?int
    {
        return $this->startsAfterDateTime($filters, $timeScope)?->timestamp;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function startsBeforeTimestamp(array $filters, string $timeScope = 'upcoming'): ?int
    {
        $startsBefore = $this->startsBeforeDateTime($filters, $timeScope);

        return $startsBefore?->timestamp;
    }

    protected function normalizeTimeScope(mixed $value): string
    {
        if (! is_string($value)) {
            return 'upcoming';
        }

        return in_array($value, ['upcoming', 'past', 'all'], true) ? $value : 'upcoming';
    }

    protected function parseDateFilter(mixed $value, bool $endOfDay): ?CarbonInterface
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }

        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }

    /**
     * @return array<int, mixed>
     */
    protected function normalizeArrayFilter(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $values = is_array($value) ? $value : [$value];

        return array_values(array_filter($values, fn (mixed $item): bool => $item !== null && $item !== ''));
    }

    protected function normalizeBooleanFilter(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (in_array($value, [1, '1', 'true', 'on', 'yes'], true)) {
            return true;
        }

        if (in_array($value, [0, '0', 'false', 'off', 'no'], true)) {
            return false;
        }

        return null;
    }

    protected function databaseDriver(): string
    {
        /** @var \Illuminate\Database\Connection $connection */
        $connection = Event::query()->getConnection();

        return $connection->getDriverName();
    }

    private function databaseLikeOperator(): string
    {
        return $this->databaseDriver() === 'pgsql' ? 'ILIKE' : 'LIKE';
    }

    private function searchableDescriptionExpression(): string
    {
        return match ($this->databaseDriver()) {
            'pgsql' => "COALESCE(description::text, '')",
            'mysql', 'mariadb' => "COALESCE(CAST(description AS CHAR), '')",
            default => "COALESCE(CAST(description AS TEXT), '')",
        };
    }
}

<?php

namespace App\Services;

use App\Enums\EventPrayerTime;
use App\Enums\PrayerReference;
use App\Enums\TimingMode;
use App\Models\Event;
use App\Models\Venue;
use App\Support\Timezone\UserDateTimeFormatter;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            'institution.address.state',
            'institution.address.district',
            'institution.address.subdistrict',
            'venue.address.state',
            'venue.address.district',
            'venue.address.subdistrict',
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
        if ($this->requiresDatabaseFiltering($filters)) {
            return $this->searchWithDatabase($query, $filters, $perPage, $sort);
        }

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
        $normalizedQuery = $this->normalizeSearchQuery($query);

        if ($normalizedQuery === null) {
            $queryBuilder = $this->buildDatabaseQuery(null, $filters)
                ->with($this->cardRelationships());

            $this->applyDatabaseOrdering($queryBuilder, $sort, null);

            return $queryBuilder->paginate($perPage);
        }

        $directQuery = $this->buildDatabaseQuery(null, $filters)
            ->with($this->cardRelationships());
        $this->applyDirectSearch($directQuery, $normalizedQuery);
        $this->applyDatabaseOrdering($directQuery, $sort, $normalizedQuery);

        $directMatches = $directQuery->paginate($perPage);

        if ($directMatches->total() > 0 || mb_strlen($normalizedQuery) < 3) {
            return $directMatches;
        }

        return $this->fuzzySearchWithDatabase($filters, $normalizedQuery, $perPage);
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
        if ($this->requiresDatabaseFiltering($filters)) {
            return $this->searchNearbyWithDatabase($lat, $lng, $radiusKm, $filters, $perPage);
        }

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
            $filterParts[] = '(ends_at:>='.$startsAfterTimestamp.'||starts_at:>='.$startsAfterTimestamp.')';
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

        if (! empty($filters['event_format'])) {
            $eventFormats = $this->normalizeArrayFilter($filters['event_format']);

            if ($eventFormats !== []) {
                $filterParts[] = 'event_format:['.implode(',', $eventFormats).']';
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

        if (! empty($filters['venue_id'])) {
            $filterParts[] = 'venue_id:='.$filters['venue_id'];
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

        $queryBuilder = Event::query()->active();
        $table = $queryBuilder->getModel()->getTable();

        $startsAfter = $this->startsAfterDateTime($filters, $timeScope);

        if ($startsAfter instanceof \Carbon\CarbonInterface) {
            $queryBuilder->where(function (Builder $heldAfterQuery) use ($startsAfter, $table): void {
                $heldAfterQuery
                    ->where("{$table}.ends_at", '>=', $startsAfter)
                    ->orWhere(function (Builder $openEndedQuery) use ($startsAfter, $table): void {
                        $openEndedQuery
                            ->whereNull("{$table}.ends_at")
                            ->where("{$table}.starts_at", '>=', $startsAfter);
                    });
            });
        }

        $startsBefore = $this->startsBeforeDateTime($filters, $timeScope);

        if ($startsBefore instanceof \Carbon\CarbonInterface) {
            $queryBuilder->where("{$table}.starts_at", '<=', $startsBefore);
        }

        if (filled($query)) {
            $this->applyDirectSearch($queryBuilder, (string) $query);
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

        $languageCodes = $this->normalizeArrayFilter($filters['language_codes'] ?? null);

        if ($languageCodes !== []) {
            $queryBuilder->whereHas('languages', function (Builder $languageQuery) use ($languageCodes) {
                $languageQuery->whereIn('code', $languageCodes);
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

        $eventFormats = $this->normalizeArrayFilter($filters['event_format'] ?? null);

        if ($eventFormats !== []) {
            $queryBuilder->whereIn('event_format', $eventFormats);
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

        $isMuslimOnly = $this->normalizeBooleanFilter($filters['is_muslim_only'] ?? null);

        if ($isMuslimOnly !== null) {
            $queryBuilder->where('is_muslim_only', $isMuslimOnly);
        }

        if (! empty($filters['institution_id'])) {
            $queryBuilder->where('institution_id', $filters['institution_id']);
        }

        if (! empty($filters['venue_id'])) {
            $queryBuilder->where('venue_id', $filters['venue_id']);
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

        $timingMode = $this->normalizeTimingModeFilter($filters['timing_mode'] ?? null);
        $prayerTime = $this->normalizePrayerTimeFilter($filters['prayer_time'] ?? null);

        if ($prayerTime !== null && $timingMode !== TimingMode::Absolute->value) {
            $queryBuilder
                ->where('timing_mode', TimingMode::PrayerRelative->value)
                ->where(function (Builder $prayerQuery) use ($prayerTime): void {
                    $prayerQuery->where('prayer_display_text', $this->databaseLikeOperator(), "%{$prayerTime}%");

                    if (($prayerReference = $this->resolvePrayerReferenceFromFilter($prayerTime)) instanceof PrayerReference) {
                        $prayerQuery->orWhere('prayer_reference', $prayerReference->value);
                        }
                });
        }

        if ($timingMode !== null) {
            $queryBuilder->where('timing_mode', $timingMode);
        }

        $startsTimeFrom = $this->normalizeTimeFilter($filters['starts_time_from'] ?? null);
        $startsTimeUntil = $this->normalizeTimeFilter($filters['starts_time_until'] ?? null);

        if (
            $timingMode === TimingMode::Absolute->value
            && ($startsTimeFrom !== null || $startsTimeUntil !== null)
        ) {
            $this->applyAbsoluteTimeRangeFilter($queryBuilder, $startsTimeFrom, $startsTimeUntil);
        }

        $hasEventUrl = $this->normalizeBooleanFilter($filters['has_event_url'] ?? null);

        if ($hasEventUrl === true) {
            $queryBuilder->whereNotNull('event_url')->where('event_url', '!=', '');
        } elseif ($hasEventUrl === false) {
            $queryBuilder->where(function (Builder $eventUrlQuery): void {
                $eventUrlQuery->whereNull('event_url')->orWhere('event_url', '');
            });
        }

        $hasLiveUrl = $this->normalizeBooleanFilter($filters['has_live_url'] ?? null);

        if ($hasLiveUrl === true) {
            $queryBuilder->whereNotNull('live_url')->where('live_url', '!=', '');
        } elseif ($hasLiveUrl === false) {
            $queryBuilder->where(function (Builder $liveUrlQuery): void {
                $liveUrlQuery->whereNull('live_url')->orWhere('live_url', '');
            });
        }

        $hasEndTime = $this->normalizeBooleanFilter($filters['has_end_time'] ?? null);

        if ($hasEndTime === true) {
            $queryBuilder->whereNotNull('ends_at');
        } elseif ($hasEndTime === false) {
            $queryBuilder->whereNull('ends_at');
        }

        return $queryBuilder;
    }

    /**
     * @param  Builder<Event>  $queryBuilder
     */
    protected function applyDirectSearch(Builder $queryBuilder, string $search): void
    {
        $normalizedSearch = trim($search);

        if ($normalizedSearch === '') {
            return;
        }

        $operator = strtolower($this->databaseLikeOperator());
        $descriptionExpression = $this->searchableDescriptionExpression();
        $rawOperator = $this->databaseLikeOperator();
        $collapsedSearch = preg_replace('/\s+/u', ' ', $normalizedSearch) ?? '';
        $collapsedWildcardSearch = '%'.str_replace(' ', '%', $collapsedSearch).'%';

        /** @var list<string> $searchTokens */
        $searchTokens = array_values(array_filter(
            explode(' ', $collapsedSearch),
            static fn (string $token): bool => $token !== ''
        ));

        $queryBuilder->where(function (Builder $nestedQuery) use ($descriptionExpression, $normalizedSearch, $operator, $rawOperator, $collapsedWildcardSearch, $searchTokens): void {
            $nestedQuery
                ->where('title', $operator, "%{$normalizedSearch}%")
                ->orWhere('title', $operator, $collapsedWildcardSearch)
                ->orWhereRaw("{$descriptionExpression} {$rawOperator} ?", ["%{$normalizedSearch}%"])
                ->orWhereRaw("{$descriptionExpression} {$rawOperator} ?", [$collapsedWildcardSearch])
                ->orWhereHas('institution', function (Builder $institutionQuery) use ($normalizedSearch, $operator, $collapsedWildcardSearch): void {
                    $institutionQuery
                        ->where('name', $operator, "%{$normalizedSearch}%")
                        ->orWhere('name', $operator, $collapsedWildcardSearch);
                })
                ->orWhereHas('venue', function (Builder $venueQuery) use ($normalizedSearch, $operator, $collapsedWildcardSearch): void {
                    $venueQuery
                        ->where('name', $operator, "%{$normalizedSearch}%")
                        ->orWhere('name', $operator, $collapsedWildcardSearch);
                })
                ->orWhereHas('speakers', function (Builder $speakerQuery) use ($normalizedSearch, $operator, $collapsedWildcardSearch): void {
                    $speakerQuery
                        ->where('name', $operator, "%{$normalizedSearch}%")
                        ->orWhere('name', $operator, $collapsedWildcardSearch);
                });

            foreach ($searchTokens as $token) {
                if (mb_strlen($token) < 3) {
                    continue;
                }

                $nestedQuery
                    ->orWhere('title', $operator, "%{$token}%")
                    ->orWhereRaw("{$descriptionExpression} {$rawOperator} ?", ["%{$token}%"])
                    ->orWhereHas('institution', function (Builder $institutionQuery) use ($operator, $token): void {
                        $institutionQuery->where('name', $operator, "%{$token}%");
                    })
                    ->orWhereHas('venue', function (Builder $venueQuery) use ($operator, $token): void {
                        $venueQuery->where('name', $operator, "%{$token}%");
                    })
                    ->orWhereHas('speakers', function (Builder $speakerQuery) use ($operator, $token): void {
                        $speakerQuery->where('name', $operator, "%{$token}%");
                    });
            }
        });
    }

    /**
     * @param  Builder<Event>  $queryBuilder
     */
    protected function applyDatabaseOrdering(Builder $queryBuilder, string $sort, ?string $query): void
    {
        if ($sort === 'relevance' && is_string($query) && $query !== '') {
            $operator = $this->databaseLikeOperator();
            $descriptionExpression = $this->searchableDescriptionExpression();

            $queryBuilder->orderByRaw(
                "CASE
                    WHEN events.title {$operator} ? THEN 1
                    WHEN {$descriptionExpression} {$operator} ? THEN 2
                    WHEN EXISTS (
                        SELECT 1
                        FROM institutions
                        WHERE institutions.id = events.institution_id
                        AND institutions.name {$operator} ?
                    ) THEN 3
                    WHEN EXISTS (
                        SELECT 1
                        FROM venues
                        WHERE venues.id = events.venue_id
                        AND venues.name {$operator} ?
                    ) THEN 4
                    WHEN EXISTS (
                        SELECT 1
                        FROM event_speaker
                        INNER JOIN speakers ON speakers.id = event_speaker.speaker_id
                        WHERE event_speaker.event_id = events.id
                        AND speakers.name {$operator} ?
                    ) THEN 5
                    ELSE 6
                END, events.starts_at ASC",
                ["%{$query}%", "%{$query}%", "%{$query}%", "%{$query}%", "%{$query}%"]
            );

            return;
        }

        $queryBuilder->orderBy('starts_at', 'asc');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Event>
     */
    protected function fuzzySearchWithDatabase(array $filters, string $search, int $perPage): LengthAwarePaginator
    {
        $normalizedSearch = $this->normalizeForSimilarity($search);

        if ($normalizedSearch === '') {
            $queryBuilder = $this->buildDatabaseQuery(null, $filters)
                ->with($this->cardRelationships())
                ->orderBy('starts_at', 'asc');

            return $queryBuilder->paginate($perPage);
        }

        $rankedCandidates = $this->buildDatabaseQuery(null, $filters)
            ->with(['institution:id,name', 'venue:id,name', 'speakers:id,name'])
            ->select(['events.id', 'events.title', 'events.description', 'events.institution_id', 'events.venue_id'])
            ->get()
            ->map(function (Event $event) use ($normalizedSearch): array {
                return [
                    'id' => $event->id,
                    'score' => $this->eventSimilarityScore($normalizedSearch, $event),
                ];
            })
            ->filter(static fn (array $candidate): bool => $candidate['score'] >= 0.70)
            ->sortByDesc('score')
            ->values();

        $currentPage = max(1, (int) Paginator::resolveCurrentPage());
        $paginationMeta = [
            'path' => request()->url(),
            'query' => request()->query(),
        ];

        if ($rankedCandidates->isEmpty()) {
            return new Paginator(collect(), 0, $perPage, $currentPage, $paginationMeta);
        }

        /** @var list<string> $orderedIds */
        $orderedIds = $rankedCandidates->pluck('id')->all();
        $paginatedIds = array_slice($orderedIds, ($currentPage - 1) * $perPage, $perPage);

        if ($paginatedIds === []) {
            return new Paginator(collect(), count($orderedIds), $perPage, $currentPage, $paginationMeta);
        }

        $events = $this->buildDatabaseQuery(null, $filters)
            ->with($this->cardRelationships())
            ->whereIn('events.id', $paginatedIds)
            ->get()
            ->sortBy(static function (Event $event) use ($paginatedIds): int {
                $position = array_search($event->id, $paginatedIds, true);

                return is_int($position) ? $position : PHP_INT_MAX;
            })
            ->values();

        return new Paginator($events, count($orderedIds), $perPage, $currentPage, $paginationMeta);
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
        return UserDateTimeFormatter::parseUserDateToUtc($value, $endOfDay);
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

    protected function normalizePrayerTimeFilter(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = mb_strtolower(trim($value));

        if ($normalized === '') {
            return null;
        }

        $enum = EventPrayerTime::tryFrom($normalized);

        if ($enum instanceof EventPrayerTime) {
            return mb_strtolower($enum->getLabel());
        }

        return $normalized;
    }

    protected function normalizeTimingModeFilter(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return in_array($value, [TimingMode::Absolute->value, TimingMode::PrayerRelative->value], true)
            ? $value
            : null;
    }

    protected function normalizeTimeFilter(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        try {
            return now(UserDateTimeFormatter::resolveTimezone())
                ->setTimeFromTimeString($normalized)
                ->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  Builder<Event>  $queryBuilder
     */
    protected function applyAbsoluteTimeRangeFilter(
        Builder $queryBuilder,
        ?string $startsTimeFrom,
        ?string $startsTimeUntil
    ): void {
        if ($startsTimeFrom === null && $startsTimeUntil === null) {
            return;
        }

        $expression = $this->startsAtUserTimeSqlExpression($this->userUtcOffsetMinutes());

        if ($startsTimeFrom !== null && $startsTimeUntil !== null) {
            if ($startsTimeFrom <= $startsTimeUntil) {
                $queryBuilder
                    ->whereRaw("{$expression} >= ?", [$startsTimeFrom])
                    ->whereRaw("{$expression} <= ?", [$startsTimeUntil]);

                return;
            }

            $queryBuilder->where(function (Builder $timeQuery) use ($expression, $startsTimeFrom, $startsTimeUntil): void {
                $timeQuery
                    ->whereRaw("{$expression} >= ?", [$startsTimeFrom])
                    ->orWhereRaw("{$expression} <= ?", [$startsTimeUntil]);
            });

            return;
        }

        if ($startsTimeFrom !== null) {
            $queryBuilder->whereRaw("{$expression} >= ?", [$startsTimeFrom]);

            return;
        }

        $queryBuilder->whereRaw("{$expression} <= ?", [(string) $startsTimeUntil]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function requiresDatabaseFiltering(array $filters): bool
    {
        return $this->normalizePrayerTimeFilter($filters['prayer_time'] ?? null) !== null
            || $this->normalizeArrayFilter($filters['language_codes'] ?? null) !== []
            || $this->normalizeTimingModeFilter($filters['timing_mode'] ?? null) !== null
            || $this->normalizeTimeFilter($filters['starts_time_from'] ?? null) !== null
            || $this->normalizeTimeFilter($filters['starts_time_until'] ?? null) !== null
            || filled($filters['venue_id'] ?? null)
            || $this->normalizeBooleanFilter($filters['is_muslim_only'] ?? null) !== null
            || $this->normalizeBooleanFilter($filters['has_event_url'] ?? null) !== null
            || $this->normalizeBooleanFilter($filters['has_live_url'] ?? null) !== null
            || $this->normalizeBooleanFilter($filters['has_end_time'] ?? null) !== null;
    }

    protected function resolvePrayerReferenceFromFilter(string $prayerTime): ?PrayerReference
    {
        if (str_contains($prayerTime, 'jumaat') || str_contains($prayerTime, 'friday')) {
            return PrayerReference::FridayPrayer;
        }

        if (str_contains($prayerTime, 'maghrib')) {
            return PrayerReference::Maghrib;
        }

        if (str_contains($prayerTime, 'asar') || str_contains($prayerTime, 'asr')) {
            return PrayerReference::Asr;
        }

        if (str_contains($prayerTime, 'subuh') || str_contains($prayerTime, 'fajr')) {
            return PrayerReference::Fajr;
        }

        if (str_contains($prayerTime, 'zohor') || str_contains($prayerTime, 'zuhur') || str_contains($prayerTime, 'dhuhr')) {
            return PrayerReference::Dhuhr;
        }

        if (str_contains($prayerTime, 'isyak') || str_contains($prayerTime, 'isha')) {
            return PrayerReference::Isha;
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
            'pgsql' => "COALESCE(events.description::text, '')",
            'mysql', 'mariadb' => "COALESCE(CAST(events.description AS CHAR), '')",
            default => "COALESCE(CAST(events.description AS TEXT), '')",
        };
    }

    private function startsAtUserTimeSqlExpression(int $offsetMinutes): string
    {
        $safeOffsetMinutes = (int) $offsetMinutes;

        return match ($this->databaseDriver()) {
            'pgsql' => "to_char(events.starts_at + interval '{$safeOffsetMinutes} minutes', 'HH24:MI')",
            'mysql', 'mariadb' => "DATE_FORMAT(DATE_ADD(events.starts_at, INTERVAL {$safeOffsetMinutes} MINUTE), '%H:%i')",
            default => "strftime('%H:%M', datetime(events.starts_at, '{$safeOffsetMinutes} minutes'))",
        };
    }

    private function userUtcOffsetMinutes(): int
    {
        return now(UserDateTimeFormatter::resolveTimezone())->utcOffset();
    }

    private function normalizeSearchQuery(?string $query): ?string
    {
        if (! is_string($query)) {
            return null;
        }

        $normalizedQuery = trim($query);

        return $normalizedQuery === '' ? null : $normalizedQuery;
    }

    private function normalizeForSimilarity(string $value): string
    {
        return (string) Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9\s]+/u', ' ')
            ->replaceMatches('/\s+/u', ' ')
            ->trim();
    }

    private function similarityScore(string $search, string $candidate): float
    {
        if ($search === '' || $candidate === '') {
            return 0.0;
        }

        $distance = levenshtein($search, $candidate);
        $maxLength = max(mb_strlen($search), mb_strlen($candidate));
        $distanceScore = $maxLength > 0 ? 1 - ($distance / $maxLength) : 0.0;

        similar_text($search, $candidate, $similarityPercent);
        $similarityScore = $similarityPercent / 100;

        return max($distanceScore, $similarityScore);
    }

    private function eventSimilarityScore(string $normalizedSearch, Event $event): float
    {
        /** @var list<string> $candidates */
        $candidates = array_values(array_filter([
            $event->title,
            $event->description_text,
            $event->institution?->name,
            $event->venue?->name,
            $event->speakers->pluck('name')->implode(' '),
        ], static fn (mixed $value): bool => is_string($value) && $value !== ''));

        if ($candidates === []) {
            return 0.0;
        }

        $scoreCandidates = [];

        foreach ($candidates as $candidate) {
            $normalizedCandidate = $this->normalizeForSimilarity($candidate);

            if ($normalizedCandidate === '') {
                continue;
            }

            $scoreCandidates[] = $this->similarityScore($normalizedSearch, $normalizedCandidate);

            /** @var list<string> $candidateTokens */
            $candidateTokens = array_values(array_filter(
                explode(' ', $normalizedCandidate),
                static fn (string $token): bool => mb_strlen($token) >= 2
            ));

            foreach ($candidateTokens as $token) {
                $scoreCandidates[] = $this->similarityScore($normalizedSearch, $token);
            }
        }

        return $scoreCandidates === [] ? 0.0 : max($scoreCandidates);
    }
}

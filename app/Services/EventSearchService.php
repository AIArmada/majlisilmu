<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
     */
    public function search(
        ?string $query = null,
        array $filters = [],
        int $perPage = 20,
        string $sort = 'time'
    ): LengthAwarePaginator {
        // Cache the default search for a short time
        if (empty($query) && empty($filters) && $perPage === 12 && $sort === 'time') {
            return cache()->remember('default_events_search', 60, function () use ($query, $filters, $perPage, $sort) {
                return $this->performSearch($query, $filters, $perPage, $sort);
            });
        }

        return $this->performSearch($query, $filters, $perPage, $sort);
    }

    protected function performSearch(
        ?string $query = null,
        array $filters = [],
        int $perPage = 20,
        string $sort = 'time'
    ): LengthAwarePaginator {
        // Use Scout/Typesense when driver is typesense
        if (config('scout.driver') === 'typesense') {
            try {
                return $this->searchWithTypesense($query, $filters, $perPage, $sort);
            } catch (\Exception $e) {
                Log::warning('Typesense search failed, falling back to database', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback to database search
        return $this->searchWithDatabase($query, $filters, $perPage, $sort);
    }

    /**
     * Search with Typesense via Laravel Scout.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function searchWithTypesense(
        ?string $query,
        array $filters,
        int $perPage,
        string $sort
    ): LengthAwarePaginator {
        $search = Event::search($query ?? '')
            ->query(fn ($builder) => $builder->with($this->cardRelationships()));

        // Build filter_by string for Typesense
        $filterParts = [
            'status:[approved, pending]',
            'visibility:public',
            'starts_at:>='.now()->timestamp,
        ];

        // Apply filters
        if (! empty($filters['state_id'])) {
            $filterParts[] = 'state_id:='.$filters['state_id'];
        }

        if (! empty($filters['district_id'])) {
            $filterParts[] = 'district_id:='.$filters['district_id'];
        }

        if (! empty($filters['language'])) {
            $filterParts[] = 'language:='.$filters['language'];
        }

        if (! empty($filters['event_type'])) {
            $filterParts[] = 'event_type:='.$filters['event_type'];
        }

        if (! empty($filters['genre'])) {
            $filterParts[] = 'event_type:='.$filters['genre'];
        }

        if (! empty($filters['age_group'])) {
            $ageGroups = is_array($filters['age_group']) ? $filters['age_group'] : [$filters['age_group']];
            $filterParts[] = 'age_group:['.implode(',', $ageGroups).']';
        }

        if (! empty($filters['audience'])) {
            $ageGroups = is_array($filters['audience']) ? $filters['audience'] : [$filters['audience']];
            $filterParts[] = 'audience:['.implode(',', $ageGroups).']';
        }

        // Speaker filter
        if (! empty($filters['speaker_ids'])) {
            $speakerFilter = 'speaker_ids:['.implode(',', $filters['speaker_ids']).']';
            $filterParts[] = $speakerFilter;
        }

        // Sort
        $sortBy = match ($sort) {
            'relevance' => '_text_match:desc,starts_at:asc',
            'distance' => 'location:asc',
            default => 'starts_at:asc',
        };

        // Apply options
        $search->options([
            'filter_by' => implode(' && ', $filterParts),
            'sort_by' => $sortBy,
        ]);

        return $search->paginate($perPage);
    }

    /**
     * Search with database (fallback).
     *
     * @param  array<string, mixed>  $filters
     */
    protected function searchWithDatabase(
        ?string $query,
        array $filters,
        int $perPage,
        string $sort
    ): LengthAwarePaginator {
        $queryBuilder = Event::query()
            ->whereIn('status', ['approved', 'pending'])
            ->where('visibility', 'public')
            ->where('starts_at', '>=', now());

        // Text search
        if ($query) {
            $operator = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $queryBuilder->where(function ($q) use ($query, $operator) {
                $q->where('title', $operator, "%{$query}%")
                    ->orWhere('description', $operator, "%{$query}%");
            });
        }

        // Apply location filters through venue->address
        if (! empty($filters['state_id'])) {
            $queryBuilder->whereHas('venue.address', function ($q) use ($filters) {
                $q->where('state_id', $filters['state_id']);
            });
        }

        if (! empty($filters['district_id'])) {
            $queryBuilder->whereHas('venue.address', function ($q) use ($filters) {
                $q->where('district_id', $filters['district_id']);
            });
        }

        if (! empty($filters['language'])) {
            $queryBuilder->whereHas('languages', function ($q) use ($filters) {
                $q->where('code', $filters['language']);
            });
        }

        if (! empty($filters['event_type'])) {
            $eventTypes = is_array($filters['event_type']) ? $filters['event_type'] : [$filters['event_type']];
            $queryBuilder->where(function ($q) use ($eventTypes) {
                foreach ($eventTypes as $eventType) {
                    $q->orWhereJsonContains('event_type', $eventType);
                }
            });
        }

        if (! empty($filters['genre'])) {
            $genres = is_array($filters['genre']) ? $filters['genre'] : [$filters['genre']];
            $queryBuilder->where(function ($q) use ($genres) {
                foreach ($genres as $genre) {
                    $q->orWhereJsonContains('event_type', $genre);
                }
            });
        }

        if (! empty($filters['age_group'])) {
            $ageGroups = is_array($filters['age_group']) ? $filters['age_group'] : [$filters['age_group']];
            $ageGroups = array_values(array_filter($ageGroups));

            if ($ageGroups !== []) {
                $queryBuilder->where(function ($query) use ($ageGroups) {
                    foreach ($ageGroups as $ageGroup) {
                        $query->orWhereJsonContains('age_group', $ageGroup);
                    }
                });
            }
        }

        if (! empty($filters['audience'])) {
            $ageGroups = is_array($filters['audience']) ? $filters['audience'] : [$filters['audience']];
            $ageGroups = array_values(array_filter($ageGroups));

            if ($ageGroups !== []) {
                $queryBuilder->where(function ($query) use ($ageGroups) {
                    foreach ($ageGroups as $ageGroup) {
                        $query->orWhereJsonContains('age_group', $ageGroup);
                    }
                });
            }
        }

        if (! empty($filters['institution_id'])) {
            $queryBuilder->where('institution_id', $filters['institution_id']);
        }

        // Speaker filter
        if (! empty($filters['speaker_ids'])) {
            $queryBuilder->whereHas('speakers', function ($q) use ($filters) {
                $q->whereIn('speakers.id', $filters['speaker_ids']);
            });
        }

        // Eager load relationships used by the cards
        $queryBuilder->with($this->cardRelationships());

        // Sort
        $operator = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        if ($sort === 'relevance' && $query) {
            $queryBuilder->orderByRaw("
                CASE 
                    WHEN title $operator ? THEN 1
                    WHEN description $operator ? THEN 2
                    ELSE 3
                END, starts_at ASC
            ", ["%{$query}%", "%{$query}%"]);
        } else {
            $queryBuilder->orderBy('starts_at', 'asc');
        }

        return $queryBuilder->paginate($perPage);
    }

    /**
     * Geo search for "near me" functionality.
     *
     * @param  array<string, mixed>  $filters
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

                $search->query(fn ($builder) => $builder->with($this->cardRelationships()));

                // Geo filter
                $search->options([
                    'filter_by' => "location:({$lat}, {$lng}, {$radiusKm} km) && status:[approved, pending] && visibility:public",
                    'sort_by' => "location({$lat}, {$lng}):asc",
                ]);

                return $search->paginate($perPage);
            } catch (\Exception $e) {
                Log::warning('Typesense geo search failed', ['error' => $e->getMessage()]);
            }
        }

        // Fallback: simple search without distance (Postgres would need PostGIS)
        return $this->search(null, $filters, $perPage, 'time');
    }
}

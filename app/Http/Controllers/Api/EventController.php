<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EventController extends Controller
{
    /**
     * List events with filtering, sorting, and includes.
     *
     * Example API calls:
     * /api/v1/events?filter[status]=published
     * /api/v1/events?filter[event_format]=online
     * /api/v1/events?filter[starts_after]=2026-02-01
     * /api/v1/events?include=venue,speakers
     * /api/v1/events?sort=-starts_at
     * /api/v1/events?filter[search]=kuliah
     */
    public function index()
    {
        $events = QueryBuilder::for(Event::class)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('visibility'),
                AllowedFilter::exact('event_format'),
                AllowedFilter::exact('event_type_id'),
                AllowedFilter::exact('institution_id'),
                AllowedFilter::exact('venue_id'),

                // Date filters
                AllowedFilter::callback('starts_after', fn (Builder $query, $value) => $query->where('starts_at', '>=', Carbon::parse($value))
                ),
                AllowedFilter::callback('starts_before', fn (Builder $query, $value) => $query->where('starts_at', '<=', Carbon::parse($value))
                ),
                AllowedFilter::callback('ends_after', fn (Builder $query, $value) => $query->where('ends_at', '>=', Carbon::parse($value))
                ),
                AllowedFilter::callback('ends_before', fn (Builder $query, $value) => $query->where('ends_at', '<=', Carbon::parse($value))
                ),

                // Location filters
                AllowedFilter::callback('state_id', fn (Builder $query, $value) => $query->whereHas('venue.address', fn ($q) => $q->where('state_id', $value))
                ),
                AllowedFilter::callback('city_id', fn (Builder $query, $value) => $query->whereHas('venue.address', fn ($q) => $q->where('city_id', $value))
                ),

                // Relationship filters
                AllowedFilter::callback('speaker', fn (Builder $query, $value) => $query->whereHas('speakers', fn ($q) => $q->where('speaker_id', $value))
                ),
                AllowedFilter::callback('series', fn (Builder $query, $value) => $query->whereHas('series', fn ($q) => $q->where('series_id', $value))
                ),

                // Search filter
                AllowedFilter::callback('search', fn (Builder $query, $value) => $query->where(function ($q) use ($value) {
                    $q->where('title', 'ILIKE', "%{$value}%")
                        ->orWhere('description', 'ILIKE', "%{$value}%");
                })
                ),
            ])
            ->allowedIncludes([
                'venue',
                'venue.address',
                'institution',
                'speakers',
                'eventType',
                'series',
                'mediaLinks',
                'settings',
                'languages',
            ])
            ->allowedSorts([
                'title',
                'starts_at',
                'ends_at',
                'created_at',
                'updated_at',
                'views_count',
            ])
            ->defaultSort('-starts_at')
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->paginate(request('per_page', 20))
            ->appends(request()->query());

        return response()->json($events);
    }

    /**
     * Show a single event by ID or slug.
     */
    public function show(string $eventIdentifier)
    {
        $event = QueryBuilder::for(Event::class)
            ->allowedIncludes([
                'venue',
                'venue.address',
                'venue.address.state',
                'venue.address.city',
                'institution',
                'institution.address',
                'speakers',
                'eventType',
                'series',
                'mediaLinks',
                'settings',
                'languages',
                'donationChannels',
            ])
            ->where(function ($query) use ($eventIdentifier) {
                $query->where('id', $eventIdentifier)
                    ->orWhere('slug', $eventIdentifier);
            })
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->firstOrFail();

        return response()->json(['data' => $event]);
    }
}

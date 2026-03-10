<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Speaker;
use Illuminate\Database\Connection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SpeakerController extends Controller
{
    /**
     * List speakers with filtering and includes.
     */
    public function index(Request $request): JsonResponse
    {
        $speakers = QueryBuilder::for(Speaker::query())
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value): void {
                    $term = is_string($value) ? trim($value) : '';
                    if ($term === '') {
                        return;
                    }
                    /** @var Connection $connection */
                    $connection = $query->getConnection();
                    $operator = $connection->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
                    $query->where('name', $operator, "%{$term}%");
                }),
                AllowedFilter::exact('gender'),
            ])
            ->allowedIncludes([
                'institutions',
                'socialMedia',
            ])
            ->allowedSorts(['name', 'created_at'])
            ->defaultSort('name')
            ->where('is_active', true)
            ->paginate((int) $request->input('per_page', 20))
            ->appends($request->query());

        $speakers->getCollection()->each(function (Speaker $speaker): void {
            $speaker->avatar_url = $speaker->default_avatar_url;
            $speaker->append('formatted_name');
        });

        return response()->json($speakers);
    }

    /**
     * Show a single speaker by ID or slug.
     */
    public function show(Request $request, string $speakerIdentifier): JsonResponse
    {
        $speaker = QueryBuilder::for(Speaker::query())
            ->allowedIncludes([
                'institutions',
                'socialMedia',
                'contacts',
            ])
            ->where(function ($query) use ($speakerIdentifier): void {
                $query->where('id', $speakerIdentifier)
                    ->orWhere('slug', $speakerIdentifier);
            })
            ->where('is_active', true)
            ->firstOrFail();

        $speaker->avatar_url = $speaker->default_avatar_url;
        $speaker->cover_url = $speaker->getFirstMediaUrl('cover', 'banner') ?: null;
        $speaker->append('formatted_name');

        $speaker->loadCount([
            'events' => function ($query): void {
                $query->where('is_active', true)->whereIn('status', ['approved', 'pending']);
            },
        ]);

        return response()->json(['data' => $speaker]);
    }

    /**
     * List events for a speaker.
     */
    public function events(Request $request, string $speakerIdentifier): JsonResponse
    {
        $speaker = Speaker::query()
            ->where(function ($query) use ($speakerIdentifier): void {
                $query->where('id', $speakerIdentifier)
                    ->orWhere('slug', $speakerIdentifier);
            })
            ->where('is_active', true)
            ->firstOrFail();

        $events = QueryBuilder::for($speaker->events())
            ->allowedSorts(['starts_at', 'title', 'created_at'])
            ->defaultSort('-starts_at')
            ->where('is_active', true)
            ->whereIn('status', ['approved', 'pending', 'cancelled'])
            ->where('visibility', 'public')
            ->with(['institution', 'venue'])
            ->paginate((int) $request->input('per_page', 20))
            ->appends($request->query());

        $events->getCollection()->each(function ($event): void {
            $event->makeVisible('card_image_url');
        });

        return response()->json($events);
    }
}

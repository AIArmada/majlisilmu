<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Database\Connection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class InstitutionController extends Controller
{
    /**
     * List institutions with filtering and includes.
     */
    public function index(Request $request): JsonResponse
    {
        $institutions = QueryBuilder::for(Institution::query())
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::exact('type'),
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
            ])
            ->allowedIncludes([
                'address',
                'address.state',
                'address.district',
                'socialMedia',
            ])
            ->allowedSorts(['name', 'created_at'])
            ->defaultSort('name')
            ->where('is_active', true)
            ->paginate((int) $request->input('per_page', 20))
            ->appends($request->query());

        $institutions->getCollection()->transform(function (Institution $institution): Institution {
            $institution->logo_url = $institution->getFirstMediaUrl('logo', 'thumb') ?: null;

            return $institution;
        });

        return response()->json($institutions);
    }

    /**
     * Show a single institution by ID or slug.
     */
    public function show(Request $request, string $institutionIdentifier): JsonResponse
    {
        $institution = QueryBuilder::for(Institution::query())
            ->allowedIncludes([
                'address',
                'address.state',
                'address.district',
                'address.city',
                'socialMedia',
                'contacts',
            ])
            ->where(function ($query) use ($institutionIdentifier): void {
                $query->where('id', $institutionIdentifier)
                    ->orWhere('slug', $institutionIdentifier);
            })
            ->where('is_active', true)
            ->firstOrFail();

        $institution->logo_url = $institution->getFirstMediaUrl('logo', 'thumb') ?: null;
        $institution->cover_url = $institution->getFirstMediaUrl('cover', 'banner') ?: null;

        // Recent events count
        $institution->loadCount([
            'events' => function ($query): void {
                $query->where('is_active', true)->whereIn('status', ['approved', 'pending']);
            },
        ]);

        return response()->json(['data' => $institution]);
    }

    /**
     * List events belonging to an institution.
     */
    public function events(Request $request, string $institutionIdentifier): JsonResponse
    {
        $institution = Institution::query()
            ->where(function ($query) use ($institutionIdentifier): void {
                $query->where('id', $institutionIdentifier)
                    ->orWhere('slug', $institutionIdentifier);
            })
            ->where('is_active', true)
            ->firstOrFail();

        $events = QueryBuilder::for($institution->events())
            ->allowedSorts(['starts_at', 'title', 'created_at'])
            ->defaultSort('-starts_at')
            ->where('is_active', true)
            ->whereIn('status', ['approved', 'pending', 'cancelled'])
            ->where('visibility', 'public')
            ->with(['venue', 'speakers'])
            ->paginate((int) $request->input('per_page', 20))
            ->appends($request->query());

        $events->getCollection()->each(function ($event): void {
            // Access computed attribute to include it in the serialized output
            $event->makeVisible('card_image_url');
        });

        return response()->json($events);
    }
}

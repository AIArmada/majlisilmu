<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use App\Services\EventSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SavedSearchController extends Controller
{
    public function __construct(
        protected EventSearchService $searchService
    ) {}

    /**
     * List all saved searches for the authenticated user.
     */
    public function index(): JsonResponse
    {
        $savedSearches = SavedSearch::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $savedSearches,
        ]);
    }

    /**
     * Create a new saved search.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'query' => 'nullable|string|max:255',
            'filters' => 'nullable|array',
            'filters.state_id' => 'nullable|uuid|exists:states,id',
            'filters.district_id' => 'nullable|uuid|exists:districts,id',
            'filters.language' => ['nullable', Rule::in(['malay', 'english', 'arabic', 'mixed'])],
            'filters.genre' => ['nullable', Rule::in(['kuliah', 'ceramah', 'tazkirah', 'forum', 'halaqah', 'other'])],
            'filters.audience' => ['nullable', Rule::in(['general', 'men_only', 'women_only', 'youth', 'children', 'families'])],
            'filters.speaker_ids' => 'nullable|array',
            'filters.speaker_ids.*' => 'uuid|exists:speakers,id',
            'filters.topic_ids' => 'nullable|array',
            'filters.topic_ids.*' => 'uuid|exists:tags,id',
            'radius_km' => 'nullable|integer|min:1|max:500',
            'lat' => 'nullable|required_with:radius_km|numeric|between:-90,90',
            'lng' => 'nullable|required_with:radius_km|numeric|between:-180,180',
            'notify' => ['required', Rule::in(['off', 'instant', 'daily', 'weekly'])],
        ]);

        // Check limit (max 10 saved searches per user)
        $count = SavedSearch::where('user_id', Auth::id())->count();
        if ($count >= 10) {
            return response()->json([
                'message' => 'Maximum saved searches limit reached (10).',
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'Limit reached',
                ],
            ], 422);
        }

        $savedSearch = SavedSearch::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'query' => $validated['query'] ?? null,
            'filters' => $validated['filters'] ?? null,
            'radius_km' => $validated['radius_km'] ?? null,
            'lat' => $validated['lat'] ?? null,
            'lng' => $validated['lng'] ?? null,
            'notify' => $validated['notify'],
        ]);

        return response()->json([
            'message' => 'Saved search created.',
            'data' => $savedSearch,
        ], 201);
    }

    /**
     * Get a specific saved search.
     */
    public function show(SavedSearch $savedSearch): JsonResponse
    {
        $this->authorize('view', $savedSearch);

        return response()->json([
            'data' => $savedSearch,
        ]);
    }

    /**
     * Update a saved search.
     */
    public function update(Request $request, SavedSearch $savedSearch): JsonResponse
    {
        $this->authorize('update', $savedSearch);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'query' => 'nullable|string|max:255',
            'filters' => 'nullable|array',
            'radius_km' => 'nullable|integer|min:1|max:500',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'notify' => ['nullable', Rule::in(['off', 'instant', 'daily', 'weekly'])],
        ]);

        $savedSearch->update($validated);

        return response()->json([
            'message' => 'Saved search updated.',
            'data' => $savedSearch->fresh(),
        ]);
    }

    /**
     * Delete a saved search.
     */
    public function destroy(SavedSearch $savedSearch): \Illuminate\Http\Response
    {
        $this->authorize('delete', $savedSearch);

        $savedSearch->delete();

        return response()->noContent();
    }

    /**
     * Execute a saved search and return results.
     */
    public function execute(SavedSearch $savedSearch): JsonResponse
    {
        $this->authorize('view', $savedSearch);

        $filters = $savedSearch->filters ?? [];

        // Add geo if set
        if ($savedSearch->lat && $savedSearch->lng && $savedSearch->radius_km) {
            $events = $this->searchService->searchNearby(
                lat: $savedSearch->lat,
                lng: $savedSearch->lng,
                radiusKm: $savedSearch->radius_km,
                filters: $filters,
                perPage: 20
            );
        } else {
            $events = $this->searchService->search(
                query: $savedSearch->query,
                filters: $filters,
                perPage: 20
            );
        }

        return response()->json([
            'data' => $events->items(),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }
}

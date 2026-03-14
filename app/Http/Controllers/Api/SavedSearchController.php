<?php

namespace App\Http\Controllers\Api;

use App\Actions\SavedSearches\CreateSavedSearchAction;
use App\Actions\SavedSearches\ExecuteSavedSearchAction;
use App\Actions\SavedSearches\UpdateSavedSearchAction;
use App\Enums\EventKeyPersonRole;
use App\Exceptions\SavedSearchLimitReachedException;
use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SavedSearchController extends Controller
{
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
    public function store(Request $request, CreateSavedSearchAction $createSavedSearchAction): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'query' => 'nullable|string|max:255',
            'filters' => 'nullable|array',
            'filters.state_id' => 'nullable|integer|exists:states,id',
            'filters.district_id' => 'nullable|integer|exists:districts,id',
            'filters.subdistrict_id' => 'nullable|integer|exists:subdistricts,id',
            'filters.language' => ['nullable', Rule::in(['malay', 'english', 'arabic', 'mixed'])],
            'filters.genre' => ['nullable', Rule::in(['kuliah', 'ceramah', 'tazkirah', 'forum', 'halaqah', 'other'])],
            'filters.audience' => ['nullable', Rule::in(['general', 'men_only', 'women_only', 'youth', 'children', 'families'])],
            'filters.speaker_ids' => 'nullable|array',
            'filters.speaker_ids.*' => 'uuid|exists:speakers,id',
            'filters.key_person_roles' => 'nullable|array',
            'filters.key_person_roles.*' => ['string', Rule::in(array_keys(EventKeyPersonRole::nonSpeakerOptions()))],
            'filters.moderator_ids' => 'nullable|array',
            'filters.moderator_ids.*' => 'uuid|exists:speakers,id',
            'filters.imam_ids' => 'nullable|array',
            'filters.imam_ids.*' => 'uuid|exists:speakers,id',
            'filters.khatib_ids' => 'nullable|array',
            'filters.khatib_ids.*' => 'uuid|exists:speakers,id',
            'filters.bilal_ids' => 'nullable|array',
            'filters.bilal_ids.*' => 'uuid|exists:speakers,id',
            'filters.topic_ids' => 'nullable|array',
            'filters.topic_ids.*' => 'uuid|exists:tags,id',
            'filters.domain_tag_ids' => 'nullable|array',
            'filters.domain_tag_ids.*' => 'uuid|exists:tags,id',
            'filters.source_tag_ids' => 'nullable|array',
            'filters.source_tag_ids.*' => 'uuid|exists:tags,id',
            'filters.issue_tag_ids' => 'nullable|array',
            'filters.issue_tag_ids.*' => 'uuid|exists:tags,id',
            'filters.reference_ids' => 'nullable|array',
            'filters.reference_ids.*' => 'uuid|exists:references,id',
            'radius_km' => 'nullable|integer|min:1|max:1000',
            'lat' => 'nullable|required_with:radius_km|numeric|between:-90,90',
            'lng' => 'nullable|required_with:radius_km|numeric|between:-180,180',
            'notify' => ['required', Rule::in(['off', 'instant', 'daily', 'weekly'])],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        try {
            $savedSearch = $createSavedSearchAction->handle($user, $validated, $request);
        } catch (SavedSearchLimitReachedException $exception) {
            return response()->json([
                'message' => 'Maximum saved searches limit reached ('.$exception->maximum.').',
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'Limit reached',
                ],
            ], 422);
        }

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
    public function update(
        Request $request,
        SavedSearch $savedSearch,
        UpdateSavedSearchAction $updateSavedSearchAction,
    ): JsonResponse {
        $this->authorize('update', $savedSearch);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'query' => 'nullable|string|max:255',
            'filters' => 'nullable|array',
            'filters.state_id' => 'nullable|integer|exists:states,id',
            'filters.district_id' => 'nullable|integer|exists:districts,id',
            'filters.subdistrict_id' => 'nullable|integer|exists:subdistricts,id',
            'filters.language' => ['nullable', Rule::in(['malay', 'english', 'arabic', 'mixed'])],
            'filters.genre' => ['nullable', Rule::in(['kuliah', 'ceramah', 'tazkirah', 'forum', 'halaqah', 'other'])],
            'filters.audience' => ['nullable', Rule::in(['general', 'men_only', 'women_only', 'youth', 'children', 'families'])],
            'filters.speaker_ids' => 'nullable|array',
            'filters.speaker_ids.*' => 'uuid|exists:speakers,id',
            'filters.key_person_roles' => 'nullable|array',
            'filters.key_person_roles.*' => ['string', Rule::in(array_keys(EventKeyPersonRole::nonSpeakerOptions()))],
            'filters.moderator_ids' => 'nullable|array',
            'filters.moderator_ids.*' => 'uuid|exists:speakers,id',
            'filters.imam_ids' => 'nullable|array',
            'filters.imam_ids.*' => 'uuid|exists:speakers,id',
            'filters.khatib_ids' => 'nullable|array',
            'filters.khatib_ids.*' => 'uuid|exists:speakers,id',
            'filters.bilal_ids' => 'nullable|array',
            'filters.bilal_ids.*' => 'uuid|exists:speakers,id',
            'filters.topic_ids' => 'nullable|array',
            'filters.topic_ids.*' => 'uuid|exists:tags,id',
            'filters.domain_tag_ids' => 'nullable|array',
            'filters.domain_tag_ids.*' => 'uuid|exists:tags,id',
            'filters.source_tag_ids' => 'nullable|array',
            'filters.source_tag_ids.*' => 'uuid|exists:tags,id',
            'filters.issue_tag_ids' => 'nullable|array',
            'filters.issue_tag_ids.*' => 'uuid|exists:tags,id',
            'filters.reference_ids' => 'nullable|array',
            'filters.reference_ids.*' => 'uuid|exists:references,id',
            'radius_km' => 'nullable|integer|min:1|max:1000',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'notify' => ['sometimes', 'required', Rule::in(['off', 'instant', 'daily', 'weekly'])],
        ]);

        $savedSearch = $updateSavedSearchAction->handle($savedSearch, $validated);

        return response()->json([
            'message' => 'Saved search updated.',
            'data' => $savedSearch,
        ]);
    }

    /**
     * Delete a saved search.
     */
    public function destroy(SavedSearch $savedSearch): Response
    {
        $this->authorize('delete', $savedSearch);

        $savedSearch->delete();

        return response()->noContent();
    }

    /**
     * Execute a saved search and return results.
     */
    public function execute(SavedSearch $savedSearch, ExecuteSavedSearchAction $executeSavedSearchAction): JsonResponse
    {
        $this->authorize('view', $savedSearch);

        $events = $executeSavedSearchAction->handle($savedSearch, request());

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

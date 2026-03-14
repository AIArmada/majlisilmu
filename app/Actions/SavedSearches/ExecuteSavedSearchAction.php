<?php

namespace App\Actions\SavedSearches;

use App\Models\Event;
use App\Models\SavedSearch;
use App\Models\User;
use App\Services\EventSearchService;
use App\Services\Signals\ProductSignalsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class ExecuteSavedSearchAction
{
    use AsAction;

    public function __construct(
        private EventSearchService $searchService,
        private ProductSignalsService $productSignalsService,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Event>
     */
    public function handle(SavedSearch $savedSearch, ?Request $request = null): LengthAwarePaginator
    {
        $filters = is_array($savedSearch->filters) ? $savedSearch->filters : [];

        $events = ($savedSearch->lat !== null && $savedSearch->lng !== null && $savedSearch->radius_km !== null)
            ? $this->searchService->searchNearby(
                lat: $savedSearch->lat,
                lng: $savedSearch->lng,
                radiusKm: $savedSearch->radius_km,
                filters: $filters,
                perPage: 20,
            )
            : $this->searchService->search(
                query: $savedSearch->query,
                filters: $filters,
                perPage: 20,
            );

        $resolvedRequest = $request ?? request();
        $user = $resolvedRequest->user();

        $this->productSignalsService->recordSearchExecuted(
            user: $user instanceof User ? $user : null,
            request: $resolvedRequest,
            surface: 'saved_search.execute',
            query: $savedSearch->query,
            filters: array_merge($filters, array_filter([
                'lat' => $savedSearch->lat,
                'lng' => $savedSearch->lng,
                'radius_km' => $savedSearch->radius_km,
            ], static fn (mixed $value): bool => $value !== null)),
            resultCount: $events->total(),
            savedSearchId: (string) $savedSearch->getKey(),
        );

        return $events;
    }
}

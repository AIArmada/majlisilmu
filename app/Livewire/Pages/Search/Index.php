<?php

namespace App\Livewire\Pages\Search;

use App\Enums\EventStructure;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Services\EventSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Search Majlis, Speakers & Institutions')]
class Index extends Component
{
    #[Url]
    public ?string $search = null;

    #[Url]
    public ?string $lat = null;

    #[Url]
    public ?string $lng = null;

    #[Url]
    public int $radius_km = 15;

    public function clearSearch(): void
    {
        $this->search = null;
    }

    public function clearLocation(): void
    {
        $this->lat = null;
        $this->lng = null;
        $this->radius_km = 15;
    }

    #[Computed]
    public function hasSearchContext(): bool
    {
        return $this->normalizedSearch() !== null || $this->locationIsActive();
    }

    #[Computed]
    public function hasTypedSearch(): bool
    {
        return $this->normalizedSearch() !== null;
    }

    /**
     * @return array{items: Collection<int, Event>, total: int}
     */
    #[Computed]
    public function eventResults(): array
    {
        if (! $this->hasSearchContext()) {
            return [
                'items' => collect(),
                'total' => 0,
            ];
        }

        $search = $this->normalizedSearch();
        $searchService = app(EventSearchService::class);

        $paginator = $this->locationIsActive()
            ? $searchService->searchNearbyWithQuery(
                query: $search,
                lat: $this->normalizedCoordinate($this->lat) ?? 0.0,
                lng: $this->normalizedCoordinate($this->lng) ?? 0.0,
                radiusKm: $this->normalizedRadius(),
                perPage: 6,
            )
            : $searchService->search(
                query: $search,
                perPage: 6,
                sort: $search !== null ? 'relevance' : 'time',
            );

        /** @var Collection<int, Event> $items */
        $items = collect($paginator->items())->values();

        return [
            'items' => $items,
            'total' => $paginator->total(),
        ];
    }

    /**
     * @return array{items: Collection<int, Speaker>, total: int}
     */
    #[Computed]
    public function speakerResults(): array
    {
        $search = $this->normalizedSearch();

        if ($search === null) {
            return [
                'items' => collect(),
                'total' => 0,
            ];
        }

        $query = $this->speakerSearchQuery($search);
        $total = (clone $query)->count();

        /** @var Collection<int, Speaker> $items */
        $items = $query
            ->orderBy('name')
            ->limit(4)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * @return array{items: Collection<int, Institution>, total: int}
     */
    #[Computed]
    public function institutionResults(): array
    {
        $search = $this->normalizedSearch();

        if ($search === null) {
            return [
                'items' => collect(),
                'total' => 0,
            ];
        }

        $query = $this->institutionSearchQuery($search);
        $total = (clone $query)->count();

        /** @var Collection<int, Institution> $items */
        $items = $query
            ->orderBy('name')
            ->limit(4)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public function render(): View
    {
        return view('livewire.pages.search.index');
    }

    /**
     * @return Builder<Speaker>
     */
    private function speakerSearchQuery(string $search): Builder
    {
        $collapsedSearch = preg_replace('/\s+/u', ' ', trim($search)) ?? '';
        $collapsedWildcardSearch = '%'.str_replace(' ', '%', $collapsedSearch).'%';
        $searchTokens = array_values(array_filter(explode(' ', $collapsedSearch), static fn (string $token): bool => $token !== ''));
        $operator = $this->databaseLikeOperator();

        return Speaker::query()
            ->active()
            ->where('status', 'verified')
            ->withCount(['events' => function (Builder $query): void {
                $query
                    ->where('events.is_active', true)
                    ->whereIn('events.status', Event::PUBLIC_STATUSES)
                    ->where('events.visibility', EventVisibility::Public)
                    ->where('events.event_structure', '!=', EventStructure::ParentProgram->value)
                    ->where('events.starts_at', '>=', now());
            }])
            ->with('media')
            ->where(function (Builder $query) use ($collapsedSearch, $collapsedWildcardSearch, $searchTokens, $operator): void {
                $query
                    ->where('name', $operator, "%{$collapsedSearch}%")
                    ->orWhere('name', $operator, $collapsedWildcardSearch);

                foreach ($searchTokens as $token) {
                    if (mb_strlen($token) < 2) {
                        continue;
                    }

                    $query->orWhere('name', $operator, "%{$token}%");
                }
            });
    }

    /**
     * @return Builder<Institution>
     */
    private function institutionSearchQuery(string $search): Builder
    {
        return Institution::query()
            ->active()
            ->where('status', 'verified')
            ->withCount(['events' => function (Builder $query): void {
                $query
                    ->where('events.is_active', true)
                    ->whereIn('events.status', Event::PUBLIC_STATUSES)
                    ->where('events.visibility', EventVisibility::Public)
                    ->where('events.event_structure', '!=', EventStructure::ParentProgram->value)
                    ->where('events.starts_at', '>=', now());
            }])
            ->with(['address.state', 'address.district', 'address.subdistrict', 'media'])
            ->searchNameOrNickname($search);
    }

    private function normalizedSearch(): ?string
    {
        if (! is_string($this->search)) {
            return null;
        }

        $normalizedSearch = trim($this->search);

        return $normalizedSearch === '' ? null : $normalizedSearch;
    }

    private function locationIsActive(): bool
    {
        return $this->normalizedCoordinate($this->lat) !== null
            && $this->normalizedCoordinate($this->lng) !== null;
    }

    private function normalizedCoordinate(?string $value): ?float
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function normalizedRadius(): int
    {
        return max(1, min($this->radius_km, 100));
    }

    private function databaseLikeOperator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}

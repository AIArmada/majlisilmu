<?php

namespace App\Livewire\Pages\Events;

use App\Models\State;
use App\Services\EventSearchService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Upcoming Events')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public ?string $search = null;

    #[Url]
    public ?string $state_id = null;

    #[Url]
    public ?string $district_id = null;

    #[Url]
    public ?string $language = null;

    #[Url]
    public ?string $event_type = null;

    #[Url]
    public ?string $gender = null;

    #[Url]
    public array $age_group = [];

    #[Url]
    public ?bool $children_allowed = null;

    #[Url]
    public ?string $institution_id = null;

    #[Url]
    public ?array $topic_ids = [];

    #[Url]
    public ?array $speaker_ids = [];

    #[Url]
    public ?string $lat = null;

    #[Url]
    public ?string $lng = null;

    #[Url]
    public int $radius_km = 50;

    #[Url]
    public string $sort = 'time';

    public function mount(): void
    {
        //
    }

    #[Computed]
    public function states(): Collection
    {
        return cache()->remember('states_all', 3600, fn () => State::query()->orderBy('name')->get());
    }

    #[Computed]
    public function events(): LengthAwarePaginator
    {
        $filters = [
            'state_id' => $this->state_id,
            'district_id' => $this->district_id,
            'language' => $this->language,
            'event_type' => $this->event_type,
            'gender' => $this->gender,
            'age_group' => $this->age_group,
            'children_allowed' => $this->children_allowed,
            'institution_id' => $this->institution_id,
            'topic_ids' => $this->topic_ids,
            'speaker_ids' => $this->speaker_ids,
        ];

        $filters = array_filter($filters, function ($value) {
            if ($value === null || $value === '') {
                return false;
            }

            if (is_array($value)) {
                return $value !== [];
            }

            return true;
        });

        /** @var EventSearchService $searchService */
        $searchService = app(EventSearchService::class);

        if ($this->lat && $this->lng) {
            return $searchService->searchNearby(
                lat: (float) $this->lat,
                lng: (float) $this->lng,
                radiusKm: $this->radius_km,
                filters: $filters,
                perPage: 12
            );
        }

        return $searchService->search(
            query: $this->search,
            filters: $filters,
            perPage: 12,
            sort: $this->sort
        );
    }

    public function render()
    {
        return view('livewire.pages.events.index');
    }
}

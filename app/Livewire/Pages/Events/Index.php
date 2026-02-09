<?php

namespace App\Livewire\Pages\Events;

use App\Enums\TagType;
use App\Models\State;
use App\Models\Tag;
use App\Services\EventSearchService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
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
    public ?array $speaker_ids = [];

    #[Url]
    public array $topic_ids = [];

    #[Url]
    public ?string $starts_after = null;

    #[Url]
    public ?string $starts_before = null;

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
        return cache()->remember('states_my', 3600, fn () => State::query()
            ->where('country_code', 'MY')
            ->orderBy('name')
            ->get()
        );
    }

    #[Computed]
    public function topics(): Collection
    {
        return cache()->remember('events_topics_'.app()->getLocale(), 300, fn () => Tag::query()
            ->whereIn('type', [TagType::Discipline->value, TagType::Issue->value])
            ->whereIn('status', ['verified', 'pending'])
            ->ordered()
            ->get()
        );
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
            'speaker_ids' => $this->speaker_ids,
            'topic_ids' => $this->topic_ids,
            'starts_after' => $this->starts_after,
            'starts_before' => $this->starts_before,
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

    public function render(): View
    {
        return view('livewire.pages.events.index');
    }
}

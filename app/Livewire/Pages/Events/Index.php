<?php

namespace App\Livewire\Pages\Events;

use App\Enums\TagType;
use App\Models\District;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\State;
use App\Models\Subdistrict;
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

    /**
     * @var list<string>
     */
    private const array FILTER_PROPERTIES = [
        'search',
        'state_id',
        'district_id',
        'subdistrict_id',
        'language',
        'event_type',
        'gender',
        'age_group',
        'children_allowed',
        'institution_id',
        'speaker_ids',
        'topic_ids',
        'starts_after',
        'starts_before',
        'time_scope',
        'prayer_time',
        'lat',
        'lng',
        'radius_km',
        'sort',
    ];

    #[Url]
    public ?string $search = null;

    #[Url]
    public ?string $state_id = null;

    #[Url]
    public ?string $district_id = null;

    #[Url]
    public ?string $subdistrict_id = null;

    #[Url]
    public ?string $language = null;

    #[Url]
    public ?string $event_type = null;

    #[Url]
    public ?string $gender = null;

    /**
     * @var list<string>
     */
    #[Url]
    public array $age_group = [];

    #[Url]
    public ?bool $children_allowed = null;

    #[Url]
    public ?string $institution_id = null;

    /**
     * @var list<string>|null
     */
    #[Url]
    public ?array $speaker_ids = [];

    /**
     * @var list<string>
     */
    #[Url]
    public array $topic_ids = [];

    #[Url]
    public ?string $starts_after = null;

    #[Url]
    public ?string $starts_before = null;

    #[Url]
    public ?string $time_scope = null;

    #[Url]
    public ?string $prayer_time = null;

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

    public function updatedStateId(): void
    {
        $this->district_id = null;
        $this->subdistrict_id = null;
    }

    public function updatedDistrictId(): void
    {
        $this->subdistrict_id = null;
    }

    public function updated(string $property): void
    {
        if (in_array($property, self::FILTER_PROPERTIES, true)) {
            $this->resetPage();
        }
    }

    public function setLocation(float $lat, float $lng): void
    {
        $this->lat = (string) $lat;
        $this->lng = (string) $lng;
        $this->sort = 'distance';
    }

    public function clearAllFilters(): void
    {
        $this->search = null;
        $this->state_id = null;
        $this->district_id = null;
        $this->subdistrict_id = null;
        $this->language = null;
        $this->event_type = null;
        $this->gender = null;
        $this->age_group = [];
        $this->children_allowed = null;
        $this->institution_id = null;
        $this->speaker_ids = [];
        $this->topic_ids = [];
        $this->starts_after = null;
        $this->starts_before = null;
        $this->time_scope = null;
        $this->prayer_time = null;
        $this->lat = null;
        $this->lng = null;
        $this->radius_km = 50;
        $this->sort = 'time';
    }

    /**
     * @return Collection<int, State>
     */
    #[Computed]
    public function states(): Collection
    {
        return cache()->remember('states_my', 3600, fn () => State::query()
            ->where('country_code', 'MY')
            ->orderBy('name')
            ->get()
        );
    }

    /**
     * @return Collection<int, District>
     */
    #[Computed]
    public function districts(): Collection
    {
        if (! filled($this->state_id)) {
            return collect();
        }

        return District::query()
            ->where('state_id', $this->state_id)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Subdistrict>
     */
    #[Computed]
    public function subdistricts(): Collection
    {
        if (! filled($this->district_id)) {
            return collect();
        }

        return Subdistrict::query()
            ->where('district_id', $this->district_id)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Tag>
     */
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

    /**
     * @return Collection<int, Institution>
     */
    #[Computed]
    public function institutions(): Collection
    {
        return cache()->remember('events_institutions_'.app()->getLocale(), 300, fn () => Institution::query()
            ->whereIn('status', ['verified', 'pending'])
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name'])
        );
    }

    /**
     * @return Collection<int, Speaker>
     */
    #[Computed]
    public function speakers(): Collection
    {
        return cache()->remember('events_speakers_'.app()->getLocale(), 300, fn () => Speaker::query()
            ->whereIn('status', ['verified', 'pending'])
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(300)
            ->get(['id', 'name'])
        );
    }

    /**
     * @return LengthAwarePaginator<int, Event>
     */
    #[Computed]
    public function events(): LengthAwarePaginator
    {
        $filters = [
            'state_id' => $this->state_id,
            'district_id' => $this->district_id,
            'subdistrict_id' => $this->subdistrict_id,
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
            'time_scope' => in_array($this->time_scope, ['past', 'all'], true) ? $this->time_scope : null,
            'prayer_time' => $this->prayer_time,
        ];

        $filters = array_filter($filters, function (mixed $value): bool {
            if ($value === null || $value === '') {
                return false;
            }

            if (is_array($value)) {
                return $value !== [];
            }

            return true;
        });
        /** @var array<string, mixed> $filters */

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

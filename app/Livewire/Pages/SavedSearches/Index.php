<?php

namespace App\Livewire\Pages\SavedSearches;

use App\Models\SavedSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Saved Searches')]
class Index extends Component
{
    public string $name = '';

    public ?string $query = null;

    public string $notify = 'daily';

    public ?int $radius_km = null;

    public ?string $lat = null;

    public ?string $lng = null;

    /**
     * @var array<string, mixed>
     */
    public array $filters = [];

    public function mount(): void
    {
        $this->prefillFromRequest();
    }

    /**
     * @return Collection<int, SavedSearch>
     */
    #[Computed]
    public function savedSearches(): Collection
    {
        $user = auth()->user();

        return $user ? $user->savedSearches()->latest()->get() : collect();
    }

    public function save(): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'query' => ['nullable', 'string', 'max:255'],
            'notify' => ['required', Rule::in(['off', 'instant', 'daily', 'weekly'])],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:500'],
            'lat' => ['nullable', 'required_with:radius_km', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:radius_km', 'numeric', 'between:-180,180'],
        ]);

        if ($user->savedSearches()->count() >= 10) {
            $this->addError('name', __('Anda telah mencapai had maksimum 10 carian tersimpan.'));

            return;
        }

        $user->savedSearches()->create([
            'name' => $validated['name'],
            'query' => $validated['query'] ?? null,
            'filters' => $this->filters === [] ? null : $this->filters,
            'radius_km' => $validated['radius_km'] ?? null,
            'lat' => isset($validated['lat']) ? (float) $validated['lat'] : null,
            'lng' => isset($validated['lng']) ? (float) $validated['lng'] : null,
            'notify' => $validated['notify'],
        ]);

        $this->reset(['name', 'query', 'radius_km', 'lat', 'lng']);
        $this->notify = 'daily';
        $this->filters = [];

        session()->flash('status', __('Carian berjaya disimpan.'));
    }

    public function delete(string $savedSearchId): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        $savedSearch = $user->savedSearches()->where('id', $savedSearchId)->firstOrFail();
        $savedSearch->delete();

        session()->flash('status', __('Carian tersimpan telah dipadam.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toEventQueryParams(SavedSearch $savedSearch): array
    {
        $params = array_merge(
            ['search' => $savedSearch->query],
            is_array($savedSearch->filters) ? $savedSearch->filters : []
        );

        if ($savedSearch->lat !== null && $savedSearch->lng !== null) {
            $params = array_merge($params, [
                'lat' => $savedSearch->lat,
                'lng' => $savedSearch->lng,
                'radius_km' => $savedSearch->radius_km,
                'sort' => 'distance',
            ]);
        }

        return array_filter($params, filled(...));
    }

    public function render(): View
    {
        return view('livewire.pages.saved-searches.index');
    }

    protected function prefillFromRequest(): void
    {
        $this->query = request()->filled('search') ? (string) request('search') : null;
        $this->radius_km = request()->filled('radius_km') ? (int) request('radius_km') : null;
        $this->lat = request()->filled('lat') ? (string) request('lat') : null;
        $this->lng = request()->filled('lng') ? (string) request('lng') : null;

        $this->filters = $this->extractRequestFilters();

        if ($this->query || $this->filters !== [] || ($this->lat && $this->lng)) {
            $this->name = $this->suggestedName();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractRequestFilters(): array
    {
        $filterKeys = [
            'state_id',
            'district_id',
            'subdistrict_id',
            'language',
            'event_type',
            'event_format',
            'gender',
            'institution_id',
            'starts_after',
            'starts_before',
            'time_scope',
            'prayer_time',
            'timing_mode',
            'starts_time_from',
            'starts_time_until',
            'children_allowed',
            'is_muslim_only',
            'has_event_url',
            'has_live_url',
            'has_end_time',
        ];

        $filters = [];

        foreach ($filterKeys as $filterKey) {
            if (request()->filled($filterKey)) {
                $filters[$filterKey] = request()->input($filterKey);
            }
        }

        $ageGroups = array_values(array_filter((array) request()->input('age_group', [])));

        if ($ageGroups !== []) {
            $filters['age_group'] = $ageGroups;
        }

        $topicIds = array_values(array_filter((array) request()->input('topic_ids', [])));

        if ($topicIds !== []) {
            $filters['topic_ids'] = $topicIds;
        }

        $speakerIds = array_values(array_filter((array) request()->input('speaker_ids', [])));

        if ($speakerIds !== []) {
            $filters['speaker_ids'] = $speakerIds;
        }

        $eventType = array_values(array_filter((array) request()->input('event_type', [])));

        if ($eventType !== []) {
            $filters['event_type'] = $eventType;
        }

        $eventFormat = array_values(array_filter((array) request()->input('event_format', [])));

        if ($eventFormat !== []) {
            $filters['event_format'] = $eventFormat;
        }

        $languageCodes = array_values(array_filter((array) request()->input('language_codes', [])));

        if ($languageCodes !== []) {
            $filters['language_codes'] = $languageCodes;
        }

        return $filters;
    }

    protected function suggestedName(): string
    {
        if (filled($this->query)) {
            return __('Carian: :query', ['query' => Str::limit($this->query, 40)]);
        }

        if (! empty($this->filters['event_type'])) {
            $eventType = is_array($this->filters['event_type'])
                ? $this->filters['event_type'][0]
                : $this->filters['event_type'];

            return __('Jenis: :type', ['type' => Str::headline((string) $eventType)]);
        }

        if (! empty($this->filters['starts_after']) || ! empty($this->filters['starts_before'])) {
            return __('Tarikh Majlis Akan Datang');
        }

        return __('Carian Majlis Saya');
    }
}

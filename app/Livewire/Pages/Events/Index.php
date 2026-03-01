<?php

namespace App\Livewire\Pages\Events;

use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Enums\EventType;
use App\Enums\TagType;
use App\Enums\TimingMode;
use App\Models\District;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\State;
use App\Models\Subdistrict;
use App\Models\Tag;
use App\Services\EventSearchService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Nnjeim\World\Models\Language;

#[Layout('layouts.app')]
#[Title('Upcoming Events')]
class Index extends Component implements HasForms
{
    use InteractsWithForms;
    use WithPagination;

    #[Url]
    public ?string $search = null;

    #[Url]
    public ?string $state_id = null;

    #[Url]
    public ?string $district_id = null;

    #[Url]
    public ?string $subdistrict_id = null;

    // Legacy single-language query support.
    #[Url]
    public ?string $language = null;

    /**
     * @var list<string>
     */
    #[Url]
    public array $language_codes = [];

    /**
     * @var list<string>|string|null
     */
    #[Url]
    public array|string|null $event_type = [];

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
    public ?bool $is_muslim_only = null;

    #[Url]
    public ?string $institution_id = null;

    /**
     * @var list<string>
     */
    #[Url]
    public array $speaker_ids = [];

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
    public ?string $timing_mode = null;

    /**
     * @var list<string>|string|null
     */
    #[Url]
    public array|string|null $event_format = [];

    #[Url]
    public ?bool $has_event_url = null;

    #[Url]
    public ?bool $has_live_url = null;

    #[Url]
    public ?bool $has_end_time = null;

    #[Url]
    public ?string $lat = null;

    #[Url]
    public ?string $lng = null;

    #[Url]
    public int $radius_km = 50;

    #[Url]
    public string $sort = 'time';

    /**
     * @var array<string, mixed>
     */
    public array $filterData = [];

    public function mount(): void
    {
        $normalized = $this->normalizedUrlState();

        $this->fillPublicPropertiesFromFilters($normalized);
        $this->filterData = $normalized;
        $this->getForm('form')?->fill($normalized);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('filterData')
            ->schema([
                Section::make(__('Advanced Filters'))
                    ->extraAttributes(['class' => 'mi-advanced-filter-section'])
                    ->description(__('Refine events using format, timing, audience, speakers, and links.'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Section::make(__('Location'))
                            ->extraAttributes(['class' => 'mi-advanced-filter-group'])
                            ->description(__('Narrow events by geography and institution.'))
                            ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                            ->schema([
                                Select::make('state_id')
                                    ->label(__('State'))
                                    ->placeholder(__('All States'))
                                    ->options(fn (): array => $this->states()
                                        ->pluck('name', 'id')
                                        ->mapWithKeys(fn (string $name, mixed $id): array => [(string) $id => $name])
                                        ->all()
                                    )
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('district_id', null);
                                        $set('subdistrict_id', null);
                                    }),

                                Select::make('district_id')
                                    ->label(__('District'))
                                    ->placeholder(__('All Districts'))
                                    ->options(function (Get $get): array {
                                        $stateId = $get('state_id');

                                        if (! filled($stateId)) {
                                            return [];
                                        }

                                        return District::query()
                                            ->where('state_id', $stateId)
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                            ->mapWithKeys(fn (string $name, mixed $id): array => [(string) $id => $name])
                                            ->all();
                                    })
                                    ->disabled(fn (Get $get): bool => ! filled($get('state_id')))
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('subdistrict_id', null)),

                                Select::make('subdistrict_id')
                                    ->label(__('Subdistrict'))
                                    ->placeholder(__('All Subdistricts'))
                                    ->options(function (Get $get): array {
                                        $districtId = $get('district_id');

                                        if (! filled($districtId)) {
                                            return [];
                                        }

                                        return Subdistrict::query()
                                            ->where('district_id', $districtId)
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                            ->mapWithKeys(fn (string $name, mixed $id): array => [(string) $id => $name])
                                            ->all();
                                    })
                                    ->disabled(fn (Get $get): bool => ! filled($get('district_id')))
                                    ->searchable()
                                    ->live(),

                                Select::make('institution_id')
                                    ->label(__('Institution'))
                                    ->placeholder(__('Any Institution'))
                                    ->searchable()
                                    ->options(fn (): array => $this->institutions()
                                        ->pluck('name', 'id')
                                        ->all()
                                    )
                                    ->live(),
                            ]),

                        Section::make(__('People & Content'))
                            ->extraAttributes(['class' => 'mi-advanced-filter-group'])
                            ->description(__('Filter by speakers, topics, type, format, and language.'))
                            ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                            ->schema([
                                Select::make('speaker_ids')
                                    ->label(__('Speaker'))
                                    ->placeholder(__('Any Speaker'))
                                    ->searchable()
                                    ->multiple()
                                    ->options(fn (): array => $this->speakers()
                                        ->pluck('name', 'id')
                                        ->all()
                                    )
                                    ->live(),

                                Select::make('topic_ids')
                                    ->label(__('Topic'))
                                    ->placeholder(__('Any Topic'))
                                    ->searchable()
                                    ->multiple()
                                    ->options(fn (): array => $this->topics()
                                        ->pluck('name', 'id')
                                        ->all()
                                    )
                                    ->live(),

                                Select::make('event_type')
                                    ->label(__('Event Type'))
                                    ->placeholder(__('Any Type'))
                                    ->searchable()
                                    ->multiple()
                                    ->options(function (): array {
                                        return collect(EventType::cases())
                                            ->mapToGroups(fn (EventType $type): array => [
                                                $type->getGroup() => [$type->value => $type->getLabel()],
                                            ])
                                            ->map(fn (Collection $group): array => $group->collapse()->all())
                                            ->toArray();
                                    })
                                    ->live(),

                                Select::make('event_format')
                                    ->label(__('Format Majlis'))
                                    ->placeholder(__('Any Format'))
                                    ->options(collect(EventFormat::cases())
                                        ->mapWithKeys(fn (EventFormat $format): array => [$format->value => $format->getLabel()])
                                        ->all()
                                    )
                                    ->multiple()
                                    ->live(),

                                Select::make('gender')
                                    ->label(__('Gender'))
                                    ->placeholder(__('Any'))
                                    ->options(collect(EventGenderRestriction::cases())
                                        ->mapWithKeys(fn (EventGenderRestriction $gender): array => [$gender->value => $gender->getLabel()])
                                        ->all()
                                    )
                                    ->live(),

                                Select::make('age_group')
                                    ->label(__('Age Group'))
                                    ->placeholder(__('Any Age Group'))
                                    ->options(collect(EventAgeGroup::cases())
                                        ->mapWithKeys(fn (EventAgeGroup $age): array => [$age->value => $age->getLabel()])
                                        ->all()
                                    )
                                    ->multiple()
                                    ->live()
                                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                                        $ageGroups = $this->normalizeStringArray($state);

                                        if (
                                            in_array(EventAgeGroup::Children->value, $ageGroups, true)
                                            || in_array(EventAgeGroup::AllAges->value, $ageGroups, true)
                                        ) {
                                            $set('children_allowed', true);
                                        }
                                    }),

                                Select::make('language_codes')
                                    ->label(__('Bahasa'))
                                    ->helperText(__('Bahasa yang digunakan dalam majlis.'))
                                    ->placeholder(__('Any Language'))
                                    ->multiple()
                                    ->searchable()
                                    ->options(fn (): array => $this->languageOptions())
                                    ->live(),
                            ]),

                        Section::make(__('Audience'))
                            ->extraAttributes(['class' => 'mi-advanced-filter-group'])
                            ->description(__('Set attendance restrictions and age targeting.'))
                            ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                            ->schema([
                                Select::make('children_allowed')
                                    ->label(__('Children Allowed'))
                                    ->placeholder(__('Any'))
                                    ->options([
                                        '1' => __('Yes'),
                                        '0' => __('No'),
                                    ])
                                    ->live(),

                                Select::make('is_muslim_only')
                                    ->label(__('Muslim Only'))
                                    ->placeholder(__('Any'))
                                    ->options([
                                        '1' => __('Yes'),
                                        '0' => __('No'),
                                    ])
                                    ->live(),
                            ]),

                        Section::make(__('Time & Date'))
                            ->extraAttributes(['class' => 'mi-advanced-filter-group'])
                            ->description(__('Control when events happened and how time is interpreted.'))
                            ->columns(['default' => 1, 'md' => 2, 'xl' => 5])
                            ->schema([
                                DatePicker::make('starts_after')
                                    ->label(__('Start Date'))
                                    ->native()
                                    ->maxDate(fn (Get $get): ?string => $get('starts_before'))
                                    ->live(),

                                DatePicker::make('starts_before')
                                    ->label(__('End Date'))
                                    ->native()
                                    ->minDate(fn (Get $get): ?string => $get('starts_after'))
                                    ->live(),

                                Select::make('time_scope')
                                    ->label(__('Time Scope'))
                                    ->options([
                                        'upcoming' => __('Upcoming'),
                                        'past' => __('Past'),
                                        'all' => __('All Time'),
                                    ])
                                    ->default('upcoming')
                                    ->live(),

                                Select::make('timing_mode')
                                    ->label(__('Timing Mode'))
                                    ->placeholder(__('Any'))
                                    ->options([
                                        TimingMode::Absolute->value => TimingMode::Absolute->label(),
                                        TimingMode::PrayerRelative->value => TimingMode::PrayerRelative->label(),
                                    ])
                                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                                        if ($state !== TimingMode::PrayerRelative->value) {
                                            $set('prayer_time', null);
                                        }
                                    })
                                    ->live(),

                                Select::make('prayer_time')
                                    ->label(__('Prayer Time'))
                                    ->placeholder(__('Any'))
                                    ->disabled(fn (Get $get): bool => $get('timing_mode') !== TimingMode::PrayerRelative->value)
                                    ->searchable()
                                    ->options(collect(EventPrayerTime::cases())
                                        ->mapWithKeys(fn (EventPrayerTime $prayerTime): array => [$prayerTime->value => $prayerTime->getLabel()])
                                    ->all()
                                    )
                                    ->live(),
                            ]),

                        Section::make(__('Links & Visibility'))
                            ->extraAttributes(['class' => 'mi-advanced-filter-group'])
                            ->description(__('Filter events by URL and end-time metadata availability.'))
                            ->columns(['default' => 1, 'md' => 3])
                            ->schema([
                                Select::make('has_event_url')
                                    ->label(__('Event URL'))
                                    ->placeholder(__('Any'))
                                    ->options([
                                        '1' => __('Has URL'),
                                        '0' => __('No URL'),
                                    ])
                                    ->live(),

                                Select::make('has_live_url')
                                    ->label(__('Live URL'))
                                    ->placeholder(__('Any'))
                                    ->options([
                                        '1' => __('Has Live URL'),
                                        '0' => __('No Live URL'),
                                    ])
                                    ->live(),

                                Select::make('has_end_time')
                                    ->label(__('End Time'))
                                    ->placeholder(__('Any'))
                                    ->options([
                                        '1' => __('Has End Time'),
                                        '0' => __('No End Time'),
                                    ])
                                    ->live(),
                            ]),
                    ]),
            ]);
    }

    public function updatedFilterData(): void
    {
        $normalized = $this->normalizedFilterData($this->filterData);

        $this->fillPublicPropertiesFromFilters($normalized);
        $this->resetPage();
    }

    public function setLocation(float $lat, float $lng): void
    {
        $this->lat = (string) $lat;
        $this->lng = (string) $lng;
        $this->sort = 'distance';

        $this->filterData['lat'] = $this->lat;
        $this->filterData['lng'] = $this->lng;
        $this->filterData['sort'] = $this->sort;

        $this->resetPage();
    }

    public function clearLocation(): void
    {
        $this->lat = null;
        $this->lng = null;

        $defaultSort = $this->sort === 'distance' ? 'time' : $this->sort;
        $this->sort = $defaultSort;

        $this->filterData['lat'] = null;
        $this->filterData['lng'] = null;
        $this->filterData['sort'] = $defaultSort;

        $this->resetPage();
    }

    public function clearAllFilters(): void
    {
        $defaults = $this->defaultFilterData();

        $this->fillPublicPropertiesFromFilters($defaults);
        $this->filterData = $defaults;
        $this->getForm('form')?->fill($defaults);

        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = null;
        $this->filterData['search'] = null;
        $this->resetPage();
    }

    public function setSort(string $sort): void
    {
        if (! in_array($sort, ['time', 'relevance', 'distance'], true)) {
            return;
        }

        if ($sort === 'distance' && (! filled($this->lat) || ! filled($this->lng))) {
            return;
        }

        $this->sort = $sort;
        $this->filterData['sort'] = $sort;

        $this->resetPage();
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
        $stateId = $this->state_id;

        if (! filled($stateId)) {
            return collect();
        }

        return District::query()
            ->where('state_id', $stateId)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Subdistrict>
     */
    #[Computed]
    public function subdistricts(): Collection
    {
        $districtId = $this->district_id;

        if (! filled($districtId)) {
            return collect();
        }

        return Subdistrict::query()
            ->where('district_id', $districtId)
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
            ->limit(400)
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
            ->limit(500)
            ->get(['id', 'name'])
        );
    }

    /**
     * @return LengthAwarePaginator<int, Event>
     */
    #[Computed]
    public function events(): LengthAwarePaginator
    {
        $filters = $this->normalizedUrlState();

        $searchFilters = [
            'state_id' => $filters['state_id'],
            'district_id' => $filters['district_id'],
            'subdistrict_id' => $filters['subdistrict_id'],
            'language' => $filters['language'],
            'language_codes' => $filters['language_codes'],
            'event_type' => $filters['event_type'],
            'gender' => $filters['gender'],
            'age_group' => $filters['age_group'],
            'children_allowed' => $filters['children_allowed'],
            'is_muslim_only' => $filters['is_muslim_only'],
            'institution_id' => $filters['institution_id'],
            'speaker_ids' => $filters['speaker_ids'],
            'topic_ids' => $filters['topic_ids'],
            'starts_after' => $filters['starts_after'],
            'starts_before' => $filters['starts_before'],
            'time_scope' => $filters['time_scope'],
            'prayer_time' => $filters['prayer_time'],
            'timing_mode' => $filters['timing_mode'],
            'event_format' => $filters['event_format'],
            'has_event_url' => $filters['has_event_url'],
            'has_live_url' => $filters['has_live_url'],
            'has_end_time' => $filters['has_end_time'],
        ];

        $searchFilters = array_filter($searchFilters, function (mixed $value): bool {
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

        if ($filters['lat'] !== null && $filters['lng'] !== null) {
            return $searchService->searchNearby(
                lat: (float) $filters['lat'],
                lng: (float) $filters['lng'],
                radiusKm: $filters['radius_km'],
                filters: $searchFilters,
                perPage: 12
            );
        }

        return $searchService->search(
            query: $filters['search'],
            filters: $searchFilters,
            perPage: 12,
            sort: $filters['sort']
        );
    }

    /**
     * @return array<string, string>
     */
    public function languageOptions(): array
    {
        return cache()->remember('event_filter_languages_v2', 3600, function (): array {
            $preferredOrder = ['ms', 'ar', 'en', 'id', 'zh', 'ta', 'jv'];
            $preferredLabels = [
                'ms' => 'Bahasa Melayu',
                'ar' => 'Bahasa Arab',
                'en' => 'Bahasa Inggeris',
                'id' => 'Bahasa Indonesia',
                'zh' => 'Bahasa Cina',
                'ta' => 'Bahasa Tamil',
                'jv' => 'Bahasa Jawa',
            ];

            return Language::query()
                ->whereIn('code', $preferredOrder)
                ->get(['code', 'name'])
                ->sortBy(fn (Language $language): int|false => array_search((string) $language->code, $preferredOrder, true))
                ->mapWithKeys(function (Language $language) use ($preferredLabels): array {
                    $code = (string) $language->code;
                    $label = $preferredLabels[$code] ?? (string) ($language->name ?? strtoupper($code));

                    return [$code => $label];
                })
                ->all();
        });
    }

    public function render(): View
    {
        return view('livewire.pages.events.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultFilterData(): array
    {
        return [
            'search' => null,
            'state_id' => null,
            'district_id' => null,
            'subdistrict_id' => null,
            'language' => null,
            'language_codes' => [],
            'event_type' => [],
            'gender' => null,
            'age_group' => [],
            'children_allowed' => null,
            'is_muslim_only' => null,
            'institution_id' => null,
            'speaker_ids' => [],
            'topic_ids' => [],
            'starts_after' => null,
            'starts_before' => null,
            'time_scope' => 'upcoming',
            'prayer_time' => null,
            'timing_mode' => null,
            'event_format' => [],
            'has_event_url' => null,
            'has_live_url' => null,
            'has_end_time' => null,
            'lat' => null,
            'lng' => null,
            'radius_km' => 50,
            'sort' => 'time',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedUrlState(): array
    {
        $defaults = $this->defaultFilterData();

        $languageCodes = $this->normalizeStringArray($this->language_codes);

        if ($languageCodes === [] && filled($this->language)) {
            $languageCodes = [(string) $this->language];
        }

        return [
            'search' => filled($this->search) ? trim((string) $this->search) : null,
            'state_id' => filled($this->state_id) ? (string) $this->state_id : null,
            'district_id' => filled($this->district_id) ? (string) $this->district_id : null,
            'subdistrict_id' => filled($this->subdistrict_id) ? (string) $this->subdistrict_id : null,
            'language' => filled($this->language) ? (string) $this->language : null,
            'language_codes' => $languageCodes,
            'event_type' => $this->normalizeStringArray($this->event_type),
            'gender' => filled($this->gender) ? (string) $this->gender : null,
            'age_group' => $this->normalizeStringArray($this->age_group),
            'children_allowed' => $this->normalizeNullableBoolean($this->children_allowed),
            'is_muslim_only' => $this->normalizeNullableBoolean($this->is_muslim_only),
            'institution_id' => filled($this->institution_id) ? (string) $this->institution_id : null,
            'speaker_ids' => $this->normalizeStringArray($this->speaker_ids),
            'topic_ids' => $this->normalizeStringArray($this->topic_ids),
            'starts_after' => filled($this->starts_after) ? (string) $this->starts_after : null,
            'starts_before' => filled($this->starts_before) ? (string) $this->starts_before : null,
            'time_scope' => in_array($this->time_scope, ['upcoming', 'past', 'all'], true) ? $this->time_scope : $defaults['time_scope'],
            'prayer_time' => filled($this->prayer_time) ? (string) $this->prayer_time : null,
            'timing_mode' => in_array($this->timing_mode, [TimingMode::Absolute->value, TimingMode::PrayerRelative->value], true)
                ? $this->timing_mode
                : null,
            'event_format' => $this->normalizeStringArray($this->event_format),
            'has_event_url' => $this->normalizeNullableBoolean($this->has_event_url),
            'has_live_url' => $this->normalizeNullableBoolean($this->has_live_url),
            'has_end_time' => $this->normalizeNullableBoolean($this->has_end_time),
            'lat' => filled($this->lat) ? (string) $this->lat : null,
            'lng' => filled($this->lng) ? (string) $this->lng : null,
            'radius_km' => max(1, min(500, (int) $this->radius_km)),
            'sort' => in_array($this->sort, ['time', 'relevance', 'distance'], true) ? $this->sort : $defaults['sort'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function fillPublicPropertiesFromFilters(array $filters): void
    {
        $this->search = $filters['search'];
        $this->state_id = $filters['state_id'];
        $this->district_id = $filters['district_id'];
        $this->subdistrict_id = $filters['subdistrict_id'];
        $this->language = $filters['language'];
        $this->language_codes = $filters['language_codes'];
        $this->event_type = $filters['event_type'];
        $this->gender = $filters['gender'];
        $this->age_group = $filters['age_group'];
        $this->children_allowed = $filters['children_allowed'];
        $this->is_muslim_only = $filters['is_muslim_only'];
        $this->institution_id = $filters['institution_id'];
        $this->speaker_ids = $filters['speaker_ids'];
        $this->topic_ids = $filters['topic_ids'];
        $this->starts_after = $filters['starts_after'];
        $this->starts_before = $filters['starts_before'];
        $this->time_scope = $filters['time_scope'];
        $this->prayer_time = $filters['prayer_time'];
        $this->timing_mode = $filters['timing_mode'];
        $this->event_format = $filters['event_format'];
        $this->has_event_url = $filters['has_event_url'];
        $this->has_live_url = $filters['has_live_url'];
        $this->has_end_time = $filters['has_end_time'];
        $this->lat = $filters['lat'];
        $this->lng = $filters['lng'];
        $this->radius_km = $filters['radius_km'];
        $this->sort = $filters['sort'];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizedFilterData(array $raw): array
    {
        $defaults = $this->defaultFilterData();

        $normalized = array_replace($defaults, $raw);

        $languageCodes = $this->normalizeStringArray($normalized['language_codes'] ?? []);
        $legacyLanguage = filled($normalized['language'] ?? null) ? (string) $normalized['language'] : null;

        if ($languageCodes === [] && $legacyLanguage !== null) {
            $languageCodes = [$legacyLanguage];
        }

        $normalizedLanguage = $languageCodes !== [] ? $languageCodes[0] : $legacyLanguage;

        $timeScope = (string) ($normalized['time_scope'] ?? $defaults['time_scope']);

        if (! in_array($timeScope, ['upcoming', 'past', 'all'], true)) {
            $timeScope = (string) $defaults['time_scope'];
        }

        $sort = (string) ($normalized['sort'] ?? $defaults['sort']);

        if (! in_array($sort, ['time', 'relevance', 'distance'], true)) {
            $sort = (string) $defaults['sort'];
        }

        $timingMode = (string) ($normalized['timing_mode'] ?? '');

        if (! in_array($timingMode, [TimingMode::Absolute->value, TimingMode::PrayerRelative->value], true)) {
            $timingMode = '';
        }

        return [
            'search' => filled($normalized['search']) ? trim((string) $normalized['search']) : null,
            'state_id' => filled($normalized['state_id']) ? (string) $normalized['state_id'] : null,
            'district_id' => filled($normalized['district_id']) ? (string) $normalized['district_id'] : null,
            'subdistrict_id' => filled($normalized['subdistrict_id']) ? (string) $normalized['subdistrict_id'] : null,
            'language' => $normalizedLanguage,
            'language_codes' => $languageCodes,
            'event_type' => $this->normalizeStringArray($normalized['event_type'] ?? []),
            'gender' => filled($normalized['gender']) ? (string) $normalized['gender'] : null,
            'age_group' => $this->normalizeStringArray($normalized['age_group'] ?? []),
            'children_allowed' => $this->normalizeNullableBoolean($normalized['children_allowed'] ?? null),
            'is_muslim_only' => $this->normalizeNullableBoolean($normalized['is_muslim_only'] ?? null),
            'institution_id' => filled($normalized['institution_id']) ? (string) $normalized['institution_id'] : null,
            'speaker_ids' => $this->normalizeStringArray($normalized['speaker_ids'] ?? []),
            'topic_ids' => $this->normalizeStringArray($normalized['topic_ids'] ?? []),
            'starts_after' => filled($normalized['starts_after']) ? (string) $normalized['starts_after'] : null,
            'starts_before' => filled($normalized['starts_before']) ? (string) $normalized['starts_before'] : null,
            'time_scope' => $timeScope,
            'prayer_time' => filled($normalized['prayer_time']) ? (string) $normalized['prayer_time'] : null,
            'timing_mode' => $timingMode !== '' ? $timingMode : null,
            'event_format' => $this->normalizeStringArray($normalized['event_format'] ?? []),
            'has_event_url' => $this->normalizeNullableBoolean($normalized['has_event_url'] ?? null),
            'has_live_url' => $this->normalizeNullableBoolean($normalized['has_live_url'] ?? null),
            'has_end_time' => $this->normalizeNullableBoolean($normalized['has_end_time'] ?? null),
            'lat' => filled($normalized['lat']) ? (string) $normalized['lat'] : null,
            'lng' => filled($normalized['lng']) ? (string) $normalized['lng'] : null,
            'radius_km' => max(1, min(500, (int) ($normalized['radius_km'] ?? $defaults['radius_km']))),
            'sort' => $sort,
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeStringArray(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $values = is_array($value) ? $value : [$value];

        return array_values(array_filter(array_map('strval', $values), static fn (string $item): bool => $item !== ''));
    }

    private function normalizeNullableBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (in_array($value, [1, '1', 'true', 'on', 'yes'], true)) {
            return true;
        }

        if (in_array($value, [0, '0', 'false', 'off', 'no'], true)) {
            return false;
        }

        return null;
    }
}

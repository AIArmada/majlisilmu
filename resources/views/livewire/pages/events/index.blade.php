@section('title', __('Upcoming Events') . ' - ' . config('app.name'))

@push('head')
    <!-- OpenGraph / Twitter Cards -->
    <meta property="og:title" content="{{ __('Upcoming Events') }} - {{ config('app.name') }}">
    <meta property="og:description"
        content="{{ __('Discover Islamic lectures, classes, and gatherings happening near you.') }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ route('events.index') }}">
    <meta name="twitter:card" content="summary">
@endpush

@once
    @push('styles')
        <style>
            .mi-filter-shell .mi-advanced-filter-section.fi-section {
                border-radius: 1.2rem;
                border-color: rgb(226 232 240 / 0.8);
                background: linear-gradient(180deg, rgb(255 255 255), rgb(248 250 252));
                box-shadow: 0 8px 22px -18px rgb(15 23 42 / 0.45);
            }

            .mi-filter-shell .mi-advanced-filter-section .fi-section-content {
                gap: 1rem;
            }

            .mi-filter-shell .mi-advanced-filter-group.fi-section {
                border-radius: 0.95rem;
                border: 1px solid rgb(226 232 240 / 0.9);
                background: rgb(255 255 255 / 0.92);
                box-shadow: none;
            }

            .mi-filter-shell .mi-advanced-filter-group+.mi-advanced-filter-group {
                margin-top: 0.85rem;
            }

            .mi-filter-shell .mi-advanced-filter-group .fi-section-content {
                gap: 0.75rem;
            }

            .mi-filter-shell .mi-advanced-filter-section .fi-input,
            .mi-filter-shell .mi-advanced-filter-section .fi-select-input,
            .mi-filter-shell .mi-advanced-filter-section .fi-select-control {
                border-radius: 0.8rem;
            }

            .mi-filter-shell .mi-advanced-filter-section .fi-fo-field-wrp-label {
                font-size: 0.72rem;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: rgb(100 116 139);
            }
        </style>
    @endpush
@endonce

@php
    $search = $this->search;
    $stateId = $this->state_id;
    $districtId = $this->district_id;
    $subdistrictId = $this->subdistrict_id;
    $institutionId = $this->institution_id;
    $gender = $this->gender;
    $childrenAllowed = $this->children_allowed;
    $isMuslimOnly = $this->is_muslim_only;
    $startsAfter = $this->starts_after;
    $startsBefore = $this->starts_before;
    $prayerTime = $this->prayer_time;
    $timingMode = $this->timing_mode;
    $timeScope = $this->time_scope ?? 'upcoming';
    $lat = $this->lat;
    $sort = $this->sort;
    $states = $this->states;
    $districts = $this->districts;
    $subdistricts = $this->subdistricts;
    $topics = $this->topics;
    $institutions = $this->institutions;
    $speakers = $this->speakers;
    $languageOptions = $this->languageOptions();
    $selectedAgeGroups = array_values(array_filter((array) $this->age_group));
    $selectedTopicIds = array_values(array_filter((array) $this->topic_ids));
    $selectedSpeakerIds = array_values(array_filter((array) $this->speaker_ids));
    $selectedEventTypes = array_values(array_filter((array) $this->event_type));
    $selectedEventFormats = array_values(array_filter((array) $this->event_format));
    $selectedLanguageCodes = array_values(array_filter((array) $this->language_codes));
    $selectedTopicLabels = collect($selectedTopicIds)
        ->map(fn (string $topicId): ?string => $topics->firstWhere('id', $topicId)?->name)
        ->filter()
        ->values();
    $selectedSpeakerLabels = collect($selectedSpeakerIds)
        ->map(fn (string $speakerId): ?string => $speakers->firstWhere('id', $speakerId)?->name)
        ->filter()
        ->values();
    $selectedLanguageLabels = collect($selectedLanguageCodes)
        ->map(fn (string $code): string => (string) ($languageOptions[$code] ?? strtoupper($code)))
        ->filter()
        ->values();

    $eventTypeLabels = collect(\App\Enums\EventType::cases())
        ->mapWithKeys(fn (\App\Enums\EventType $type): array => [$type->value => $type->getLabel()])
        ->all();

    $eventFormatLabels = collect(\App\Enums\EventFormat::cases())
        ->mapWithKeys(fn (\App\Enums\EventFormat $format): array => [$format->value => $format->getLabel()])
        ->all();

    $ageGroupLabels = collect(\App\Enums\EventAgeGroup::cases())
        ->mapWithKeys(fn (\App\Enums\EventAgeGroup $group): array => [$group->value => $group->getLabel()])
        ->all();

    $genderLabels = collect(\App\Enums\EventGenderRestriction::cases())
        ->mapWithKeys(fn (\App\Enums\EventGenderRestriction $restriction): array => [$restriction->value => $restriction->getLabel()])
        ->all();

    $timingModeLabel = \App\Enums\TimingMode::tryFrom((string) $timingMode)?->label();
    $prayerTimeLabel = \App\Enums\EventPrayerTime::tryFrom((string) $prayerTime)?->getLabel() ?? $prayerTime;

    $savedSearchQuery = array_filter([
        'search' => $search,
        'state_id' => $stateId,
        'district_id' => $districtId,
        'subdistrict_id' => $subdistrictId,
        'institution_id' => $institutionId,
        'speaker_ids' => $selectedSpeakerIds,
        'language_codes' => $selectedLanguageCodes,
        'event_type' => $selectedEventTypes,
        'event_format' => $selectedEventFormats,
        'gender' => $gender,
        'age_group' => $selectedAgeGroups,
        'children_allowed' => $childrenAllowed,
        'is_muslim_only' => $isMuslimOnly,
        'starts_after' => $startsAfter,
        'starts_before' => $startsBefore,
        'prayer_time' => $prayerTime,
        'timing_mode' => $timingMode,
        'has_event_url' => $this->has_event_url,
        'has_live_url' => $this->has_live_url,
        'has_end_time' => $this->has_end_time,
        'topic_ids' => $selectedTopicIds,
        'lat' => $lat,
        'lng' => $this->lng,
        'radius_km' => $this->radius_km,
        'sort' => $sort,
        'time_scope' => $this->time_scope,
    ], function (mixed $value): bool {
        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null && $value !== '';
    });

    $activeFilterCount = collect([
        $stateId,
        $districtId,
        $subdistrictId,
        $institutionId,
        count($selectedLanguageCodes) > 0,
        count($selectedEventTypes) > 0,
        count($selectedEventFormats) > 0,
        $gender,
        count($selectedAgeGroups) > 0,
        $childrenAllowed,
        $isMuslimOnly,
        count($selectedSpeakerIds) > 0,
        count($selectedTopicIds) > 0,
        $startsAfter,
        $startsBefore,
        $prayerTime,
        $timingMode,
        $this->has_event_url,
        $this->has_live_url,
        $this->has_end_time,
        $timeScope !== 'upcoming',
        $lat,
    ])->filter(fn ($value) => $value !== null && $value !== '' && $value !== false)->count();

    $hasActiveFilters = collect([
        $search,
        $stateId,
        $districtId,
        $subdistrictId,
        $institutionId,
        count($selectedLanguageCodes) > 0,
        count($selectedEventTypes) > 0,
        count($selectedEventFormats) > 0,
        $gender,
        count($selectedAgeGroups) > 0,
        $childrenAllowed,
        $isMuslimOnly,
        count($selectedSpeakerIds) > 0,
        count($selectedTopicIds) > 0,
        $startsAfter,
        $startsBefore,
        $prayerTime,
        $timingMode,
        $this->has_event_url,
        $this->has_live_url,
        $this->has_end_time,
        $timeScope !== 'upcoming',
        $lat,
    ])->contains(fn ($value) => $value !== null && $value !== '' && $value !== false);
@endphp

<div class="relative min-h-screen pb-32">
    <div class="relative pt-24 pb-16 bg-white border-b border-slate-100 overflow-hidden">
        <div class="absolute inset-0 bg-emerald-50/50"></div>
        <div class="absolute inset-0 bg-[url('/images/pattern-bg.png')] opacity-5"></div>

        <div class="container relative mx-auto px-6 lg:px-12 text-center">
            <span
                class="inline-flex items-center gap-1.5 rounded-full border border-emerald-100 bg-white/80 px-3 py-1 text-xs font-semibold text-emerald-700 shadow-sm backdrop-blur-sm mb-6">
                <span class="relative flex h-2 w-2">
                    <span
                        class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                {{ $this->events->total() }}
                {{ match ($this->time_scope ?? 'upcoming') {
                    'past' => __('Past Gatherings'),
                    'all' => __('All Gatherings'),
                    default => __('Upcoming Gatherings'),
                } }}
            </span>

            <h1
                class="font-heading text-5xl md:text-6xl font-extrabold text-slate-900 tracking-tight text-balance mb-6">
                {{ __('Find Your Next') }} <br class="hidden md:block" />
                <span
                    class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-teal-500">{{ __('Circle of Knowledge') }}</span>
            </h1>
            <p class="text-slate-500 text-lg md:text-xl max-w-2xl mx-auto text-balance">
                {{ __('Discover classes, lectures, and community gatherings happening near you. Connect with knowledge seekers in your area.') }}
            </p>
        </div>
    </div>

    <div class="container mx-auto px-6 lg:px-12 -mt-8 relative z-10">
        <form wire:submit.prevent x-data="{
                    locating: false,
                    locate() {
                        if (this.locating) return;
                        if (! navigator.geolocation) {
                            alert('{{ __("Geolocation is not supported by your browser.") }}');
                            return;
                        }
                        this.locating = true;
                        navigator.geolocation.getCurrentPosition((position) => {
                            this.$wire.setLocation(position.coords.latitude, position.coords.longitude);
                            this.locating = false;
                        }, () => {
                            this.locating = false;
                            alert('{{ __("Unable to get your location. Please enable location services.") }}');
                        });
                    },
                }" class="relative bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 p-6 md:p-8">

            <div wire:loading.delay.short
                wire:target="filterData,setLocation,clearLocation,clearAllFilters,setSort"
                class="pointer-events-none absolute right-4 top-4 z-20 inline-flex items-center gap-2 rounded-lg border border-emerald-100 bg-emerald-50/95 px-3 py-1.5 text-xs font-semibold text-emerald-700 shadow-sm backdrop-blur-sm md:right-6 md:top-6">
                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4"></circle>
                    <path class="opacity-75" stroke-width="4" d="M22 12a10 10 0 00-10-10"></path>
                </svg>
                {{ __('Updating results...') }}
            </div>

            <div class="max-w-xl mx-auto mb-6">
                <div class="relative group">
                    <label for="event-search" class="sr-only">{{ __('Search events') }}</label>
                    <input
                        type="text"
                        id="event-search"
                        wire:model.live.debounce.300ms="filterData.search"
                        wire:keydown.escape="clearSearch"
                        placeholder="{{ __('Cari mengikut tajuk, surau, masjid atau penceramah') }}"
                        class="w-full h-14 pl-12 pr-4 rounded-2xl border-2 border-slate-100 bg-white shadow-lg shadow-slate-200/50 font-medium text-slate-900 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all placeholder:text-slate-400"
                    >
                    <svg class="absolute left-4 top-1/2 -translate-y-1/2 h-6 w-6 text-slate-400 group-focus-within:text-emerald-500 transition-colors"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    @if(filled($search))
                        <button type="button" wire:click="clearSearch"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-red-500 hover:underline">
                            {{ __('Clear') }}
                        </button>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                <div class="flex items-center gap-2">
                    <button type="button" @click="locate" :disabled="locating"
                        class="h-11 px-4 rounded-xl border border-slate-200 bg-white font-semibold text-slate-600 hover:border-emerald-500 hover:text-emerald-600 focus:ring-4 focus:ring-emerald-500/10 transition-all flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" :class="locating ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path x-show="!locating" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path x-show="!locating" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            <circle x-show="locating" class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path x-show="locating" class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span x-text="locating ? '{{ __('Locating...') }}' : '{{ __('Near Me') }}'"></span>
                    </button>

                    @if($lat)
                        <button type="button" wire:click="clearLocation"
                            class="h-11 px-4 rounded-xl border border-rose-100 bg-rose-50 text-xs font-semibold text-rose-600 hover:bg-rose-100 transition">
                            {{ __('Clear Location') }}
                        </button>
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-slate-500">{{ __('Sort:') }}</span>
                    <div class="flex bg-slate-100 p-1 rounded-xl">
                        <button type="button" wire:click="setSort('time')"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $sort === 'time' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                            {{ __('Latest') }}
                        </button>
                        <button type="button" wire:click="setSort('relevance')"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $sort === 'relevance' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                            {{ __('Relevance') }}
                        </button>
                        @if($lat)
                            <button type="button" wire:click="setSort('distance')"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $sort === 'distance' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                                {{ __('Distance') }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mi-filter-shell">
                {{ $this->form }}
            </div>

            @if($hasActiveFilters)
                <div class="flex flex-wrap items-center gap-2 mt-6 pt-4 border-t border-slate-100">
                    <span class="text-xs font-bold text-slate-400 uppercase mr-2">{{ __('Active:') }}</span>

                    @if($search)
                        <span
                            class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            "{{ $search }}"
                        </span>
                    @endif

                    @if($lat)
                        <span
                            class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            </svg>
                            {{ __('Nearby') }}
                        </span>
                    @endif

                    @if($stateId)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ $states->firstWhere('id', $stateId)?->name ?? __('State') }}
                        </span>
                    @endif

                    @if($districtId)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ $districts->firstWhere('id', $districtId)?->name ?? __('District') }}
                        </span>
                    @endif

                    @if($subdistrictId)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ $subdistricts->firstWhere('id', $subdistrictId)?->name ?? __('Subdistrict') }}
                        </span>
                    @endif

                    @if($institutionId)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ $institutions->firstWhere('id', $institutionId)?->name ?? __('Institution') }}
                        </span>
                    @endif

                    @foreach($selectedLanguageLabels as $languageLabel)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-sky-50 text-sky-700 border border-sky-100">
                            {{ $languageLabel }}
                        </span>
                    @endforeach

                    @foreach($selectedEventTypes as $eventType)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ $eventTypeLabels[$eventType] ?? str((string) $eventType)->replace('_', ' ')->headline() }}
                        </span>
                    @endforeach

                    @foreach($selectedEventFormats as $eventFormat)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-violet-50 text-violet-700 border border-violet-100">
                            {{ $eventFormatLabels[$eventFormat] ?? str((string) $eventFormat)->headline() }}
                        </span>
                    @endforeach

                    @if($gender)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ $genderLabels[$gender] ?? str((string) $gender)->replace('_', ' ')->headline() }}
                        </span>
                    @endif

                    @if($prayerTime)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-100">
                            {{ $prayerTimeLabel }}
                        </span>
                    @endif

                    @if($timingModeLabel)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-100">
                            {{ $timingModeLabel }}
                        </span>
                    @endif

                    @if($childrenAllowed !== null)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ $childrenAllowed ? __('Children Allowed') : __('No Children') }}
                        </span>
                    @endif

                    @if($isMuslimOnly !== null)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ $isMuslimOnly ? __('Muslim Only') : __('Open to All Faiths') }}
                        </span>
                    @endif

                    @if($this->has_event_url !== null)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ $this->has_event_url ? __('Has Event URL') : __('No Event URL') }}
                        </span>
                    @endif

                    @if($this->has_live_url !== null)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ $this->has_live_url ? __('Has Live URL') : __('No Live URL') }}
                        </span>
                    @endif

                    @if($this->has_end_time !== null)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ $this->has_end_time ? __('Has End Time') : __('No End Time') }}
                        </span>
                    @endif

                    @foreach($selectedAgeGroups as $ageGroup)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ $ageGroupLabels[$ageGroup] ?? str($ageGroup)->replace('_', ' ')->headline() }}
                        </span>
                    @endforeach

                    @foreach($selectedTopicLabels as $topicLabel)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                            {{ $topicLabel }}
                        </span>
                    @endforeach

                    @foreach($selectedSpeakerLabels as $speakerLabel)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ $speakerLabel }}
                        </span>
                    @endforeach

                    @if($startsAfter)
                        @php
                            $startsAfterLabel = \Illuminate\Support\Carbon::make($startsAfter)?->format('d M Y') ?? $startsAfter;
                        @endphp
                        <span
                            class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ __('From') }} {{ $startsAfterLabel }}
                        </span>
                    @endif

                    @if($startsBefore)
                        @php
                            $startsBeforeLabel = \Illuminate\Support\Carbon::make($startsBefore)?->format('d M Y') ?? $startsBefore;
                        @endphp
                        <span
                            class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ __('Until') }} {{ $startsBeforeLabel }}
                        </span>
                    @endif

                    @if($timeScope === 'past')
                        <span
                            class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ __('Past') }}
                        </span>
                    @endif

                    @if($timeScope === 'all')
                        <span
                            class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ __('All Time') }}
                        </span>
                    @endif

                    <a href="{{ route('saved-searches.index', $savedSearchQuery) }}" wire:navigate
                        class="ml-auto text-xs font-bold text-emerald-600 hover:text-emerald-700 hover:underline">
                        {{ __('Save This Search') }}
                    </a>

                    <button type="button" wire:click="clearAllFilters"
                        class="text-xs font-bold text-red-500 hover:text-red-600 hover:underline">
                        {{ __('Clear All Filters') }}
                    </button>
                </div>
            @endif
        </form>

        <!-- Results Grid -->
        <div class="mt-16 relative"
            wire:loading.class="opacity-60"
            wire:target="filterData,setLocation,clearLocation,clearAllFilters,setSort">
            <div wire:loading.flex
                wire:target="filterData,setLocation,clearLocation,clearAllFilters,setSort"
                class="pointer-events-none absolute inset-0 z-30 items-center justify-center rounded-3xl bg-white/50 backdrop-blur-[1px]">
                <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow">
                    <svg class="h-4 w-4 animate-spin text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4"></circle>
                        <path class="opacity-75" stroke-width="4" d="M22 12a10 10 0 00-10-10"></path>
                    </svg>
                    {{ __('Refreshing events...') }}
                </div>
            </div>
            @php
                $events = $this->events;
            @endphp

            @if($events->isEmpty())
                <div class="flex flex-col items-center justify-center py-24 text-center">
                    <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mb-6">
                        <svg class="w-10 h-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="font-heading text-xl font-bold text-slate-900 mb-2">{{ __('No events found') }}</h3>
                    <p class="text-slate-500 max-w-md">
                        {{ __('Try adjusting your search terms or filters to find what you\'re looking for.') }}
                    </p>
                    <button type="button" @click="window.location.href='{{ route('events.index') }}'"
                        class="mt-6 font-semibold text-emerald-600 hover:text-emerald-700">
                        {{ __('View all events') }} &rarr;
                    </button>
                </div>
            @else
                <div class="grid items-start md:grid-cols-2 lg:grid-cols-3 gap-8">
                    @foreach($events as $event)
                        @php
                            $posterMedia = $event->getFirstMedia('poster');
                            $eventHasPoster = $posterMedia !== null;
                            $posterUrl = $eventHasPoster ? (string) $posterMedia->getAvailableUrl(['card', 'preview', 'thumb']) : '';
                            $eventCardImageUrl = $posterUrl !== '' ? $posterUrl : $event->card_image_url;
                            $eventPosterIsPortrait = $eventHasPoster && in_array($event->poster_orientation, ['portrait', 'square'], true);
                            $primaryLocationName = $event->venue?->name ?? $event->institution?->name;
                            $addressModel = $event->venue?->addressModel ?? $event->institution?->addressModel;
                            $subdistrictName = $addressModel?->subdistrict?->name;
                            $districtName = $addressModel?->district?->name;
                            $stateName = $addressModel?->state?->name;

                            $stateHiddenDistricts = ['kuala lumpur', 'putrajaya', 'labuan'];
                            if (is_string($districtName) && in_array(mb_strtolower(trim($districtName)), $stateHiddenDistricts, true)) {
                                $stateName = null;
                            }

                            $hierarchyParts = array_values(array_filter([
                                $subdistrictName,
                                $districtName,
                                $stateName,
                            ], static fn (?string $part): bool => is_string($part) && $part !== ''));

                            $hierarchyText = match (count($hierarchyParts)) {
                                0 => '',
                                1 => $hierarchyParts[0],
                                2 => $hierarchyParts[0].' & '.$hierarchyParts[1],
                                default => implode(', ', array_slice($hierarchyParts, 0, -1)).' & '.$hierarchyParts[array_key_last($hierarchyParts)],
                            };

                            $locationPrimaryText = is_string($primaryLocationName) && $primaryLocationName !== ''
                                ? $primaryLocationName
                                : null;
                            $locationSecondaryText = $hierarchyText !== '' ? $hierarchyText : null;

                            if ($locationPrimaryText === null && $locationSecondaryText === null) {
                                $locationPrimaryText = __('Online');
                            }
                        @endphp
                        <article
                            class="group flex flex-col bg-white rounded-3xl overflow-hidden shadow-sm hover:shadow-xl hover:shadow-emerald-900/5 hover:-translate-y-1 transition-all duration-300 border border-slate-100">
                            <!-- Image/Date -->
                            <a href="{{ route('events.show', $event) }}" wire:navigate
                                class="relative overflow-hidden bg-slate-100 block {{ $eventPosterIsPortrait ? 'aspect-[4/5]' : 'aspect-[3/2]' }}">
                                <div
                                    class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-60 z-10 transition-opacity group-hover:opacity-70">
                                </div>
                                <div class="w-full h-full bg-slate-200 flex items-center justify-center text-slate-300">
                                    <img src="{{ $eventCardImageUrl }}" alt="{{ $event->title }}" loading="lazy"
                                        class="w-full h-full transition-transform duration-700 group-hover:scale-105 {{ $eventHasPoster ? 'object-contain bg-slate-100' : 'object-cover' }}">
                                </div>

                                <!-- Badges -->
                                <div class="absolute top-4 left-4 z-20 flex flex-col gap-2">
                                    <div
                                        class="bg-white/95 backdrop-blur-sm rounded-xl px-3 py-1.5 text-center shadow-sm border border-black/5 min-w-[3.5rem]">
                                        <div class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                            {{ \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->starts_at, 'M') }}
                                        </div>
                                        <div class="text-xl font-bold font-heading text-slate-900 leading-none">
                                            {{ \App\Support\Timezone\UserDateTimeFormatter::format($event->starts_at, 'd') }}
                                        </div>
                                    </div>
                                    @if($event->status instanceof \App\States\EventStatus\Pending)
                                        <span class="inline-flex items-center gap-1 bg-amber-500/90 backdrop-blur-md text-white px-2.5 py-1 rounded-full text-[0.65rem] font-bold shadow-lg uppercase tracking-wide">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            {{ __('Menunggu Kelulusan') }}
                                        </span>
                                    @endif
                                </div>

                                @if(isset($event->distance_km))
                                    <div class="absolute top-4 right-4 z-20">
                                        <span
                                            class="inline-flex items-center gap-1 bg-emerald-600/90 backdrop-blur-md text-white px-2.5 py-1 rounded-full text-xs font-semibold shadow-lg">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            </svg>
                                            {{ number_format($event->distance_km, 1) }} km
                                        </span>
                                    </div>
                                @endif

                                <!-- Category Pill -->
                                <div class="absolute bottom-4 left-4 z-20">
                                    <span
                                        class="inline-flex items-center rounded-full bg-white/20 backdrop-blur-md border border-white/30 px-3 py-1 text-xs font-semibold text-white shadow-sm hover:bg-white/30 transition-colors">
                                        {{ $event->eventType?->name ?? __('Kuliah') }}
                                    </span>
                                </div>
                            </a>

                            <div class="p-6 flex flex-col">
                                <div class="flex justify-between items-start mb-3 gap-4">
                                    <a href="{{ route('events.show', $event) }}" wire:navigate
                                        class="group-hover:text-emerald-700 transition-colors">
                                        <h3 class="font-heading text-xl font-bold text-slate-900 line-clamp-2 leading-tight">
                                            {{ $event->title }}
                                        </h3>
                                    </a>
                                </div>

                                <div class="space-y-3">
                                    <div class="flex items-start gap-2.5 text-sm text-slate-600">
                                        <svg class="w-4 h-4 text-emerald-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span class="min-w-0">
                                            <span class="block line-clamp-1">{{ $locationPrimaryText }}</span>
                                            @if($locationSecondaryText)
                                                <span class="mt-0.5 block line-clamp-1 text-xs text-slate-500">{{ $locationSecondaryText }}</span>
                                            @endif
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2.5 text-sm text-slate-600">
                                        <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        {{ $event->timing_display }}
                                    </div>
                                </div>

                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="mt-16">
                    {{ $events->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

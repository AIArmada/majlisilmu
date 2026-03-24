@section('title', __('Kuliah & Majlis Ilmu Akan Datang di Malaysia') . ' - ' . config('app.name'))
@section('meta_description', __('Terokai kuliah, ceramah, kelas, dan majlis ilmu akan datang di seluruh Malaysia. Tapis mengikut lokasi, tarikh, penceramah, dan topik.'))
@section('og_url', route('events.index'))
@section('og_image', asset('images/default-mosque-hero.png'))
@section('og_image_alt', __('Kuliah dan majlis ilmu akan datang di Malaysia'))
@section('og_image_width', '1024')
@section('og_image_height', '1024')

@push('head')
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
    $venueId = $this->venue_id;
    $gender = $this->gender;
    $childrenAllowed = $this->children_allowed;
    $isMuslimOnly = $this->is_muslim_only;
    $startsAfter = $this->starts_after;
    $startsBefore = $this->starts_before;
    $prayerTime = $this->prayer_time;
    $timingMode = $this->timing_mode;
    $startsTimeFrom = $this->starts_time_from;
    $startsTimeUntil = $this->starts_time_until;
    $timeScope = $this->time_scope ?? 'upcoming';
    $lat = $this->lat;
    $lng = $this->lng;
    $sort = $this->sort;
    $states = $this->states;
    $districts = $this->districts;
    $subdistricts = $this->subdistricts;
    $languageOptions = $this->languageOptions();
    $selectedAgeGroups = array_values(array_filter((array) $this->age_group));
    $selectedTopicIds = array_values(array_filter((array) $this->topic_ids));
    $selectedDomainTagIds = array_values(array_filter((array) $this->domain_tag_ids));
    $selectedSourceTagIds = array_values(array_filter((array) $this->source_tag_ids));
    $selectedIssueTagIds = array_values(array_filter((array) $this->issue_tag_ids));
    $selectedReferenceIds = array_values(array_filter((array) $this->reference_ids));
    $selectedSpeakerIds = array_values(array_filter((array) $this->speaker_ids));
    $selectedKeyPersonRoles = array_values(array_filter((array) $this->key_person_roles));
    $selectedModeratorIds = array_values(array_filter((array) $this->moderator_ids));
    $selectedImamIds = array_values(array_filter((array) $this->imam_ids));
    $selectedKhatibIds = array_values(array_filter((array) $this->khatib_ids));
    $selectedBilalIds = array_values(array_filter((array) $this->bilal_ids));
    $selectedEventTypes = array_values(array_filter((array) $this->event_type));
    $selectedEventFormats = array_values(array_filter((array) $this->event_format));
    $selectedLanguageCodes = array_values(array_filter((array) $this->language_codes));
    $selectedTopicOptions = $this->tagOptionLabels(
        \App\Enums\TagType::Discipline,
        $selectedTopicIds,
    );
    $selectedSpeakerOptions = $this->speakerOptionLabels($selectedSpeakerIds);
    $selectedModeratorOptions = $this->speakerOptionLabels($selectedModeratorIds);
    $selectedImamOptions = $this->speakerOptionLabels($selectedImamIds);
    $selectedKhatibOptions = $this->speakerOptionLabels($selectedKhatibIds);
    $selectedBilalOptions = $this->speakerOptionLabels($selectedBilalIds);
    $selectedDomainTagOptions = $this->tagOptionLabels(\App\Enums\TagType::Domain, $selectedDomainTagIds);
    $selectedSourceTagOptions = $this->tagOptionLabels(\App\Enums\TagType::Source, $selectedSourceTagIds);
    $selectedIssueTagOptions = $this->tagOptionLabels(\App\Enums\TagType::Issue, $selectedIssueTagIds);
    $selectedReferenceOptions = $this->referenceOptionLabels($selectedReferenceIds);
    $selectedInstitutionLabel = filled($institutionId) ? $this->institutionOptionLabel((string) $institutionId) : null;
    $selectedVenueLabel = filled($venueId) ? $this->venueOptionLabel((string) $venueId) : null;
    $selectedTopicLabels = collect($selectedTopicIds)
        ->map(fn (string $topicId): ?string => $selectedTopicOptions[$topicId] ?? null)
        ->filter()
        ->values();
    $selectedSpeakerLabels = collect($selectedSpeakerIds)
        ->map(fn (string $speakerId): ?string => $selectedSpeakerOptions[$speakerId] ?? null)
        ->filter()
        ->values();
    $selectedKeyPersonRoleLabels = collect($selectedKeyPersonRoles)
        ->map(fn (string $role): ?string => \App\Enums\EventKeyPersonRole::tryFrom($role)?->getLabel())
        ->filter()
        ->values();
    $selectedModeratorLabels = collect($selectedModeratorIds)
        ->map(fn (string $speakerId): ?string => $selectedModeratorOptions[$speakerId] ?? null)
        ->filter()
        ->values();
    $selectedImamLabels = collect($selectedImamIds)
        ->map(fn (string $speakerId): ?string => $selectedImamOptions[$speakerId] ?? null)
        ->filter()
        ->values();
    $selectedKhatibLabels = collect($selectedKhatibIds)
        ->map(fn (string $speakerId): ?string => $selectedKhatibOptions[$speakerId] ?? null)
        ->filter()
        ->values();
    $selectedBilalLabels = collect($selectedBilalIds)
        ->map(fn (string $speakerId): ?string => $selectedBilalOptions[$speakerId] ?? null)
        ->filter()
        ->values();
    $selectedDomainTagLabels = collect($selectedDomainTagIds)
        ->map(fn (string $tagId): ?string => $selectedDomainTagOptions[$tagId] ?? null)
        ->filter()
        ->values();
    $selectedSourceTagLabels = collect($selectedSourceTagIds)
        ->map(fn (string $tagId): ?string => $selectedSourceTagOptions[$tagId] ?? null)
        ->filter()
        ->values();
    $selectedIssueTagLabels = collect($selectedIssueTagIds)
        ->map(fn (string $tagId): ?string => $selectedIssueTagOptions[$tagId] ?? null)
        ->filter()
        ->values();
    $selectedReferenceLabels = collect($selectedReferenceIds)
        ->map(fn (string $referenceId): ?string => $selectedReferenceOptions[$referenceId] ?? null)
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
        'venue_id' => $venueId,
        'speaker_ids' => $selectedSpeakerIds,
        'key_person_roles' => $selectedKeyPersonRoles,
        'moderator_ids' => $selectedModeratorIds,
        'imam_ids' => $selectedImamIds,
        'khatib_ids' => $selectedKhatibIds,
        'bilal_ids' => $selectedBilalIds,
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
        'starts_time_from' => $startsTimeFrom,
        'starts_time_until' => $startsTimeUntil,
        'has_event_url' => $this->has_event_url,
        'has_live_url' => $this->has_live_url,
        'topic_ids' => $selectedTopicIds,
        'domain_tag_ids' => $selectedDomainTagIds,
        'source_tag_ids' => $selectedSourceTagIds,
        'issue_tag_ids' => $selectedIssueTagIds,
        'reference_ids' => $selectedReferenceIds,
        'lat' => filled($lat) && filled($lng) ? $lat : null,
        'lng' => filled($lat) && filled($lng) ? $lng : null,
        'radius_km' => filled($lat) && filled($lng) ? $this->radius_km : null,
        'sort' => $sort,
        'time_scope' => $this->time_scope,
    ], function (mixed $value): bool {
        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null && $value !== '';
    });

    $activeFilterCount = collect([
        filled($search),
        filled($stateId),
        filled($districtId),
        filled($subdistrictId),
        filled($institutionId),
        filled($venueId),
        count($selectedLanguageCodes) > 0,
        count($selectedEventTypes) > 0,
        count($selectedEventFormats) > 0,
        filled($gender),
        count($selectedAgeGroups) > 0,
        $childrenAllowed !== null,
        $isMuslimOnly !== null,
        count($selectedSpeakerIds) > 0,
        count($selectedKeyPersonRoles) > 0,
        count($selectedModeratorIds) > 0,
        count($selectedImamIds) > 0,
        count($selectedKhatibIds) > 0,
        count($selectedBilalIds) > 0,
        count($selectedTopicIds) > 0,
        count($selectedDomainTagIds) > 0,
        count($selectedSourceTagIds) > 0,
        count($selectedIssueTagIds) > 0,
        count($selectedReferenceIds) > 0,
        filled($startsAfter),
        filled($startsBefore),
        filled($prayerTime),
        filled($timingMode),
        filled($startsTimeFrom),
        filled($startsTimeUntil),
        $this->has_event_url !== null,
        $this->has_live_url !== null,
        $timeScope !== 'upcoming',
        filled($lat),
    ])->filter()->count();

    $hasActiveFilters = $activeFilterCount > 0;
    $searchShareUrl = $hasActiveFilters ? route('events.index', $savedSearchQuery) : null;
    $searchShareText = __('Explore these Majlis Ilmu search results on :app', ['app' => config('app.name')]);
    $searchShareData = $searchShareUrl !== null
        ? [
            'title' => __('Search Results'),
            'text' => __('Share these filtered results with others.'),
            'url' => $searchShareUrl,
            'sourceUrl' => $searchShareUrl,
            'shareText' => $searchShareText,
            'fallbackTitle' => __('Search Results'),
            'payloadEndpoint' => route('dawah-share.payload'),
        ]
        : null;
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
            <p class="text-slate-600 text-lg md:text-xl max-w-2xl mx-auto text-balance">
                {{ __('Discover classes, lectures, and community gatherings happening near you. Connect with knowledge seekers in your area.') }}
            </p>
        </div>
    </div>

    <div class="container mx-auto px-6 lg:px-12 -mt-8 relative z-10">
        <form wire:submit.prevent x-data="{
                    locating: false,
                    copiedShareLink: false,
                    shareData: @js($searchShareData),
                    attributedShareData: null,
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
                    async resolveShareData() {
                        if (! this.shareData) {
                            return null;
                        }

                        if (this.attributedShareData) {
                            return this.attributedShareData;
                        }

                        const params = new URLSearchParams({
                            url: this.shareData.sourceUrl,
                            text: this.shareData.shareText,
                            title: this.shareData.fallbackTitle,
                        });
                        const response = await fetch(`${this.shareData.payloadEndpoint}?${params.toString()}`, {
                            headers: {
                                Accept: 'application/json',
                            },
                        });

                        if (!response.ok) {
                            return this.shareData;
                        }

                        const payload = await response.json();
                        this.attributedShareData = {
                            ...this.shareData,
                            url: payload.url,
                        };

                        return this.attributedShareData;
                    },
                    async shareResults() {
                        const shareData = await this.resolveShareData();
                        if (!shareData) {
                            return;
                        }

                        if (navigator.share) {
                            navigator.share(shareData);

                            return;
                        }

                        this.copyShareLink();
                    },
                    async copyShareLink() {
                        const shareData = await this.resolveShareData();
                        if (!shareData) {
                            return;
                        }

                        if (! navigator.clipboard) {
                            window.prompt('{{ __("Copy this link:") }}', shareData.url);

                            return;
                        }

                        navigator.clipboard.writeText(shareData.url).then(() => {
                            this.copiedShareLink = true;
                            setTimeout(() => this.copiedShareLink = false, 2200);
                        }, () => {
                            window.prompt('{{ __("Copy this link:") }}', shareData.url);
                        });
                    },
                }" class="relative bg-white rounded-3xl shadow-xl shadow-slate-200/60 border border-slate-200 p-6 md:p-8">

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
                        placeholder="{{ __('Cari mengikut tajuk...') }}"
                        class="w-full h-14 pl-12 pr-4 rounded-2xl border-2 border-slate-200 bg-white shadow-lg shadow-slate-200/60 font-medium text-slate-900 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all placeholder:text-slate-400"
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

                        @if(! $showAdvancedFiltersPanel)
                            <label class="flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 shadow-sm">
                                <span>{{ __('Radius (km)') }}</span>
                                <input
                                    type="number"
                                    min="1"
                                    max="1000"
                                    step="1"
                                    wire:model.live="filterData.radius_km"
                                    class="h-8 w-20 rounded-lg border border-slate-200 px-2 text-sm font-medium text-slate-900 focus:border-emerald-500 focus:outline-none"
                                >
                            </label>
                        @endif
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-slate-600">{{ __('Sort:') }}</span>
                    <div class="flex bg-slate-100 p-1 rounded-xl">
                        <button type="button" wire:click="setSort('time')"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $sort === 'time' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-800' }}">
                            {{ __('Latest') }}
                        </button>
                        <button type="button" wire:click="setSort('relevance')"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $sort === 'relevance' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-800' }}">
                            {{ __('Relevance') }}
                        </button>
                        @if($lat)
                            <button type="button" wire:click="setSort('distance')"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $sort === 'distance' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-800' }}">
                                {{ __('Distance') }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mi-filter-shell space-y-4">
                <button type="button" wire:click="toggleAdvancedFiltersPanel"
                    class="flex w-full items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-left transition hover:border-emerald-300 hover:bg-emerald-50/60 focus:outline-none focus:ring-4 focus:ring-emerald-500/10">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ __('Advanced Filters') }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $showAdvancedFiltersPanel
                                ? __('Hide the full filter panel once you are done refining results.')
                                : __('Open the full filter panel only when you need detailed filtering.') }}
                        </p>
                    </div>

                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600">
                        @if($activeFilterCount > 0)
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-700">{{ $activeFilterCount }}</span>
                        @endif

                        {{ $showAdvancedFiltersPanel ? __('Hide') : __('Show') }}
                    </span>
                </button>

                @if($showAdvancedFiltersPanel)
                    <livewire:pages.events.advanced-filters-panel :filters="$filterData" />
                @endif
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
                            {{ $subdistricts->firstWhere('id', $subdistrictId)?->name ?? __('Bandar / Mukim / Zon') }}
                        </span>
                    @endif

                    @if($institutionId)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ $selectedInstitutionLabel ?? __('Institution') }}
                        </span>
                    @endif

                    @if($venueId)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ $selectedVenueLabel ?? __('Tempat') }}
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

                    @if($startsTimeFrom)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-100">
                            {{ __('Masa Dari') }} {{ \Illuminate\Support\Carbon::make($startsTimeFrom)?->format('h:i A') ?? $startsTimeFrom }}
                        </span>
                    @endif

                    @if($startsTimeUntil)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-100">
                            {{ __('Masa Hingga') }} {{ \Illuminate\Support\Carbon::make($startsTimeUntil)?->format('h:i A') ?? $startsTimeUntil }}
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

                    @foreach($selectedAgeGroups as $ageGroup)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ $ageGroupLabels[$ageGroup] ?? str($ageGroup)->replace('_', ' ')->headline() }}
                        </span>
                    @endforeach

                    @foreach($selectedDomainTagLabels as $domainTagLabel)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-cyan-50 text-cyan-700 border border-cyan-100">
                            {{ $domainTagLabel }}
                        </span>
                    @endforeach

                    @foreach($selectedTopicLabels as $topicLabel)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                            {{ $topicLabel }}
                        </span>
                    @endforeach

                    @foreach($selectedSourceTagLabels as $sourceTagLabel)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100">
                            {{ $sourceTagLabel }}
                        </span>
                    @endforeach

                    @foreach($selectedIssueTagLabels as $issueTagLabel)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-100">
                            {{ $issueTagLabel }}
                        </span>
                    @endforeach

                    @foreach($selectedReferenceLabels as $referenceLabel)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-100">
                            {{ $referenceLabel }}
                        </span>
                    @endforeach

                    @foreach($selectedSpeakerLabels as $speakerLabel)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ $speakerLabel }}
                        </span>
                    @endforeach

                    @foreach($selectedKeyPersonRoleLabels as $roleLabel)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-orange-50 text-orange-700 border border-orange-100">
                            {{ $roleLabel }}
                        </span>
                    @endforeach

                    @foreach($selectedModeratorLabels as $moderatorLabel)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-orange-50 text-orange-700 border border-orange-100">
                            {{ __('Moderator') }}: {{ $moderatorLabel }}
                        </span>
                    @endforeach

                    @foreach($selectedImamLabels as $imamLabel)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-orange-50 text-orange-700 border border-orange-100">
                            {{ __('Imam') }}: {{ $imamLabel }}
                        </span>
                    @endforeach

                    @foreach($selectedKhatibLabels as $khatibLabel)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-orange-50 text-orange-700 border border-orange-100">
                            {{ __('Khatib') }}: {{ $khatibLabel }}
                        </span>
                    @endforeach

                    @foreach($selectedBilalLabels as $bilalLabel)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-orange-50 text-orange-700 border border-orange-100">
                            {{ __('Bilal') }}: {{ $bilalLabel }}
                        </span>
                    @endforeach

                    @if($startsAfter)
                        @php
                            $startsAfterLabel = \Illuminate\Support\Carbon::make($startsAfter)?->format('d M Y') ?? $startsAfter;
                        @endphp
                        <span
                            class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ __('Held from') }} {{ $startsAfterLabel }}
                        </span>
                    @endif

                    @if($startsBefore)
                        @php
                            $startsBeforeLabel = \Illuminate\Support\Carbon::make($startsBefore)?->format('d M Y') ?? $startsBefore;
                        @endphp
                        <span
                            class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            {{ __('Held until') }} {{ $startsBeforeLabel }}
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

                    <div class="ml-auto flex flex-wrap items-center gap-3">
                        <button type="button" @click="shareResults()"
                            class="text-xs font-bold text-slate-600 hover:text-emerald-700 hover:underline">
                            {{ __('Share These Results') }}
                        </button>
                        <button type="button" @click="copyShareLink()"
                            class="text-xs font-bold text-slate-600 hover:text-emerald-700 hover:underline">
                            {{ __('Copy Share Link') }}
                        </button>
                        <a href="{{ route('saved-searches.index', $savedSearchQuery) }}" wire:navigate
                            class="text-xs font-bold text-emerald-600 hover:text-emerald-700 hover:underline">
                            {{ __('Save This Search') }}
                        </a>
                    </div>

                    <button type="button" wire:click="clearAllFilters"
                        class="text-xs font-bold text-red-500 hover:text-red-600 hover:underline">
                        {{ __('Clear All Filters') }}
                    </button>
                </div>

                <div x-show="copiedShareLink" x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="mb-4 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ __('Link copied to clipboard!') }}
                </div>
            @endif
        </form>

        <!-- Results Grid -->
        <div class="mt-16 relative">
            @php
                $events = $this->events;
            @endphp

            <div wire:loading.delay.short
                wire:target="filterData,setLocation,clearLocation,clearAllFilters,setSort">
                <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                    @foreach(range(1, 6) as $index)
                        <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-md animate-pulse">
                            <div class="relative aspect-[3/2] bg-slate-200">
                                <div class="absolute left-4 top-4 h-14 w-14 rounded-xl bg-white/80"></div>
                                <div class="absolute bottom-4 left-4 h-6 w-20 rounded-full bg-white/70"></div>
                            </div>

                            <div class="space-y-4 p-6">
                                <div class="space-y-2">
                                    <div class="h-6 w-5/6 rounded-full bg-slate-200"></div>
                                    <div class="h-6 w-2/3 rounded-full bg-slate-200"></div>
                                </div>

                                <div class="space-y-3">
                                    <div class="flex items-start gap-2.5">
                                        <div class="mt-1 h-4 w-4 rounded-full bg-emerald-100"></div>
                                        <div class="w-full space-y-2">
                                            <div class="h-4 w-3/4 rounded-full bg-slate-200"></div>
                                            <div class="h-3 w-1/2 rounded-full bg-slate-100"></div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2.5">
                                        <div class="h-4 w-4 rounded-full bg-emerald-100"></div>
                                        <div class="h-4 w-2/3 rounded-full bg-slate-200"></div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>

            <div wire:loading.remove wire:target="filterData,setLocation,clearLocation,clearAllFilters,setSort">
            @if($events->isEmpty())
                <div class="flex flex-col items-center justify-center py-24 text-center">
                    <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mb-6">
                        <svg class="w-10 h-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="font-heading text-xl font-bold text-slate-900 mb-2">{{ __('No events found') }}</h3>
                    <p class="text-slate-600 max-w-md">
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
                            $hierarchyParts = \App\Support\Location\AddressHierarchyFormatter::parts($addressModel);

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
                            class="group flex flex-col bg-white rounded-3xl overflow-hidden shadow-md hover:shadow-xl hover:shadow-emerald-900/8 hover:-translate-y-1 transition-all duration-300 border border-slate-200">
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
                                        <div class="text-xs font-bold uppercase tracking-wider text-slate-600">
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
                                    @elseif($event->status instanceof \App\States\EventStatus\Cancelled)
                                        <span class="inline-flex items-center gap-1 bg-rose-600/90 backdrop-blur-md text-white px-2.5 py-1 rounded-full text-[0.65rem] font-bold shadow-lg uppercase tracking-wide">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m-12.728 0a9 9 0 010-12.728m12.728 12.728L5.636 5.636"/></svg>
                                            {{ __('Dibatalkan') }}
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
</div>

@php
    $dashboardPageLabel = in_array(app()->getLocale(), ['ms', 'ms_MY'], true) ? 'Dashboard' : __('Dashboard');
@endphp
@section('title', $dashboardPageLabel . ' - ' . config('app.name'))

@php
    $user = auth()->user();
    $summary = $this->summaryStats;
    $savedEvents = $this->savedEvents;
    $goingEvents = $this->goingEvents;
    $followingSpeakers = $this->followingSpeakers;
    $followingReferences = $this->followingReferences;
    $followingInstitutions = $this->followingInstitutions;
    $paginatedGoingEvents = $this->paginatedGoingEvents;
    $paginatedSavedEvents = $this->paginatedSavedEvents;
    $paginatedFollowingSpeakers = $this->paginatedFollowingSpeakers;
    $paginatedFollowingReferences = $this->paginatedFollowingReferences;
    $paginatedFollowingInstitutions = $this->paginatedFollowingInstitutions;
    $dawahImpactSummary = $this->dawahImpactSummary;
    $calendarFilters = $this->calendarFilters;
    $calendarFilterList = collect($calendarFilters)->map(
        fn (array $filter, string $key): array => ['key' => $key] + $filter,
    )->values()->all();
    $initialCalendarFilters = collect($calendarFilters)->mapWithKeys(
        fn (array $filter, string $key): array => [$key => true],
    )->all();
    $calendarEntriesByDate = $this->calendarEntriesByDate;
    $firstName = is_string($user?->name ?? null) ? explode(' ', $user->name)[0] : __('Friend');
    $followingCount = $followingSpeakers->count() + $followingReferences->count() + $followingInstitutions->count();
    $calendarEntries = collect($this->calendarEntries);
    $nowTimestamp = now(\App\Support\Timezone\UserDateTimeFormatter::resolveTimezone())->getTimestamp();
    $mobileCalendarEntries = $calendarEntries
        ->filter(fn (array $entry): bool => is_int($entry['starts_at_ts'] ?? null) && $entry['starts_at_ts'] >= $nowTimestamp)
        ->sortBy('starts_at_ts')
        ->take(4)
        ->values();
    $plannerSummaryItems = [
        [
            'label' => __('Saved'),
            'count' => $summary['saved_count'],
            'description' => __('Bookmarks and watchlist.'),
            'url' => '#planner-saved',
            'theme' => 'amber',
            'icon' => 'M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z',
        ],
        [
            'label' => __('Going'),
            'count' => $summary['going_count'],
            'description' => __('Events you plan to attend.'),
            'url' => '#planner-going',
            'theme' => 'emerald',
            'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        [
            'label' => __('Following'),
            'count' => $followingCount,
            'description' => __('Speakers, references, and institutions.'),
            'url' => '#planner-speakers',
            'theme' => 'sky',
            'icon' => 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z',
        ],
    ];
    $plannerSummaryThemeClasses = [
        'amber' => [
            'icon' => 'bg-amber-100 text-amber-700',
            'label' => 'text-amber-700',
        ],
        'emerald' => [
            'icon' => 'bg-emerald-100 text-emerald-700',
            'label' => 'text-emerald-700',
        ],
        'sky' => [
            'icon' => 'bg-sky-100 text-sky-700',
            'label' => 'text-sky-700',
        ],
    ];
    $impactMetrics = [
        ['label' => __('Visits'), 'value' => $dawahImpactSummary['visits'], 'description' => __('Attributed visits')],
        ['label' => __('Unique Visitors'), 'value' => $dawahImpactSummary['unique_visitors'], 'description' => __('People reached')],
        ['label' => __('Signups'), 'value' => $dawahImpactSummary['signups'], 'description' => __('New accounts')],
        ['label' => __('Responses'), 'value' => $dawahImpactSummary['total_outcomes'], 'description' => __('Beneficial actions')],
    ];
    $quickActions = [
        ['label' => __('Browse events'), 'url' => route('events.index')],
        ['label' => __('Inbox'), 'url' => route('dashboard.notifications')],
        ['label' => __('Settings'), 'url' => route('dashboard.account-settings')],
    ];
    $translateStatusLabel = static function (string $status): string {
        $translated = __($status);

        if ($translated !== $status) {
            return $translated;
        }

        return str($status)->replace('_', ' ')->headline()->toString();
    };
    $eventDateTimeLabel = static function (mixed $date): string {
        if (! $date instanceof \Carbon\CarbonInterface) {
            return __('Time to be confirmed');
        }

        return \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($date, 'd M')
            . ', ' . \App\Support\Timezone\UserDateTimeFormatter::format($date, 'h:i A');
    };
    $eventWorkflowStatusLabel = static function (string $status) use ($translateStatusLabel): string {
        return match ($status) {
            'pending' => __('Menunggu Kelulusan'),
            default => $translateStatusLabel($status),
        };
    };
    $shouldShowEventStatusBadge = static function (string $status, bool $isOwnSubmission = false): bool {
        return $status !== 'approved' || $isOwnSubmission;
    };
    $eventStatusClass = static fn (string $status): string => match ($status) {
        'approved' => 'bg-emerald-100 text-emerald-700',
        'pending', 'needs_changes' => 'bg-amber-100 text-amber-700',
        'cancelled', 'rejected' => 'bg-rose-100 text-rose-700',
        'draft' => 'bg-slate-200 text-slate-700',
        default => 'bg-slate-200 text-slate-700',
    };
    $entityStatusClass = static fn (string $status): string => match ($status) {
        'verified' => 'bg-emerald-100 text-emerald-700',
        'pending' => 'bg-amber-100 text-amber-700',
        'inactive', 'disabled' => 'bg-slate-200 text-slate-700',
        default => 'bg-slate-200 text-slate-700',
    };
@endphp

<div class="min-h-screen bg-slate-50 py-8 pb-32 md:py-10">
    <div class="container mx-auto px-6 lg:px-12">
        <div class="mx-auto max-w-7xl space-y-8">
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-5 md:px-6 lg:px-8">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-xs font-semibold text-emerald-700">{{ $dashboardPageLabel }}</p>
                            <h1 class="mt-2 font-heading text-3xl font-bold text-slate-950 md:text-4xl">
                                {{ __('Assalamualaikum, :name', ['name' => $firstName]) }}
                            </h1>
                            <p class="mt-3 max-w-2xl text-sm text-slate-500">
                                {{ __('Track saved events, attendance plans, and followed knowledge sources from one place.') }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @foreach($quickActions as $action)
                                <a href="{{ $action['url'] }}" wire:navigate
                                    class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700">
                                    {{ $action['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="grid gap-px bg-slate-200 md:grid-cols-3">
                    @foreach($plannerSummaryItems as $item)
                        @php($themeClasses = $plannerSummaryThemeClasses[$item['theme']] ?? $plannerSummaryThemeClasses['emerald'])
                        <a href="{{ $item['url'] }}"
                            class="group bg-white p-5 transition hover:bg-slate-50 md:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold {{ $themeClasses['label'] }}">{{ $item['label'] }}</p>
                                    <p class="mt-2 text-3xl font-black text-slate-950">{{ $item['count'] }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $item['description'] }}</p>
                                </div>
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg {{ $themeClasses['icon'] }}">
                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                                    </svg>
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:p-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-500">{{ __('Dawah Impact') }}</p>
                        <h2 class="mt-1 font-heading text-2xl font-bold text-slate-950">{{ __('Share outcomes') }}</h2>
                        <p class="mt-2 max-w-2xl text-sm text-slate-500">{{ __('Private metrics from visits and responses after your shared links are opened.') }}</p>
                    </div>

                    <a href="{{ route('dashboard.dawah-impact') }}" wire:navigate
                        class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                        {{ __('Open impact dashboard') }}
                    </a>
                </div>

                <div class="mt-5 grid overflow-hidden rounded-xl border border-slate-200 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach($impactMetrics as $metric)
                        <div class="border-b border-slate-200 bg-slate-50/60 p-4 last:border-b-0 sm:odd:border-r xl:border-b-0 xl:border-r xl:last:border-r-0">
                            <p class="text-sm font-semibold text-slate-500">{{ $metric['label'] }}</p>
                            <p class="mt-2 text-3xl font-black text-slate-950">{{ number_format($metric['value']) }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $metric['description'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Desktop/tablet planner calendar: intentionally hidden on mobile, visible on non-mobile (md+) only. --}}
            <div
                x-data="{
                    locale: '{{ str_replace('_', '-', app()->getLocale()) }}',
                    calendarMonth: new Date().getMonth(),
                    calendarYear: new Date().getFullYear(),
                    filters: {{ \Illuminate\Support\Js::from($initialCalendarFilters) }},
                    filterList: {{ \Illuminate\Support\Js::from($calendarFilterList) }},
                    entriesByDate: {{ \Illuminate\Support\Js::from($calendarEntriesByDate) }},
                    monthLabel() {
                        return new Date(this.calendarYear, this.calendarMonth).toLocaleDateString(this.locale, { month: 'long', year: 'numeric' });
                    },
                    toggleFilter(key) {
                        this.filters[key] = !this.filters[key];

                        if (Object.values(this.filters).every(value => value === false)) {
                            this.filters[key] = true;
                        }
                    },
                    filteredEntriesForDate(key) {
                        return (this.entriesByDate[key] || []).filter(entry => (entry.roles || []).some(role => this.filters[role] ?? false));
                    },
                    calendarCells() {
                        const first = new Date(this.calendarYear, this.calendarMonth, 1);
                        const lastDay = new Date(this.calendarYear, this.calendarMonth + 1, 0).getDate();
                        let startDay = first.getDay();
                        startDay = startDay === 0 ? 6 : startDay - 1;
                        const cells = [];

                        for (let i = 0; i < startDay; i++) {
                            cells.push({ day: null, key: `empty-${i}` });
                        }

                        for (let day = 1; day <= lastDay; day++) {
                            const key = `${this.calendarYear}-${String(this.calendarMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                            cells.push({
                                day,
                                key,
                                entries: this.filteredEntriesForDate(key),
                            });
                        }

                        return cells;
                    },
                }"
                class="hidden md:block"
            >
                <section id="planner-calendar" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                    <div class="flex flex-col gap-5">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="text-xs font-semibold text-emerald-700">{{ __('Overview Calendar') }}</p>
                                <h2 class="mt-2 font-heading text-2xl font-bold text-slate-950">{{ __('See your month at a glance') }}</h2>
                                <p class="mt-2 max-w-2xl text-sm text-slate-500">{{ __('Saved and going dates for this month.') }}</p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <template x-for="filter in filterList" :key="filter.key">
                                    <button type="button" @click="toggleFilter(filter.key)"
                                        class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-xs font-semibold transition"
                                        :class="filters[filter.key] ? filter.active_button_class : filter.inactive_button_class">
                                        <span x-text="filter.label"></span>
                                        <span class="rounded bg-white/20 px-2 py-0.5 text-[11px] font-bold" x-text="filter.count"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-slate-200">
                            <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-4 py-3 sm:px-5">
                                <button type="button"
                                    aria-label="{{ __('Previous month') }}"
                                    @click="calendarMonth--; if (calendarMonth < 0) { calendarMonth = 11; calendarYear--; }"
                                    class="flex size-9 items-center justify-center rounded-lg text-slate-400 transition hover:bg-white hover:text-slate-700">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 19.5L8.25 12l7.5-7.5" />
                                    </svg>
                                </button>
                                <h3 class="text-sm font-bold text-slate-700" x-text="monthLabel()"></h3>
                                <button type="button"
                                    aria-label="{{ __('Next month') }}"
                                    @click="calendarMonth++; if (calendarMonth > 11) { calendarMonth = 0; calendarYear++; }"
                                    class="flex size-9 items-center justify-center rounded-lg text-slate-400 transition hover:bg-white hover:text-slate-700">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                    </svg>
                                </button>
                            </div>

                            <div class="grid grid-cols-7 border-b border-slate-100 bg-slate-50/50">
                                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayLabel)
                                    <div class="py-2 text-center text-[10px] font-bold text-slate-400">{{ __($dayLabel) }}</div>
                                @endforeach
                            </div>

                            <div class="grid grid-cols-7">
                                <template x-for="cell in calendarCells()" :key="cell.key">
                                    <div class="min-h-28 border-b border-r border-slate-100 p-1.5 sm:min-h-32 sm:p-2"
                                        :class="cell.day === null ? 'bg-slate-50/40' : 'bg-white'">
                                        <template x-if="cell.day !== null">
                                            <div class="flex h-full flex-col">
                                                <div class="flex items-center">
                                                    <span class="text-xs font-bold text-slate-500" x-text="cell.day"></span>
                                                </div>

                                                <div class="mt-1 flex-1 space-y-1">
                                                    <template x-for="entry in cell.entries.slice(0, 2)" :key="entry.key">
                                                        <a :href="entry.url || '#'" class="block rounded-lg border px-2 py-1.5 text-[10px] leading-snug transition"
                                                            :class="entry.panel_class + (entry.url ? ' hover:shadow-sm' : ' pointer-events-none')">
                                                            <p class="line-clamp-2 font-semibold" x-text="entry.title"></p>
                                                        </a>
                                                    </template>
                                                    <template x-if="cell.entries.length > 2">
                                                        <div class="px-1 text-[10px] font-semibold text-slate-400" x-text="'+' + (cell.entries.length - 2) + ' {{ __('more') }}'"></div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:hidden">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold text-emerald-700">{{ __('Overview Calendar') }}</p>
                        <h2 class="mt-1 font-heading text-xl font-bold text-slate-950">{{ __('Next on your planner') }}</h2>
                    </div>
                    <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">{{ $calendarEntries->count() }}</span>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse($mobileCalendarEntries as $entry)
                        <a href="{{ $entry['url'] ?? '#' }}" wire:navigate
                            class="block rounded-xl border border-slate-200 p-3 transition hover:border-emerald-200 hover:shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-950">{{ $entry['title'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $entry['time_label'] }} · {{ $entry['secondary_label'] }}</p>
                                </div>
                                <span class="shrink-0 rounded-lg px-2 py-1 text-[11px] font-semibold {{ $eventStatusClass((string) $entry['status']) }}">
                                    {{ $entry['status_label'] }}
                                </span>
                            </div>
                        </a>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-5 text-sm text-slate-500">
                            {{ __('Mark events as going or save them to start filling your planner.') }}
                        </div>
                    @endforelse
                </div>
            </section>

            <div
                x-data="{
                    plannerSections: ['planner-saved', 'planner-going', 'planner-speakers', 'planner-references', 'planner-institutions'],
                    activePlannerSection: 'planner-saved',
                    init() {
                        const initialHash = window.location.hash.replace('#', '');

                        if (this.plannerSections.includes(initialHash)) {
                            this.activePlannerSection = initialHash;
                        }

                        window.addEventListener('hashchange', () => {
                            const nextHash = window.location.hash.replace('#', '');

                            if (this.plannerSections.includes(nextHash)) {
                                this.activePlannerSection = nextHash;
                            }
                        });
                    },
                }"
                class="flex flex-col gap-8 lg:flex-row lg:items-start"
            >
                <aside class="shrink-0 lg:sticky lg:top-6 lg:w-64">
                    <div class="flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm lg:hidden">
                        <a href="#planner-saved"
                            @click="activePlannerSection = 'planner-saved'"
                            :aria-current="activePlannerSection === 'planner-saved' ? 'page' : null"
                            class="min-w-28 rounded-xl border border-amber-100 bg-amber-50 px-3 py-3 text-left text-sm font-semibold text-amber-800 transition hover:border-amber-200 hover:bg-amber-100"
                            :class="activePlannerSection === 'planner-saved' ? 'ring-2 ring-amber-200 border-amber-300 bg-amber-100 shadow-sm' : ''">
                            <span class="block">{{ __('Saved') }}</span>
                            <span class="mt-1 inline-flex rounded bg-white px-2 py-0.5 text-xs font-bold text-amber-700">{{ $savedEvents->count() }}</span>
                        </a>
                        <a href="#planner-going"
                            @click="activePlannerSection = 'planner-going'"
                            :aria-current="activePlannerSection === 'planner-going' ? 'page' : null"
                            class="min-w-28 rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-3 text-left text-sm font-semibold text-emerald-800 transition hover:border-emerald-200 hover:bg-emerald-100"
                            :class="activePlannerSection === 'planner-going' ? 'ring-2 ring-emerald-200 border-emerald-300 bg-emerald-100 shadow-sm' : ''">
                            <span class="block">{{ __('Going') }}</span>
                            <span class="mt-1 inline-flex rounded bg-white px-2 py-0.5 text-xs font-bold text-emerald-700">{{ $goingEvents->count() }}</span>
                        </a>
                        <a href="#planner-speakers"
                            @click="activePlannerSection = 'planner-speakers'"
                            :aria-current="activePlannerSection === 'planner-speakers' ? 'page' : null"
                            class="min-w-28 rounded-xl border border-sky-100 bg-sky-50 px-3 py-3 text-left text-sm font-semibold text-sky-800 transition hover:border-sky-200 hover:bg-sky-100"
                            :class="activePlannerSection === 'planner-speakers' ? 'ring-2 ring-sky-200 border-sky-300 bg-sky-100 shadow-sm' : ''">
                            <span class="block">{{ __('Speakers') }}</span>
                            <span class="mt-1 inline-flex rounded bg-white px-2 py-0.5 text-xs font-bold text-sky-700">{{ $followingSpeakers->count() }}</span>
                        </a>
                        <a href="#planner-references"
                            @click="activePlannerSection = 'planner-references'"
                            :aria-current="activePlannerSection === 'planner-references' ? 'page' : null"
                            class="min-w-28 rounded-xl border border-violet-100 bg-violet-50 px-3 py-3 text-left text-sm font-semibold text-violet-800 transition hover:border-violet-200 hover:bg-violet-100"
                            :class="activePlannerSection === 'planner-references' ? 'ring-2 ring-violet-200 border-violet-300 bg-violet-100 shadow-sm' : ''">
                            <span class="block">{{ __('References') }}</span>
                            <span class="mt-1 inline-flex rounded bg-white px-2 py-0.5 text-xs font-bold text-violet-700">{{ $followingReferences->count() }}</span>
                        </a>
                        <a href="#planner-institutions"
                            @click="activePlannerSection = 'planner-institutions'"
                            :aria-current="activePlannerSection === 'planner-institutions' ? 'page' : null"
                            class="min-w-28 rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-3 text-left text-sm font-semibold text-indigo-800 transition hover:border-indigo-200 hover:bg-indigo-100"
                            :class="activePlannerSection === 'planner-institutions' ? 'ring-2 ring-indigo-200 border-indigo-300 bg-indigo-100 shadow-sm' : ''">
                            <span class="block">{{ __('Institutions') }}</span>
                            <span class="mt-1 inline-flex rounded bg-white px-2 py-0.5 text-xs font-bold text-indigo-700">{{ $followingInstitutions->count() }}</span>
                        </a>
                    </div>

                    <div class="hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:block">
                        <div class="border-b border-slate-100 px-4 py-4">
                            <p class="text-xs font-semibold text-slate-500">{{ __('Planner menu') }}</p>
                            <h2 class="mt-1 font-heading text-xl font-bold text-slate-950">{{ __('Jump to a section') }}</h2>
                        </div>

                        <nav class="space-y-1 p-2">
                            <a href="#planner-saved"
                                @click="activePlannerSection = 'planner-saved'"
                                :aria-current="activePlannerSection === 'planner-saved' ? 'page' : null"
                                class="group flex items-center justify-between gap-3 rounded-lg px-3 py-3 transition hover:bg-amber-50"
                                :class="activePlannerSection === 'planner-saved' ? 'bg-amber-50 ring-1 ring-amber-200' : ''">
                                <span class="flex items-center gap-3">
                                    <span class="flex size-10 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                                        </svg>
                                    </span>
                                    <span>
                                        <span class="block text-sm font-semibold text-slate-900">{{ __('Saved') }}</span>
                                        <span class="block text-xs text-slate-500">{{ __('Bookmarks and watchlist.') }}</span>
                                    </span>
                                </span>
                                <span class="rounded bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">{{ $savedEvents->count() }}</span>
                            </a>
                            <a href="#planner-going"
                                @click="activePlannerSection = 'planner-going'"
                                :aria-current="activePlannerSection === 'planner-going' ? 'page' : null"
                                class="group flex items-center justify-between gap-3 rounded-lg px-3 py-3 transition hover:bg-emerald-50"
                                :class="activePlannerSection === 'planner-going' ? 'bg-emerald-50 ring-1 ring-emerald-200' : ''">
                                <span class="flex items-center gap-3">
                                    <span class="flex size-10 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </span>
                                    <span>
                                        <span class="block text-sm font-semibold text-slate-900">{{ __('Going') }}</span>
                                        <span class="block text-xs text-slate-500">{{ __('Events you plan to attend.') }}</span>
                                    </span>
                                </span>
                                <span class="rounded bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">{{ $goingEvents->count() }}</span>
                            </a>
                            <a href="#planner-speakers"
                                @click="activePlannerSection = 'planner-speakers'"
                                :aria-current="activePlannerSection === 'planner-speakers' ? 'page' : null"
                                class="group flex items-center justify-between gap-3 rounded-lg px-3 py-3 transition hover:bg-sky-50"
                                :class="activePlannerSection === 'planner-speakers' ? 'bg-sky-50 ring-1 ring-sky-200' : ''">
                                <span class="flex items-center gap-3">
                                    <span class="flex size-10 items-center justify-center rounded-lg bg-sky-100 text-sky-700">
                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                    </span>
                                    <span>
                                        <span class="block text-sm font-semibold text-slate-900">{{ __('Speakers') }}</span>
                                        <span class="block text-xs text-slate-500">{{ __('People you follow.') }}</span>
                                    </span>
                                </span>
                                <span class="rounded bg-sky-50 px-2.5 py-1 text-xs font-bold text-sky-700">{{ $followingSpeakers->count() }}</span>
                            </a>
                            <a href="#planner-references"
                                @click="activePlannerSection = 'planner-references'"
                                :aria-current="activePlannerSection === 'planner-references' ? 'page' : null"
                                class="group flex items-center justify-between gap-3 rounded-lg px-3 py-3 transition hover:bg-violet-50"
                                :class="activePlannerSection === 'planner-references' ? 'bg-violet-50 ring-1 ring-violet-200' : ''">
                                <span class="flex items-center gap-3">
                                    <span class="flex size-10 items-center justify-center rounded-lg bg-violet-100 text-violet-700">
                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v11.494m0-11.494C10.832 5.405 9.414 5 8 5.5S5.168 7.086 4 8.5v11c1.168-1.414 2.586-2 4-2.5s2.832-.905 4-1.753m0-9.247c1.168-.848 2.586-1.253 4-1.753s2.832-.905 4-1.753v11c-1.168 1.414-2.586 2-4 2.5s-2.832.905-4 1.753" />
                                        </svg>
                                    </span>
                                    <span>
                                        <span class="block text-sm font-semibold text-slate-900">{{ __('References') }}</span>
                                        <span class="block text-xs text-slate-500">{{ __('Books and source materials.') }}</span>
                                    </span>
                                </span>
                                <span class="rounded bg-violet-50 px-2.5 py-1 text-xs font-bold text-violet-700">{{ $followingReferences->count() }}</span>
                            </a>
                            <a href="#planner-institutions"
                                @click="activePlannerSection = 'planner-institutions'"
                                :aria-current="activePlannerSection === 'planner-institutions' ? 'page' : null"
                                class="group flex items-center justify-between gap-3 rounded-lg px-3 py-3 transition hover:bg-indigo-50"
                                :class="activePlannerSection === 'planner-institutions' ? 'bg-indigo-50 ring-1 ring-indigo-200' : ''">
                                <span class="flex items-center gap-3">
                                    <span class="flex size-10 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700">
                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V8l7-4 7 4v13M9 21v-7h6v7M9 11h.01M9 14h.01M15 11h.01M15 14h.01" />
                                        </svg>
                                    </span>
                                    <span>
                                        <span class="block text-sm font-semibold text-slate-900">{{ __('Institutions') }}</span>
                                        <span class="block text-xs text-slate-500">{{ __('Organizations you follow.') }}</span>
                                    </span>
                                </span>
                                <span class="rounded bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700">{{ $followingInstitutions->count() }}</span>
                            </a>
                        </nav>
                    </div>
                </aside>

                <div class="min-w-0 flex-1 space-y-6">
                    <section id="planner-saved" x-show="activePlannerSection === 'planner-saved'" x-cloak class="rounded-2xl border border-amber-200/70 bg-white p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="font-heading text-xl font-bold text-slate-950">{{ __('Saved') }}</h2>
                                <p class="mt-2 text-sm text-slate-500">{{ __('Bookmarks that may need a final attendance decision.') }}</p>
                            </div>
                            <span class="rounded-lg bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">{{ $savedEvents->count() }}</span>
                        </div>
                        @if($savedEvents->isEmpty())
                            <div class="mt-6 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
                                <p>{{ __('No saved events yet.') }}</p>
                                <a href="{{ route('events.index') }}" wire:navigate class="mt-4 inline-flex rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">
                                    {{ __('Browse events') }}
                                </a>
                            </div>
                        @else
                            <div class="mt-6 space-y-3">
                                @foreach($paginatedSavedEvents as $event)
                                    @php($eventHasPoster = $event->hasMedia('poster'))
                                    <a href="{{ route('events.show', $event) }}" wire:navigate class="group flex gap-4 rounded-xl border border-slate-200 p-3 transition hover:border-amber-200 hover:shadow-md">
                                        <div class="size-20 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                                            <img src="{{ $event->card_image_url }}" alt="{{ $event->title }}" loading="lazy" class="h-full w-full transition duration-500 group-hover:scale-105 {{ $eventHasPoster ? 'object-contain bg-slate-100' : 'object-cover' }}">
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-3">
                                                <h3 class="min-w-0 flex-1 break-words font-semibold text-slate-900 transition group-hover:text-amber-700">{{ $event->title }}</h3>
                                                @if($shouldShowEventStatusBadge((string) $event->status))
                                                    <span class="inline-flex shrink-0 rounded-lg px-2.5 py-1 text-[11px] font-semibold {{ $eventStatusClass((string) $event->status) }}">
                                                        {{ $eventWorkflowStatusLabel((string) $event->status) }}
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="mt-1 text-sm text-slate-600">{{ $eventDateTimeLabel($event->starts_at) }}</p>
                                            <p class="mt-1 text-sm text-slate-500">{{ $event->venue?->name ?? $event->institution?->name ?? __('Online / TBD') }}</p>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                            @if($paginatedSavedEvents->hasPages())
                                <div class="mt-5">
                                    {{ $paginatedSavedEvents->links(data: ['scrollTo' => '#planner-saved']) }}
                                </div>
                            @endif
                        @endif
                    </section>

                    <section id="planner-going" x-show="activePlannerSection === 'planner-going'" x-cloak class="rounded-2xl border border-emerald-200/70 bg-white p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="font-heading text-xl font-bold text-slate-950">{{ __('Going') }}</h2>
                                <p class="mt-2 text-sm text-slate-500">{{ __('Your strongest attendance signal. These should be easy to act on.') }}</p>
                            </div>
                            <span class="rounded-lg bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">{{ $goingEvents->count() }}</span>
                        </div>
                        @if($goingEvents->isEmpty())
                            <div class="mt-6 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
                                <p>{{ __('Nothing marked as going yet.') }}</p>
                                <a href="{{ route('events.index') }}" wire:navigate class="mt-4 inline-flex rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">
                                    {{ __('Browse events') }}
                                </a>
                            </div>
                        @else
                            <div class="mt-6 space-y-3">
                                @foreach($paginatedGoingEvents as $event)
                                    @php($eventHasPoster = $event->hasMedia('poster'))
                                    <a href="{{ route('events.show', $event) }}" wire:navigate class="group flex gap-4 rounded-xl border border-slate-200 p-3 transition hover:border-emerald-200 hover:shadow-md">
                                        <div class="size-20 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                                            <img src="{{ $event->card_image_url }}" alt="{{ $event->title }}" loading="lazy" class="h-full w-full transition duration-500 group-hover:scale-105 {{ $eventHasPoster ? 'object-contain bg-slate-100' : 'object-cover' }}">
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-3">
                                                <h3 class="min-w-0 flex-1 break-words font-semibold text-slate-900 transition group-hover:text-emerald-700">{{ $event->title }}</h3>
                                                @if($shouldShowEventStatusBadge((string) $event->status))
                                                    <span class="inline-flex shrink-0 rounded-lg px-2.5 py-1 text-[11px] font-semibold {{ $eventStatusClass((string) $event->status) }}">
                                                        {{ $eventWorkflowStatusLabel((string) $event->status) }}
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="mt-1 text-sm text-slate-600">{{ $eventDateTimeLabel($event->starts_at) }}</p>
                                            <p class="mt-1 text-sm text-slate-500">{{ $event->venue?->name ?? $event->institution?->name ?? __('Online / TBD') }}</p>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                            @if($paginatedGoingEvents->hasPages())
                                <div class="mt-5">
                                    {{ $paginatedGoingEvents->links(data: ['scrollTo' => '#planner-going']) }}
                                </div>
                            @endif
                        @endif
                    </section>

                    <div class="space-y-6">
                        <article id="planner-speakers" x-show="activePlannerSection === 'planner-speakers'" x-cloak class="rounded-2xl border border-sky-200/70 bg-white p-6 shadow-sm">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h2 class="font-heading text-xl font-bold text-slate-950">{{ __('Following Speakers') }}</h2>
                                    <p class="mt-2 text-sm text-slate-500">{{ __('Speakers you follow and want to keep in view.') }}</p>
                                </div>
                                <span class="rounded-lg bg-sky-50 px-3 py-1 text-xs font-bold text-sky-700">{{ $followingSpeakers->count() }}</span>
                            </div>
                            @if($followingSpeakers->isEmpty())
                                <div class="mt-6 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
                                    <p>{{ __('No followed speakers yet.') }}</p>
                                    <a href="{{ route('speakers.index') }}" wire:navigate class="mt-4 inline-flex rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-700">
                                        {{ __('Browse speakers') }}
                                    </a>
                                </div>
                            @else
                                <div class="mt-6 space-y-3">
                                    @foreach($paginatedFollowingSpeakers as $speaker)
                                        <a href="{{ route('speakers.show', $speaker) }}" wire:navigate class="group flex gap-4 rounded-xl border border-slate-200 p-3 transition hover:border-sky-200 hover:shadow-md">
                                            <div class="size-20 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                                                <img src="{{ $speaker->public_avatar_url }}" alt="{{ $speaker->formatted_name }}" loading="lazy" class="h-full w-full transition duration-500 group-hover:scale-105 {{ $speaker->hasMedia('avatar') ? 'object-contain bg-slate-100' : 'object-cover' }}">
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center justify-between gap-3">
                                                    <h3 class="min-w-0 flex-1 break-words font-semibold text-slate-900 transition group-hover:text-sky-700">{{ $speaker->formatted_name }}</h3>
                                                    <span class="inline-flex shrink-0 rounded-lg px-2.5 py-1 text-[11px] font-semibold {{ $entityStatusClass((string) $speaker->status) }}">{{ $translateStatusLabel((string) $speaker->status) }}</span>
                                                </div>
                                                <p class="mt-1 text-sm text-slate-500">{{ __('Followed speaker') }}</p>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                                @if($paginatedFollowingSpeakers->hasPages())
                                    <div class="mt-5">
                                        {{ $paginatedFollowingSpeakers->links(data: ['scrollTo' => '#planner-speakers']) }}
                                    </div>
                                @endif
                            @endif
                        </article>

                        <article id="planner-references" x-show="activePlannerSection === 'planner-references'" x-cloak class="rounded-2xl border border-violet-200/70 bg-white p-6 shadow-sm">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h2 class="font-heading text-xl font-bold text-slate-950">{{ __('Following References') }}</h2>
                                    <p class="mt-2 text-sm text-slate-500">{{ __('Books and source materials you want to revisit later.') }}</p>
                                </div>
                                <span class="rounded-lg bg-violet-50 px-3 py-1 text-xs font-bold text-violet-700">{{ $followingReferences->count() }}</span>
                            </div>
                            @if($followingReferences->isEmpty())
                                <div class="mt-6 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
                                    <p>{{ __('No followed references yet.') }}</p>
                                    <a href="{{ route('references.index') }}" wire:navigate class="mt-4 inline-flex rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-violet-700">
                                        {{ __('Browse references') }}
                                    </a>
                                </div>
                            @else
                                <div class="mt-6 space-y-3">
                                    @foreach($paginatedFollowingReferences as $reference)
                                        @php($referenceCoverUrl = $reference->getFirstMediaUrl('front_cover', 'thumb') ?: $reference->getFirstMediaUrl('back_cover', 'thumb'))
                                        <a href="{{ route('references.show', $reference) }}" wire:navigate class="group flex gap-4 rounded-xl border border-slate-200 p-3 transition hover:border-violet-200 hover:shadow-md">
                                            <div class="size-20 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                                                @if($referenceCoverUrl !== '')
                                                    <img src="{{ $referenceCoverUrl }}" alt="{{ $reference->title }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                                                @else
                                                    <div class="flex h-full w-full items-center justify-center text-slate-400">
                                                        <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v11.494m0-11.494C10.832 5.405 9.414 5 8 5.5S5.168 7.086 4 8.5v11c1.168-1.414 2.586-2 4-2.5s2.832-.905 4-1.753m0-9.247c1.168-.848 2.586-1.253 4-1.753s2.832-.905 4-1.753v11c-1.168 1.414-2.586 2-4 2.5s-2.832.905-4 1.753" />
                                                        </svg>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center justify-between gap-3">
                                                    <h3 class="min-w-0 flex-1 break-words font-semibold text-slate-900 transition group-hover:text-violet-700">{{ $reference->title }}</h3>
                                                    <span class="inline-flex shrink-0 rounded-lg px-2.5 py-1 text-[11px] font-semibold {{ $entityStatusClass((string) $reference->status) }}">{{ $translateStatusLabel((string) $reference->status) }}</span>
                                                </div>
                                                <p class="mt-1 text-sm text-slate-500">{{ filled($reference->author) ? $reference->author : __('Followed reference') }}</p>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                                @if($paginatedFollowingReferences->hasPages())
                                    <div class="mt-5">
                                        {{ $paginatedFollowingReferences->links(data: ['scrollTo' => '#planner-references']) }}
                                    </div>
                                @endif
                            @endif
                        </article>

                        <article id="planner-institutions" x-show="activePlannerSection === 'planner-institutions'" x-cloak class="rounded-2xl border border-indigo-200/70 bg-white p-6 shadow-sm">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h2 class="font-heading text-xl font-bold text-slate-950">{{ __('Following Institutions') }}</h2>
                                    <p class="mt-2 text-sm text-slate-500">{{ __('Organizations you follow for updates and events.') }}</p>
                                </div>
                                <span class="rounded-lg bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700">{{ $followingInstitutions->count() }}</span>
                            </div>
                            @if($followingInstitutions->isEmpty())
                                <div class="mt-6 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
                                    <p>{{ __('No followed institutions yet.') }}</p>
                                    <a href="{{ route('institutions.index') }}" wire:navigate class="mt-4 inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700">
                                        {{ __('Browse institutions') }}
                                    </a>
                                </div>
                            @else
                                <div class="mt-6 space-y-3">
                                    @foreach($paginatedFollowingInstitutions as $institution)
                                        <a href="{{ route('institutions.show', $institution) }}" wire:navigate class="group flex gap-4 rounded-xl border border-slate-200 p-3 transition hover:border-indigo-200 hover:shadow-md">
                                            <div class="size-20 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                                                <img src="{{ $institution->public_image_url }}" alt="{{ $institution->name }}" loading="lazy" class="h-full w-full transition duration-500 group-hover:scale-105 {{ $institution->hasMedia('cover') || $institution->hasMedia('logo') ? 'object-contain bg-slate-100' : 'object-cover' }}">
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center justify-between gap-3">
                                                    <h3 class="min-w-0 flex-1 break-words font-semibold text-slate-900 transition group-hover:text-indigo-700">{{ $institution->name }}</h3>
                                                    <span class="inline-flex shrink-0 rounded-lg px-2.5 py-1 text-[11px] font-semibold {{ $entityStatusClass((string) $institution->status) }}">{{ $translateStatusLabel((string) $institution->status) }}</span>
                                                </div>
                                                <p class="mt-1 text-sm text-slate-500">{{ __('Followed institution') }}</p>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                                @if($paginatedFollowingInstitutions->hasPages())
                                    <div class="mt-5">
                                        {{ $paginatedFollowingInstitutions->links(data: ['scrollTo' => '#planner-institutions']) }}
                                    </div>
                                @endif
                            @endif
                        </article>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@php
    $dashboardPageLabel = in_array(app()->getLocale(), ['ms', 'ms_MY'], true) ? 'Dashboard' : __('Dashboard');
@endphp
@section('title', $dashboardPageLabel . ' - ' . config('app.name'))

@php
    $user = auth()->user();
    $summary = $this->summaryStats;
    $savedEvents = $this->savedEvents;
    $goingEvents = $this->goingEvents;
    $registeredEvents = $this->registeredEvents;
    $submittedEvents = $this->submittedEvents;
    $recentCheckins = $this->recentCheckins;
    $upcomingAgenda = $this->upcomingAgenda;
    $nextAgendaItem = $this->nextAgendaItem;
    $paginatedAgenda = $this->paginatedAgenda;
    $paginatedGoingEvents = $this->paginatedGoingEvents;
    $paginatedRegisteredEvents = $this->paginatedRegisteredEvents;
    $paginatedSavedEvents = $this->paginatedSavedEvents;
    $paginatedSubmittedEvents = $this->paginatedSubmittedEvents;
    $paginatedRecentCheckins = $this->paginatedRecentCheckins;
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
    $translateStatusLabel = static function (string $status): string {
        $translated = __($status);

        if ($translated !== $status) {
            return $translated;
        }

        return str($status)->replace('_', ' ')->headline()->toString();
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
    $isSubmittedPlannerItem = static function (mixed $roles): bool {
        return is_array($roles) && in_array('submitted', $roles, true);
    };
    $plannerQuickLinks = [
        [
            'label' => __('Calendar'),
            'count' => $summary['planning_count'],
            'href' => '#planner-calendar',
            'class' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        ],
        [
            'label' => __('Agenda'),
            'count' => $upcomingAgenda->count(),
            'href' => '#planner-agenda',
            'class' => 'border-sky-200 bg-sky-50 text-sky-700',
        ],
        [
            'label' => __('Going'),
            'count' => $summary['going_count'],
            'href' => '#planner-going',
            'class' => 'border-emerald-200 bg-white text-emerald-700',
        ],
        [
            'label' => __('Registered'),
            'count' => $summary['registered_count'],
            'href' => '#planner-registered',
            'class' => 'border-sky-200 bg-white text-sky-700',
        ],
        [
            'label' => __('Saved'),
            'count' => $summary['saved_count'],
            'href' => '#planner-saved',
            'class' => 'border-amber-200 bg-white text-amber-700',
        ],
        [
            'label' => __('Submitted'),
            'count' => $summary['submitted_count'],
            'href' => '#planner-submitted',
            'class' => 'border-violet-200 bg-white text-violet-700',
        ],
        [
            'label' => __('Check-ins'),
            'count' => $summary['checkins_count'],
            'href' => '#planner-checkins',
            'class' => 'border-slate-200 bg-white text-slate-700',
        ],
    ];
    $eventStatusClass = static fn (string $status): string => match ($status) {
        'approved' => 'bg-emerald-100 text-emerald-700',
        'pending', 'needs_changes' => 'bg-amber-100 text-amber-700',
        'cancelled', 'rejected' => 'bg-rose-100 text-rose-700',
        'draft' => 'bg-slate-200 text-slate-700',
        default => 'bg-slate-200 text-slate-700',
    };
    $registrationStatusClass = static fn (string $status): string => match ($status) {
        'registered', 'attended' => 'bg-sky-100 text-sky-700',
        'cancelled', 'no_show' => 'bg-rose-100 text-rose-700',
        default => 'bg-slate-200 text-slate-700',
    };
    $checkinMethodLabel = static fn (string $method): string => match ($method) {
        'registered_self_checkin' => __('Registered self check-in'),
        'organizer_verified' => __('Organizer verified'),
        default => __('Self reported'),
    };
@endphp

<div class="min-h-screen bg-slate-50 py-10 pb-32">
    <div class="container mx-auto px-6 lg:px-12">
        <div class="mx-auto max-w-7xl space-y-8">
            <section class="relative overflow-hidden rounded-[2rem] border border-slate-200/60 bg-gradient-to-br from-slate-950 via-emerald-950 to-slate-950 p-6 shadow-2xl shadow-emerald-950/10 md:p-8">
                <div class="pointer-events-none absolute inset-0">
                    <div class="absolute -top-24 left-12 h-72 w-72 rounded-full bg-emerald-500/20 blur-3xl"></div>
                    <div class="absolute bottom-0 right-10 h-64 w-64 rounded-full bg-sky-400/10 blur-3xl"></div>
                    <div class="absolute inset-0 opacity-[0.04]" style="background-image: url('{{ asset('images/pattern-bg.png') }}'); background-size: 240px;"></div>
                </div>

                <div class="relative z-10 grid gap-8 xl:grid-cols-[1.3fr,0.9fr] xl:items-start">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.24em] text-emerald-300">{{ $dashboardPageLabel }}</p>
                        <h1 class="mt-3 font-heading text-3xl font-bold tracking-tight text-white md:text-4xl">
                            {{ __('Assalamualaikum, :name', ['name' => $firstName]) }}
                        </h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                            {{ __('Track what you saved, what you plan to attend, and what you have already checked into. The calendar below is designed to help you plan your month without mixing in institution operations.') }}
                        </p>

                        <div class="mt-6 flex flex-wrap gap-3">
                            <a href="{{ route('events.index') }}" wire:navigate
                                class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-emerald-50 hover:text-emerald-700">
                                {{ __('Discover Events') }}
                            </a>
                            <a href="{{ route('submit-event.create') }}" wire:navigate
                                class="inline-flex items-center justify-center rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-emerald-300 hover:bg-emerald-500/20">
                                {{ __('Submit Event') }}
                            </a>
                            <a href="{{ route('contributions.index') }}" wire:navigate
                                class="inline-flex items-center justify-center rounded-xl border border-white/15 bg-transparent px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-emerald-300 hover:text-emerald-200">
                                {{ __('My Contributions') }}
                            </a>
                            <a href="{{ route('dashboard.dawah-impact') }}" wire:navigate
                                class="inline-flex items-center justify-center rounded-xl border border-white/15 bg-transparent px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-emerald-300 hover:text-emerald-200">
                                {{ __('Dawah Impact') }}
                            </a>
                            @if($summary['institutions_count'] > 0)
                                <a href="{{ route('dashboard.institutions') }}" wire:navigate
                                    class="inline-flex items-center justify-center rounded-xl border border-white/15 bg-transparent px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-sky-300 hover:text-sky-200">
                                    {{ __('Institution Dashboard') }}
                                </a>
                            @endif
                        </div>

                        <div class="mt-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-300">{{ __('Upcoming plan') }}</p>
                                <p class="mt-2 text-3xl font-black text-white">{{ $summary['planning_count'] }}</p>
                                <p class="mt-2 text-xs text-slate-300">{{ __('Unique upcoming events across your tracked activity.') }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-300">{{ __('Going') }}</p>
                                <p class="mt-2 text-3xl font-black text-white">{{ $summary['going_count'] }}</p>
                                <p class="mt-2 text-xs text-slate-300">{{ __('Events you explicitly plan to attend.') }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-300">{{ __('Registered') }}</p>
                                <p class="mt-2 text-3xl font-black text-white">{{ $summary['registered_count'] }}</p>
                                <p class="mt-2 text-xs text-slate-300">{{ __('Registrations that need reminders, travel, or follow-through.') }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-300">{{ __('Submitted') }}</p>
                                <p class="mt-2 text-3xl font-black text-white">{{ $summary['submitted_count'] }}</p>
                                <p class="mt-2 text-xs text-slate-300">{{ __('Events you submitted into the platform workflow.') }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-300">{{ __('Check-ins') }}</p>
                                <p class="mt-2 text-3xl font-black text-white">{{ $summary['checkins_count'] }}</p>
                                <p class="mt-2 text-xs text-slate-300">{{ __('Attendance history you have already recorded.') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[1.75rem] border border-white/10 bg-white/10 p-5 backdrop-blur">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-300">{{ __('Next up') }}</p>
                                <h2 class="mt-1 font-heading text-xl font-bold text-white">{{ __('Your next relevant event') }}</h2>
                            </div>
                            <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold text-slate-200">
                                {{ __('Planner view') }}
                            </span>
                        </div>

                        @if($nextAgendaItem)
                            <a href="{{ $nextAgendaItem['url'] }}" wire:navigate
                                class="mt-5 block overflow-hidden rounded-[1.5rem] border border-white/10 bg-slate-900/40 transition hover:border-emerald-300/40 hover:bg-slate-900/60">
                                <div class="aspect-[16/9] overflow-hidden bg-slate-900">
                                    <img src="{{ $nextAgendaItem['image_url'] }}" alt="{{ $nextAgendaItem['title'] }}"
                                        class="h-full w-full object-cover transition duration-500 hover:scale-105">
                                </div>
                                <div class="space-y-4 p-5">
                                    <div class="flex flex-wrap items-center gap-2">
                                        @foreach($nextAgendaItem['role_badges'] as $badge)
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $badge['class'] }}">
                                                {{ $badge['label'] }}
                                            </span>
                                        @endforeach
                                        @if($shouldShowEventStatusBadge((string) ($nextAgendaItem['status'] ?? 'approved'), $isSubmittedPlannerItem($nextAgendaItem['roles'] ?? null)))
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $nextAgendaItem['status_class'] }}">
                                                {{ $eventWorkflowStatusLabel((string) ($nextAgendaItem['status'] ?? 'approved')) }}
                                            </span>
                                        @endif
                                    </div>
                                    <div>
                                        <h3 class="font-heading text-2xl font-bold text-white">{{ $nextAgendaItem['title'] }}</h3>
                                        <p class="mt-2 text-sm text-slate-300">{{ $nextAgendaItem['time_label'] }}</p>
                                        <p class="mt-1 text-sm text-slate-400">{{ $nextAgendaItem['secondary_label'] }}</p>
                                    </div>
                                </div>
                            </a>
                        @else
                            <div class="mt-5 rounded-[1.5rem] border border-dashed border-white/15 bg-white/5 p-8 text-center">
                                <p class="text-lg font-semibold text-white">{{ __('Nothing scheduled yet') }}</p>
                                <p class="mt-2 text-sm text-slate-300">{{ __('Start saving, registering, or marking events as going to build your planner.') }}</p>
                                <a href="{{ route('events.index') }}" wire:navigate
                                    class="mt-5 inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-emerald-50 hover:text-emerald-700">
                                    {{ __('Browse upcoming events') }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">{{ __('Dawah Impact') }}</p>
                        <h2 class="mt-1 font-heading text-2xl font-bold text-slate-900">{{ __('What happened after your sharing') }}</h2>
                        <p class="mt-2 text-sm text-slate-500">{{ __('These numbers stay private to you and summarise the visits and beneficial responses that followed your shared links.') }}</p>
                    </div>

                    <a href="{{ route('dashboard.dawah-impact') }}" wire:navigate
                        class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                        {{ __('Open impact dashboard') }}
                    </a>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-3xl border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ __('Visits') }}</p>
                        <p class="mt-2 text-3xl font-black text-slate-900">{{ number_format($dawahImpactSummary['visits']) }}</p>
                        <p class="mt-2 text-xs text-slate-500">{{ __('Attributed visits from your shared pages.') }}</p>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ __('Unique Visitors') }}</p>
                        <p class="mt-2 text-3xl font-black text-slate-900">{{ number_format($dawahImpactSummary['unique_visitors']) }}</p>
                        <p class="mt-2 text-xs text-slate-500">{{ __('People reached at least once.') }}</p>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ __('Signups') }}</p>
                        <p class="mt-2 text-3xl font-black text-slate-900">{{ number_format($dawahImpactSummary['signups']) }}</p>
                        <p class="mt-2 text-xs text-slate-500">{{ __('New accounts created after a share touch.') }}</p>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ __('Registrations') }}</p>
                        <p class="mt-2 text-3xl font-black text-slate-900">{{ number_format($dawahImpactSummary['event_registrations']) }}</p>
                        <p class="mt-2 text-xs text-slate-500">{{ __('Event registrations credited to your sharing.') }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">{{ __('Jump to section') }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Use quick links to move between planner views and activity buckets without re-reading summary cards.') }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($plannerQuickLinks as $quickLink)
                            <a href="{{ $quickLink['href'] }}"
                                class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-semibold transition hover:-translate-y-0.5 hover:shadow-sm {{ $quickLink['class'] }}">
                                <span>{{ $quickLink['label'] }}</span>
                                <span class="rounded-full bg-slate-900/5 px-2 py-0.5 text-[11px] font-bold">{{ $quickLink['count'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="grid gap-8 xl:grid-cols-[1.45fr,0.95fr]"
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
                }">
                <section id="planner-calendar" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                    <div class="flex flex-col gap-5">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-600">{{ __('Overview Calendar') }}</p>
                                <h2 class="mt-2 font-heading text-2xl font-bold text-slate-900">{{ __('See your month at a glance') }}</h2>
                                <p class="mt-2 max-w-2xl text-sm text-slate-500">{{ __('Filter the calendar by saved, going, registered, submitted, and check-in history. Events with multiple roles are merged into one calendar entry.') }}</p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <template x-for="filter in filterList" :key="filter.key">
                                    <button type="button" @click="toggleFilter(filter.key)"
                                        class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-semibold transition"
                                        :class="filters[filter.key] ? filter.active_button_class : filter.inactive_button_class">
                                        <span x-text="filter.label"></span>
                                        <span class="rounded-full bg-white/20 px-2 py-0.5 text-[11px] font-bold" x-text="filter.count"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div class="overflow-hidden rounded-[1.5rem] border border-slate-200">
                            <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-4 py-3 sm:px-5">
                                <button type="button"
                                    @click="calendarMonth--; if (calendarMonth < 0) { calendarMonth = 11; calendarYear--; }"
                                    class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 transition hover:bg-white hover:text-slate-700">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 19.5L8.25 12l7.5-7.5" />
                                    </svg>
                                </button>
                                <h3 class="text-sm font-bold text-slate-700" x-text="monthLabel()"></h3>
                                <button type="button"
                                    @click="calendarMonth++; if (calendarMonth > 11) { calendarMonth = 0; calendarYear++; }"
                                    class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 transition hover:bg-white hover:text-slate-700">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                    </svg>
                                </button>
                            </div>

                            <div class="grid grid-cols-7 border-b border-slate-100 bg-slate-50/50">
                                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayLabel)
                                    <div class="py-2 text-center text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">{{ __($dayLabel) }}</div>
                                @endforeach
                            </div>

                            <div class="grid grid-cols-7">
                                <template x-for="cell in calendarCells()" :key="cell.key">
                                    <div class="min-h-[7rem] border-b border-r border-slate-100 p-1.5 sm:min-h-[8rem] sm:p-2"
                                        :class="cell.day === null ? 'bg-slate-50/40' : 'bg-white'">
                                        <template x-if="cell.day !== null">
                                            <div class="flex h-full flex-col">
                                                <div class="flex items-center">
                                                    <span class="text-xs font-bold text-slate-500" x-text="cell.day"></span>
                                                </div>

                                                <div class="mt-1 flex-1 space-y-1">
                                                    <template x-for="entry in cell.entries.slice(0, 2)" :key="entry.key">
                                                        <a :href="entry.url || '#'"
                                                            class="block rounded-lg border px-2 py-1.5 text-[10px] leading-snug transition"
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

                <section id="planner-agenda" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                    <div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-sky-600">{{ __('Upcoming Agenda') }}</p>
                        </div>
                    </div>

                    @if($paginatedAgenda->isEmpty())
                        <div class="mt-6 rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50 p-8 text-center">
                            @if($nextAgendaItem)
                                <p class="text-base font-semibold text-slate-700">{{ __('Your next event is already featured above.') }}</p>
                                <p class="mt-2 text-sm text-slate-500">{{ __('As you save or register for more events, the rest of your month will continue to fill in here.') }}</p>
                            @else
                                <p class="text-base font-semibold text-slate-700">{{ __('No events are on your immediate agenda.') }}</p>
                                <p class="mt-2 text-sm text-slate-500">{{ __('Mark events as going, register, or save them to start filling the planner.') }}</p>
                            @endif
                        </div>
                    @else
                        <div class="mt-6 space-y-3">
                            @foreach($paginatedAgenda as $item)
                                <a href="{{ $item['url'] }}" wire:navigate
                                    class="group flex gap-4 rounded-[1.5rem] border border-slate-200 p-3 transition hover:border-emerald-200 hover:shadow-md">
                                    <div class="h-24 w-24 flex-shrink-0 overflow-hidden rounded-2xl bg-slate-100">
                                        <img src="{{ $item['image_url'] }}" alt="{{ $item['title'] }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            @foreach($item['role_badges'] as $badge)
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $badge['class'] }}">
                                                    {{ $badge['label'] }}
                                                </span>
                                            @endforeach
                                            @if($shouldShowEventStatusBadge((string) ($item['status'] ?? 'approved'), $isSubmittedPlannerItem($item['roles'] ?? null)))
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $item['status_class'] }}">
                                                    {{ $eventWorkflowStatusLabel((string) ($item['status'] ?? 'approved')) }}
                                                </span>
                                            @endif
                                        </div>
                                        <h3 class="mt-3 font-semibold text-slate-900 transition group-hover:text-emerald-700">{{ $item['title'] }}</h3>
                                        <p class="mt-1 text-sm text-slate-600">{{ $item['time_label'] }}</p>
                                        <p class="mt-1 text-sm text-slate-500">{{ $item['secondary_label'] }}</p>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                        @if($paginatedAgenda->hasPages())
                            <div class="mt-5">
                                {{ $paginatedAgenda->links(data: ['scrollTo' => '#planner-agenda']) }}
                            </div>
                        @endif
                    @endif
                </section>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <article id="planner-going" class="rounded-[2rem] border border-emerald-200/70 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="font-heading text-xl font-bold text-slate-900">{{ __('Going') }}</h2>
                            <p class="mt-2 text-sm text-slate-500">{{ __('Your strongest attendance signal. These should be easy to act on.') }}</p>
                        </div>
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">{{ $goingEvents->count() }}</span>
                    </div>
                    @if($goingEvents->isEmpty())
                        <div class="mt-6 rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
                            {{ __('Nothing marked as going yet.') }}
                        </div>
                    @else
                        <div class="mt-6 space-y-3">
                            @foreach($paginatedGoingEvents as $event)
                                @php($eventHasPoster = $event->hasMedia('poster'))
                                <a href="{{ route('events.show', $event) }}" wire:navigate class="group flex gap-4 rounded-[1.5rem] border border-slate-200 p-3 transition hover:border-emerald-200 hover:shadow-md">
                                    <div class="h-20 w-20 flex-shrink-0 overflow-hidden rounded-2xl bg-slate-100">
                                        <img src="{{ $event->card_image_url }}" alt="{{ $event->title }}" class="h-full w-full transition duration-500 group-hover:scale-105 {{ $eventHasPoster ? 'object-contain bg-slate-100' : 'object-cover' }}">
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-3">
                                            <h3 class="font-semibold text-slate-900 transition group-hover:text-emerald-700">{{ $event->title }}</h3>
                                            @if($shouldShowEventStatusBadge((string) $event->status))
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $eventStatusClass((string) $event->status) }}">
                                                    {{ $eventWorkflowStatusLabel((string) $event->status) }}
                                                </span>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-sm text-slate-600">{{ $event->starts_at ? \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->starts_at, 'd M, h:i A') : __('Time to be confirmed') }}</p>
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
                </article>

                <article id="planner-registered" class="rounded-[2rem] border border-sky-200/70 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="font-heading text-xl font-bold text-slate-900">{{ __('Registered') }}</h2>
                            <p class="mt-2 text-sm text-slate-500">{{ __('Registrations that may need follow-through, reminders, or travel planning.') }}</p>
                        </div>
                        <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-bold text-sky-700">{{ $registeredEvents->count() }}</span>
                    </div>
                    @if($registeredEvents->isEmpty())
                        <div class="mt-6 rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
                            {{ __('No registrations yet.') }}
                        </div>
                    @else
                        <div class="mt-6 space-y-3">
                            @foreach($paginatedRegisteredEvents as $registration)
                                @php($event = $registration->event)
                                @if($event)
                                    @php($eventHasPoster = $event->hasMedia('poster'))
                                    <a href="{{ route('events.show', $event) }}" wire:navigate class="group flex gap-4 rounded-[1.5rem] border border-slate-200 p-3 transition hover:border-sky-200 hover:shadow-md">
                                        <div class="h-20 w-20 flex-shrink-0 overflow-hidden rounded-2xl bg-slate-100">
                                            <img src="{{ $event->card_image_url }}" alt="{{ $event->title }}" class="h-full w-full transition duration-500 group-hover:scale-105 {{ $eventHasPoster ? 'object-contain bg-slate-100' : 'object-cover' }}">
                                        </div>
                                        <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $registrationStatusClass((string) $registration->status) }}">
                                                {{ $translateStatusLabel((string) $registration->status) }}
                                            </span>
                                            @if($shouldShowEventStatusBadge((string) $event->status))
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $eventStatusClass((string) $event->status) }}">
                                                    {{ $eventWorkflowStatusLabel((string) $event->status) }}
                                                </span>
                                            @endif
                                        </div>
                                            <h3 class="mt-3 font-semibold text-slate-900 transition group-hover:text-sky-700">{{ $event->title }}</h3>
                                            <p class="mt-1 text-sm text-slate-600">{{ $event->starts_at ? \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->starts_at, 'd M, h:i A') : __('Time to be confirmed') }}</p>
                                            <p class="mt-1 text-sm text-slate-500">{{ $event->venue?->name ?? $event->institution?->name ?? __('Online / TBD') }}</p>
                                        </div>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                        @if($paginatedRegisteredEvents->hasPages())
                            <div class="mt-5">
                                {{ $paginatedRegisteredEvents->links(data: ['scrollTo' => '#planner-registered']) }}
                            </div>
                        @endif
                    @endif
                </article>

                <article id="planner-saved" class="rounded-[2rem] border border-amber-200/70 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="font-heading text-xl font-bold text-slate-900">{{ __('Saved') }}</h2>
                            <p class="mt-2 text-sm text-slate-500">{{ __('Bookmarks that may need a final attendance decision.') }}</p>
                        </div>
                        <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">{{ $savedEvents->count() }}</span>
                    </div>
                    @if($savedEvents->isEmpty())
                        <div class="mt-6 rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
                            {{ __('No saved events yet.') }}
                        </div>
                    @else
                        <div class="mt-6 space-y-3">
                            @foreach($paginatedSavedEvents as $event)
                                @php($eventHasPoster = $event->hasMedia('poster'))
                                <a href="{{ route('events.show', $event) }}" wire:navigate class="group flex gap-4 rounded-[1.5rem] border border-slate-200 p-3 transition hover:border-amber-200 hover:shadow-md">
                                    <div class="h-20 w-20 flex-shrink-0 overflow-hidden rounded-2xl bg-slate-100">
                                        <img src="{{ $event->card_image_url }}" alt="{{ $event->title }}" class="h-full w-full transition duration-500 group-hover:scale-105 {{ $eventHasPoster ? 'object-contain bg-slate-100' : 'object-cover' }}">
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-3">
                                            <h3 class="font-semibold text-slate-900 transition group-hover:text-amber-700">{{ $event->title }}</h3>
                                            @if($shouldShowEventStatusBadge((string) $event->status))
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $eventStatusClass((string) $event->status) }}">
                                                    {{ $eventWorkflowStatusLabel((string) $event->status) }}
                                                </span>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-sm text-slate-600">{{ $event->starts_at ? \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->starts_at, 'd M, h:i A') : __('Time to be confirmed') }}</p>
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
                </article>
            </section>

            <section id="planner-submitted" class="rounded-[2rem] border border-violet-200/70 bg-white p-6 shadow-sm md:p-8">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-violet-600">{{ __('Submitted Events') }}</p>
                        <h2 class="mt-2 font-heading text-2xl font-bold text-slate-900">{{ __('Your own submissions') }}</h2>
                        <p class="mt-2 max-w-2xl text-sm text-slate-500">{{ __('Submitted events stay visible here as a separate workflow so the main planner remains attendee-first.') }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-bold text-violet-700">{{ $submittedEvents->count() }}</span>
                        <a href="{{ route('submit-event.create') }}" wire:navigate
                            class="inline-flex items-center justify-center rounded-xl border border-violet-200 px-4 py-2 text-sm font-semibold text-violet-700 transition hover:bg-violet-50">
                            {{ __('Submit another event') }}
                        </a>
                    </div>
                </div>

                    @if($submittedEvents->isEmpty())
                        <div class="mt-6 rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50 p-8 text-center">
                            <p class="text-base font-semibold text-slate-700">{{ __('No submitted events yet.') }}</p>
                            <p class="mt-2 text-sm text-slate-500">{{ __('When you submit events, they will appear here with status and quick links.') }}</p>
                        </div>
                    @else
                        <div class="mt-6 grid gap-4 lg:grid-cols-2">
                            @foreach($paginatedSubmittedEvents as $submissionEntry)
                                @php($event = $submissionEntry['event'])
                                @if($event)
                                    @php($eventHasPoster = $event->hasMedia('poster'))
                                    <article class="overflow-hidden rounded-[1.5rem] border border-slate-200">
                                    <div class="aspect-[16/8] overflow-hidden bg-slate-100">
                                        <img src="{{ $event->card_image_url }}" alt="{{ $event->title }}" class="h-full w-full {{ $eventHasPoster ? 'object-contain bg-slate-100' : 'object-cover' }}">
                                    </div>
                                    <div class="space-y-4 p-5">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $eventStatusClass((string) $event->status) }}">
                                                {{ $eventWorkflowStatusLabel((string) $event->status) }}
                                            </span>
                                            <span class="inline-flex rounded-full bg-violet-50 px-2.5 py-1 text-[11px] font-semibold text-violet-700">
                                                {{ __('Submitted') }} {{ $submissionEntry['created_at']?->diffForHumans() }}
                                            </span>
                                        </div>
                                        <div>
                                            <h3 class="font-heading text-xl font-bold text-slate-900">{{ $event->title }}</h3>
                                            <p class="mt-2 text-sm text-slate-600">{{ $event->starts_at ? \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->starts_at, 'd M, h:i A') : __('Time to be confirmed') }}</p>
                                            <p class="mt-1 text-sm text-slate-500">{{ $event->venue?->name ?? $event->institution?->name ?? __('Online / TBD') }}</p>
                                        </div>
                                        @if(filled($submissionEntry['notes']))
                                            <div class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-600">
                                                {{ $submissionEntry['notes'] }}
                                            </div>
                                        @endif
                                        <div class="flex flex-wrap gap-2">
                                            <a href="{{ route('events.show', $event) }}" wire:navigate
                                                class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-violet-600">
                                                {{ __('View event') }}
                                            </a>
                                            @if($this->canManageSubmittedEvent($event))
                                                <a href="{{ \App\Filament\Ahli\Resources\Events\EventResource::getUrl('view', ['record' => $event], panel: 'ahli') }}"
                                                    class="inline-flex items-center justify-center rounded-xl border border-violet-200 px-4 py-2 text-sm font-semibold text-violet-700 transition hover:bg-violet-50">
                                                    {{ __('Manage event') }}
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                                @endif
                            @endforeach
                        </div>
                        @if($paginatedSubmittedEvents->hasPages())
                            <div class="mt-5">
                                {{ $paginatedSubmittedEvents->links(data: ['scrollTo' => '#planner-submitted']) }}
                            </div>
                        @endif
                    @endif
                </section>

            <section id="planner-checkins" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">{{ __('Recent Check-ins') }}</p>
                        <h2 class="mt-2 font-heading text-2xl font-bold text-slate-900">{{ __('Attendance history') }}</h2>
                        <p class="mt-2 max-w-2xl text-sm text-slate-500">{{ __('Check-ins remain historical. They appear as past markers in the calendar and as recent attendance records below.') }}</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{{ $recentCheckins->count() }}</span>
                </div>

                    @if($recentCheckins->isEmpty())
                        <div class="mt-6 rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50 p-8 text-center">
                            <p class="text-base font-semibold text-slate-700">{{ __('No check-ins recorded yet.') }}</p>
                            <p class="mt-2 text-sm text-slate-500">{{ __('When you check in from an event page, your attendance history will appear here.') }}</p>
                        </div>
                    @else
                        <div class="mt-6 space-y-3">
                            @foreach($paginatedRecentCheckins as $checkin)
                                @php($event = $checkin->event)
                                <article class="flex flex-col gap-3 rounded-[1.5rem] border border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    @if($event)
                                        <a href="{{ route('events.show', $event) }}" wire:navigate class="font-semibold text-slate-900 transition hover:text-emerald-700">
                                            {{ $event->title }}
                                        </a>
                                        <p class="mt-1 text-sm text-slate-600">
                                            {{ $checkin->checked_in_at ? \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($checkin->checked_in_at, 'd M Y, h:i A') : __('Attendance recorded') }}
                                        </p>
                                        <p class="mt-1 text-sm text-slate-500">{{ $event->venue?->name ?? $event->institution?->name ?? __('Online / TBD') }}</p>
                                    @else
                                        <p class="font-semibold text-slate-900">{{ __('Checked-in event') }}</p>
                                    @endif
                                </div>
                                <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                    {{ $checkinMethodLabel((string) $checkin->method) }}
                                </span>
                                </article>
                            @endforeach
                        </div>
                        @if($paginatedRecentCheckins->hasPages())
                            <div class="mt-5">
                                {{ $paginatedRecentCheckins->links(data: ['scrollTo' => '#planner-checkins']) }}
                            </div>
                        @endif
                    @endif
                </section>
        </div>
    </div>
</div>

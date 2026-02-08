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


@php
    $states = $this->states;
@endphp

<div class="relative min-h-screen pb-32">
    <!-- Hero Section -->
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
                @island(name: 'count', lazy: false)
                @placeholder
                <span class="inline-flex items-center gap-1.5 animate-pulse">
                    <span class="h-4 w-8 bg-slate-200 rounded"></span> {{ __('Gatherings') }}
                </span>
                @endplaceholder
                {{ $this->events->total() }} {{ __('Upcoming Gatherings') }}
                @endisland
            </span>

            <h1
                class="font-heading text-5xl md:text-6xl font-extrabold text-slate-900 tracking-tight text-balance mb-6">
                {{ __('Find Your Next') }} <br class="hidden md:block" />
                <span
                    class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-teal-500">{{ __('Circle of Knowledge') }}</span>
            </h1>
            <p class="text-slate-500 text-lg md:text-xl max-w-2xl mx-auto text-balance">
            <p class="text-slate-500 text-lg md:text-xl max-w-2xl mx-auto text-balance">
                {{ __('Discover classes, lectures, and community gatherings happening near you. Connect with knowledge seekers in your area.') }}
            </p>
        </div>
    </div>

    <div class="container mx-auto px-6 lg:px-12 -mt-8 relative z-10">
        <!-- Search & Filter Card -->
        <form action="{{ route('events.index') }}" method="GET" x-ref="form" x-data="{
                    locating: false,
                    showFilters: {{ request()->hasAny(['state_id', 'language', 'event_type', 'gender', 'age_group']) ? 'true' : 'false' }},
                    locate() {
                        if (this.locating) return;
                        if (! navigator.geolocation) {
                            alert('{{ __("Geolocation is not supported by your browser.") }}');
                            return;
                        }
                        this.locating = true;
                        navigator.geolocation.getCurrentPosition((position) => {
                            this.$refs.lat.value = position.coords.latitude;
                            this.$refs.lng.value = position.coords.longitude;
                            this.$refs.sort.value = 'distance';
                            this.$refs.form.submit();
                        }, () => {
                            this.locating = false;
                            alert('{{ __("Unable to get your location. Please enable location services.") }}');
                        });
                    },
                    setSort(sort) {
                        this.$refs.sort.value = sort;
                        this.$refs.form.submit();
                    },
                }" class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 p-6 md:p-8">

            <div class="grid lg:grid-cols-[1fr_auto_auto] gap-4 mb-6">
                <!-- Text Search -->
                <div class="relative group">
                    <input type="text" id="event-search" name="search" value="{{ request('search') }}"
                        placeholder="{{ __('Search by title, topic, or speaker...') }}"
                        class="w-full h-14 pl-12 pr-4 rounded-2xl border-2 border-slate-100 bg-slate-50/50 font-medium text-slate-900 focus:bg-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all placeholder:text-slate-400">
                    <svg class="absolute left-4 top-1/2 -translate-y-1/2 h-6 w-6 text-slate-400 group-focus-within:text-emerald-500 transition-colors"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>

                <!-- Location -->
                <div class="flex gap-2">
                    <button type="button" @click="locate" :disabled="locating"
                        class="h-14 px-6 rounded-2xl border-2 border-slate-100 bg-white font-semibold text-slate-600 hover:border-emerald-500 hover:text-emerald-600 focus:ring-4 focus:ring-emerald-500/10 transition-all flex items-center justify-center gap-2 min-w-[140px]">
                        <svg class="w-5 h-5" :class="locating ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24"
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
                </div>

                <!-- Search Button -->
                <button type="submit"
                    class="h-14 px-8 rounded-2xl bg-slate-900 text-white font-bold hover:bg-emerald-600 shadow-lg hover:shadow-emerald-500/30 hover:-translate-y-0.5 transition-all flex items-center justify-center gap-2">
                    <span>{{ __('Search') }}</span>
                </button>
            </div>

            <input type="hidden" name="lat" x-ref="lat" value="{{ request('lat') }}">
            <input type="hidden" name="lng" x-ref="lng" value="{{ request('lng') }}">
            <input type="hidden" name="radius_km" id="radius_km" value="{{ request('radius_km', 50) }}">
            <input type="hidden" name="sort" x-ref="sort" value="{{ request('sort', 'time') }}">

            <!-- Filters Toggle -->
            <div class="flex items-center justify-between border-t border-slate-100 pt-5">
                <button type="button" @click="showFilters = !showFilters"
                    class="flex items-center gap-2 text-sm font-semibold text-emerald-600 hover:text-emerald-700 transition-colors">
                    <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-50">
                        <svg class="w-5 h-5 transition-transform duration-300" :class="{ 'rotate-180': showFilters }"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                        </svg>
                    </span>
                    {{ __('Filter Events') }}
                </button>

                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-slate-500">{{ __('Sort:') }}</span>
                    <div class="flex bg-slate-100 p-1 rounded-xl">
                        <button type="button" @click="setSort('time')"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ request('sort', 'time') == 'time' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                            {{ __('Latest') }}
                        </button>
                        <button type="button" @click="setSort('relevance')"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ request('sort') == 'relevance' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                            {{ __('Relevance') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Live Filters -->
            <div x-show="showFilters" x-collapse>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 pt-6 mt-2">
                    <!-- State Filter -->
                    <div class="space-y-2">
                        <label
                            class="text-xs font-bold text-slate-400 uppercase tracking-wider">{{ __('Location') }}</label>
                        <select name="state_id" onchange="this.form.submit()"
                            class="w-full h-11 px-3 rounded-xl border border-slate-200 bg-slate-50 text-sm font-medium focus:border-emerald-500 focus:ring-0 cursor-pointer hover:border-emerald-400 transition-colors">
                            <option value="">{{ __('All States') }}</option>
                            @foreach($states ?? [] as $state)
                                <option value="{{ $state->id }}" @selected(request('state_id') == $state->id)>
                                    {{ $state->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Language Filter -->
                    <div class="space-y-2">
                        <label
                            class="text-xs font-bold text-slate-400 uppercase tracking-wider">{{ __('Language') }}</label>
                        <select name="language" onchange="this.form.submit()"
                            class="w-full h-11 px-3 rounded-xl border border-slate-200 bg-slate-50 text-sm font-medium focus:border-emerald-500 focus:ring-0 cursor-pointer hover:border-emerald-400 transition-colors">
                            <option value="">{{ __('Any Language') }}</option>
                            <option value="malay" @selected(request('language') == 'malay')>Bahasa Melayu</option>
                            <option value="english" @selected(request('language') == 'english')>English</option>
                            <option value="arabic" @selected(request('language') == 'arabic')>العربية</option>
                            <option value="mixed" @selected(request('language') == 'mixed')>Mixed</option>
                        </select>
                    </div>

                    <!-- Event Type Filter -->
                    <div class="space-y-2">
                        <label
                            class="text-xs font-bold text-slate-400 uppercase tracking-wider">{{ __('Event Type') }}</label>
                        <select name="event_type" onchange="this.form.submit()"
                            class="w-full h-11 px-3 rounded-xl border border-slate-200 bg-slate-50 text-sm font-medium focus:border-emerald-500 focus:ring-0 cursor-pointer hover:border-emerald-400 transition-colors">
                            <option value="">{{ __('Any Type') }}</option>
                            @foreach(collect(\App\Enums\EventType::cases())->mapToGroups(fn(\App\Enums\EventType $type) => [$type->getGroup() => [$type->value => $type->getLabel()]])->map(fn($group) => $group->collapse()) as $groupLabel => $options)
                                <optgroup label="{{ $groupLabel }}">
                                    @foreach($options as $value => $label)
                                        <option value="{{ $value }}" @selected(request('event_type') == $value)>{{ $label }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>

                    <!-- Gender Filter -->
                    <div class="space-y-2">
                        <label
                            class="text-xs font-bold text-slate-400 uppercase tracking-wider">{{ __('Gender') }}</label>
                        <select name="gender" onchange="this.form.submit()"
                            class="w-full h-11 px-3 rounded-xl border border-slate-200 bg-slate-50 text-sm font-medium focus:border-emerald-500 focus:ring-0 cursor-pointer hover:border-emerald-400 transition-colors">
                            <option value="">{{ __('Any') }}</option>
                            @foreach(\App\Enums\EventGenderRestriction::cases() as $gender)
                                <option value="{{ $gender->value }}"
                                    @selected(request('gender') == $gender->value)>{{ $gender->getLabel() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Age Group Filter -->
                    <div class="space-y-2">
                        <label
                            class="text-xs font-bold text-slate-400 uppercase tracking-wider">{{ __('Age Group') }}</label>
                        @php
                            $selectedAgeGroups = (array) request('age_group', []);
                        @endphp
                        <select name="age_group[]" multiple onchange="this.form.submit()"
                            class="w-full min-h-11 px-3 rounded-xl border border-slate-200 bg-slate-50 text-sm font-medium focus:border-emerald-500 focus:ring-0 cursor-pointer hover:border-emerald-400 transition-colors">
                            @foreach(\App\Enums\EventAgeGroup::cases() as $age)
                                <option value="{{ $age->value }}" @selected(in_array($age->value, $selectedAgeGroups, true))>
                                    {{ $age->getLabel() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <!-- Active Filters Bar -->
            @if(request()->hasAny(['search', 'state_id', 'language', 'event_type', 'gender', 'age_group', 'lat']))
                <div class="flex flex-wrap items-center gap-2 mt-6 pt-4 border-t border-slate-100">
                    <span class="text-xs font-bold text-slate-400 uppercase mr-2">{{ __('Active:') }}</span>
                    @if(request('search'))
                        <span
                            class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            "{{ request('search') }}"
                        </span>
                    @endif
                    @if(request('lat'))
                        <span
                            class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            </svg>
                            {{ __('Nearby') }}
                        </span>
                    @endif
                    <a href="{{ route('events.index') }}" wire:navigate
                        class="ml-auto text-xs font-bold text-red-500 hover:text-red-600 hover:underline">
                        {{ __('Clear All Filters') }}
                    </a>
                </div>
            @endif
        </form>

        <!-- Results Grid -->
        <div class="mt-16">
            @island(name: 'grid', lazy: false)
            @placeholder
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach(range(1, 6) as $i)
                    <div class="h-[450px] bg-slate-100 animate-pulse rounded-3xl"></div>
                @endforeach
            </div>
            @endplaceholder

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
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    @foreach($events as $event)
                        <article
                            class="group h-full flex flex-col bg-white rounded-3xl overflow-hidden shadow-sm hover:shadow-xl hover:shadow-emerald-900/5 hover:-translate-y-1 transition-all duration-300 border border-slate-100">
                            <!-- Image/Date -->
                            <a href="{{ route('events.show', $event) }}" wire:navigate
                                class="relative aspect-[3/2] overflow-hidden bg-slate-100 block">
                                <div
                                    class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-60 z-10 transition-opacity group-hover:opacity-70">
                                </div>
                                <div class="w-full h-full bg-slate-200 flex items-center justify-center text-slate-300">
                                    <img src="{{ $event->card_image_url }}" alt="{{ $event->title }}" loading="lazy"
                                        class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                                </div>

                                <!-- Badges -->
                                <div class="absolute top-4 left-4 z-20 flex flex-col gap-2">
                                    <div
                                        class="bg-white/95 backdrop-blur-sm rounded-xl px-3 py-1.5 text-center shadow-sm border border-black/5 min-w-[3.5rem]">
                                        <div class="text-[0.6rem] font-bold uppercase tracking-wider text-slate-500">
                                            {{ $event->starts_at?->format('M') }}
                                        </div>
                                        <div class="text-xl font-bold font-heading text-slate-900 leading-none">
                                            {{ $event->starts_at?->format('d') }}
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

                            <div class="flex-1 p-6 flex flex-col">
                                <div class="flex justify-between items-start mb-3 gap-4">
                                    <a href="{{ route('events.show', $event) }}" wire:navigate
                                        class="group-hover:text-emerald-700 transition-colors">
                                        <h3 class="font-heading text-xl font-bold text-slate-900 line-clamp-2 leading-tight">
                                            {{ $event->title }}
                                        </h3>
                                    </a>
                                </div>

                                <div class="space-y-3 mb-6">
                                    <div class="flex items-start gap-2.5 text-sm text-slate-600">
                                        <svg class="w-4 h-4 text-emerald-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span
                                            class="line-clamp-1">{{ $event->venue?->name ?? ($event->institution?->name ?? __('Online')) }}</span>
                                    </div>
                                    <div class="flex items-center gap-2.5 text-sm text-slate-600">
                                        <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        {{ $event->starts_at?->format('h:i A') }}
                                    </div>
                                </div>

                                <div class="mt-auto pt-5 border-t border-slate-100 flex items-center justify-between">
                                    @if($event->gender && $event->gender->value !== 'all')
                                        <span
                                            class="text-xs font-semibold text-slate-400 uppercase tracking-widest">{{ $event->gender->getLabel() }}</span>
                                    @else
                                        <span></span>
                                    @endif

                                    <a href="{{ route('events.show', $event) }}" wire:navigate
                                        class="inline-flex items-center gap-1.5 text-sm font-bold text-emerald-600 hover:text-emerald-700 transition-colors">
                                        {{ __('Join') }}
                                        <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="mt-16">
                    {{ $events->withQueryString()->links() }}
                </div>
            @endif
            @endisland
        </div>
    </div>
</div>
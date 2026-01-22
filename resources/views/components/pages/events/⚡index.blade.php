<?php

use App\Models\State;
use App\Services\EventSearchService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    private array $queryTimings = [];

    public function mount(): void
    {
        DB::listen(function ($query): void {
            $this->queryTimings[] = [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time_ms' => $query->time,
            ];
        });

        app()->terminating(function (): void {
            if ($this->queryTimings === []) {
                return;
            }

            $totalMs = array_sum(array_column($this->queryTimings, 'time_ms'));

            Log::info('Events page query timings', [
                'count' => count($this->queryTimings),
                'total_ms' => $totalMs,
                'queries' => $this->queryTimings,
            ]);
        });
    }

    #[Computed]
    public function states(): Collection
    {
        return State::query()->orderBy('name')->get();
    }

    #[Computed]
    public function events(): LengthAwarePaginator
    {
        $filters = [
            'state_id' => request()->input('state_id'),
            'district_id' => request()->input('district_id'),
            'language' => request()->input('language'),
            'genre' => request()->input('genre'),
            'audience' => request()->input('audience'),
            'institution_id' => request()->input('institution_id'),
            'topic_ids' => request()->input('topic_ids'),
            'speaker_ids' => request()->input('speaker_ids'),
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

        $lat = request()->input('lat');
        $lng = request()->input('lng');
        $radiusKm = (int) request()->input('radius_km', 50);

        /** @var EventSearchService $searchService */
        $searchService = app(EventSearchService::class);

        if ($lat && $lng) {
            return $searchService->searchNearby(
                lat: (float) $lat,
                lng: (float) $lng,
                radiusKm: $radiusKm,
                filters: $filters,
                perPage: 12
            );
        }

        return $searchService->search(
            query: request()->input('search'),
            filters: $filters,
            perPage: 12,
            sort: request()->input('sort', 'time')
        );
    }
};
?>

@extends('layouts.app')

@section('title', __('Upcoming Events') . ' - ' . config('app.name'))

@push('head')
    <!-- OpenGraph / Twitter Cards -->
    <meta property="og:title" content="{{ __('Upcoming Events') }} - {{ config('app.name') }}">
    <meta property="og:description" content="{{ __('Discover Islamic lectures, classes, and gatherings happening near you.') }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ route('events.index') }}">
    <meta name="twitter:card" content="summary">
@endpush

@section('content')
    @php
        $events = $this->events;
        $states = $this->states;
    @endphp

    <div class="bg-slate-50 min-h-screen py-16 pb-32">
        <div class="container mx-auto px-6 lg:px-12">
            <!-- Header -->
            <div class="max-w-3xl mb-10">
                <h1 class="font-heading text-4xl font-bold text-slate-900">{{ __('Browse Events') }}</h1>
                <p class="text-slate-500 mt-4 text-lg">
                    {{ __('Find classes, lectures, and community gatherings happening around you.') }}
                </p>
            </div>

            <!-- Search & Filters Bar -->
            <form action="{{ route('events.index') }}" method="GET" x-ref="form" x-data="{
                locating: false,
                locate() {
                    if (this.locating) {
                        return;
                    }

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
            }" class="bg-white rounded-2xl shadow-lg shadow-slate-100 border border-slate-100 p-6 mb-10">

                <!-- Main Search Row -->
                <div class="flex flex-col lg:flex-row gap-4 mb-6">
                    <!-- Text Search -->
                    <div class="relative flex-grow">
                        <label for="event-search" class="sr-only">{{ __('Search events') }}</label>
                        <input type="text" id="event-search" name="search" value="{{ request('search') }}"
                            placeholder="{{ __('Search events, topics, speakers...') }}"
                            class="w-full h-12 pl-11 pr-4 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all placeholder:text-slate-400">
                        <svg class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>

                    <!-- Near Me Button -->
                    <button type="button" @click="locate" :disabled="locating"
                        class="inline-flex items-center justify-center gap-2 h-12 px-6 rounded-xl border-2 border-emerald-500 text-emerald-600 font-semibold hover:bg-emerald-50 transition-all whitespace-nowrap">
                        <span class="inline-flex items-center gap-2" x-show="!locating">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            {{ __('Near Me') }}
                        </span>
                        <span class="inline-flex items-center gap-2" x-show="locating" x-cloak>
                            <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ __('Locating...') }}
                        </span>
                    </button>
                    <input type="hidden" name="lat" x-ref="lat" value="{{ request('lat') }}">
                    <input type="hidden" name="lng" x-ref="lng" value="{{ request('lng') }}">
                    <input type="hidden" name="radius_km" id="radius_km" value="{{ request('radius_km', 50) }}">

                    <!-- Search Button -->
                    <button type="submit"
                        class="inline-flex items-center justify-center gap-2 h-12 px-8 rounded-xl bg-emerald-600 text-white font-semibold shadow-lg shadow-emerald-500/20 hover:bg-emerald-700 hover:-translate-y-0.5 transition-all">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        {{ __('Search') }}
                    </button>
                </div>

                <!-- Expandable Filters -->
                <div x-data="{ showFilters: {{ request()->hasAny(['state_id', 'language', 'genre', 'audience']) ? 'true' : 'false' }} }">
                    <button type="button" @click="showFilters = !showFilters"
                        class="flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-emerald-600 transition-colors mb-4">
                        <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': showFilters }" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                        {{ __('More Filters') }}
                    </button>

                    <div x-show="showFilters" x-collapse class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-4 border-t border-slate-100">
                        <!-- State Filter -->
                        <div>
                            <label for="filter-state" class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">{{ __('State') }}</label>
                            <select id="filter-state" name="state_id"
                                class="w-full h-10 px-3 rounded-lg border border-slate-200 bg-slate-50 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none">
                                <option value="">{{ __('All States') }}</option>
                                @foreach($states ?? [] as $state)
                                    <option value="{{ $state->id }}" @selected(request('state_id') == $state->id)>
                                        {{ $state->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Language Filter -->
                        <div>
                            <label for="filter-language" class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">{{ __('Language') }}</label>
                            <select id="filter-language" name="language"
                                class="w-full h-10 px-3 rounded-lg border border-slate-200 bg-slate-50 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none">
                                <option value="">{{ __('All Languages') }}</option>
                                <option value="malay" @selected(request('language') == 'malay')>Bahasa Melayu</option>
                                <option value="english" @selected(request('language') == 'english')>English</option>
                                <option value="arabic" @selected(request('language') == 'arabic')>العربية</option>
                                <option value="mixed" @selected(request('language') == 'mixed')>{{ __('Mixed') }}</option>
                            </select>
                        </div>

                        <!-- Genre Filter -->
                        <div>
                            <label for="filter-genre" class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">{{ __('Type') }}</label>
                            <select id="filter-genre" name="genre"
                                class="w-full h-10 px-3 rounded-lg border border-slate-200 bg-slate-50 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none">
                                <option value="">{{ __('All Types') }}</option>
                                <option value="kuliah" @selected(request('genre') == 'kuliah')>{{ __('Kuliah') }}</option>
                                <option value="ceramah" @selected(request('genre') == 'ceramah')>{{ __('Ceramah') }}</option>
                                <option value="tazkirah" @selected(request('genre') == 'tazkirah')>{{ __('Tazkirah') }}</option>
                                <option value="forum" @selected(request('genre') == 'forum')>{{ __('Forum') }}</option>
                                <option value="halaqah" @selected(request('genre') == 'halaqah')>{{ __('Halaqah') }}</option>
                            </select>
                        </div>

                        <!-- Audience Filter -->
                        <div>
                            <label for="filter-audience" class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">{{ __('Audience') }}</label>
                            <select id="filter-audience" name="audience"
                                class="w-full h-10 px-3 rounded-lg border border-slate-200 bg-slate-50 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none">
                                <option value="">{{ __('Everyone') }}</option>
                                <option value="general" @selected(request('audience') == 'general')>{{ __('General') }}</option>
                                <option value="men_only" @selected(request('audience') == 'men_only')>{{ __('Men Only') }}</option>
                                <option value="women_only" @selected(request('audience') == 'women_only')>{{ __('Women Only') }}</option>
                                <option value="youth" @selected(request('audience') == 'youth')>{{ __('Youth') }}</option>
                                <option value="children" @selected(request('audience') == 'children')>{{ __('Children') }}</option>
                                <option value="families" @selected(request('audience') == 'families')>{{ __('Families') }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Sort & Active Filters -->
                <div class="flex flex-wrap items-center justify-between gap-4 mt-6 pt-4 border-t border-slate-100">
                    <div class="flex items-center gap-4">
                        <label class="text-sm font-medium text-slate-500">{{ __('Sort by:') }}</label>
                        <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden">
                            <button type="button" @click="setSort('time')"
                                class="sort-btn px-4 py-2 text-sm font-medium transition-colors {{ request('sort', 'time') == 'time' ? 'bg-emerald-50 text-emerald-700' : 'bg-white text-slate-500 hover:bg-slate-50' }}">
                                {{ __('Date') }}
                            </button>
                            <button type="button" @click="setSort('relevance')"
                                class="sort-btn px-4 py-2 text-sm font-medium border-l border-slate-200 transition-colors {{ request('sort') == 'relevance' ? 'bg-emerald-50 text-emerald-700' : 'bg-white text-slate-500 hover:bg-slate-50' }}">
                                {{ __('Relevance') }}
                            </button>
                            <button type="button" @click="setSort('distance')"
                                class="sort-btn px-4 py-2 text-sm font-medium border-l border-slate-200 transition-colors {{ request('sort') == 'distance' ? 'bg-emerald-50 text-emerald-700' : 'bg-white text-slate-500 hover:bg-slate-50' }}"
                                {{ !request('lat') ? 'disabled' : '' }}>
                                {{ __('Distance') }}
                            </button>
                        </div>
                        <input type="hidden" name="sort" x-ref="sort" value="{{ request('sort', 'time') }}">
                    </div>

                    @if(request()->hasAny(['search', 'state_id', 'language', 'genre', 'audience', 'lat']))
                        <a href="{{ route('events.index') }}" wire:navigate
                            class="text-sm font-medium text-slate-400 hover:text-red-500 flex items-center gap-1 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            {{ __('Clear All') }}
                        </a>
                    @endif
                </div>
            </form>

            <!-- Results Header -->
            <div class="flex items-center justify-between mb-6">
                <p class="text-slate-500">
                    <span class="font-semibold text-slate-900">{{ $events->total() }}</span> {{ __('events found') }}
                    @if(request('lat'))
                        <span class="text-emerald-600">• {{ __('Near your location') }}</span>
                    @endif
                </p>
            </div>

            @if($events->isEmpty())
                <div class="text-center py-32 rounded-3xl bg-white border border-slate-100 shadow-sm">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-slate-50 text-slate-300 mb-6">
                        <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900">{{ __('No events found') }}</h3>
                    <p class="text-slate-500 mt-2 max-w-md mx-auto">
                        {{ __('We couldn\'t find any upcoming events matching your search. Try adjusting your filters.') }}</p>
                    <a href="{{ route('events.index') }}" wire:navigate
                        class="mt-8 inline-flex items-center text-emerald-600 font-semibold hover:text-emerald-700">
                        {{ __('Clear Filters') }} →
                    </a>
                </div>
            @else
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    @foreach($events as $event)
                        <article
                            class="group relative bg-white rounded-3xl border border-slate-100 shadow-sm hover:shadow-xl hover:shadow-emerald-500/10 hover:-translate-y-1 transition-all duration-300 h-full flex flex-col overflow-hidden">
                            <!-- Date Badge & Placeholder -->
                            <div class="relative h-48 bg-slate-100 overflow-hidden">
                                <div
                                    class="w-full h-full flex items-center justify-center bg-gradient-to-br from-emerald-50 to-teal-50 text-emerald-200">
                                    <svg class="w-16 h-16 opacity-50 transition-transform duration-500 group-hover:scale-110"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <!-- Date Badge -->
                                <div
                                    class="absolute top-4 left-4 inline-flex flex-col items-center justify-center bg-white rounded-xl px-3 py-2 shadow-sm border border-slate-100 min-w-[3.5rem]">
                                    <span
                                        class="text-xs font-bold text-slate-400 uppercase tracking-wider">{{ $event->starts_at?->format('M') }}</span>
                                    <span
                                        class="text-xl font-black text-slate-900 leading-none">{{ $event->starts_at?->format('d') }}</span>
                                </div>
                                <!-- Distance Badge (if available) -->
                                @if(isset($event->distance_km))
                                    <div
                                        class="absolute top-4 right-4 inline-flex items-center gap-1 bg-emerald-600 text-white rounded-full px-2.5 py-1 text-xs font-semibold shadow-lg">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        </svg>
                                        {{ number_format($event->distance_km, 1) }} km
                                    </div>
                                @endif
                            </div>

                            <div class="p-6 flex flex-col flex-grow">
                                <div class="flex flex-wrap gap-2 mb-4">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                                        {{ ucfirst($event->genre ?? 'General') }}
                                    </span>
                                    @if($event->language && $event->language !== 'malay')
                                        <span
                                            class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                            {{ ucfirst($event->language) }}
                                        </span>
                                    @endif
                                </div>
                                <h3
                                    class="font-heading text-xl font-bold text-slate-900 group-hover:text-emerald-600 transition-colors mb-2 line-clamp-2">
                                    <a href="{{ route('events.show', $event) }}" wire:navigate>
                                        {{ $event->title }}
                                    </a>
                                </h3>
                                <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
                                    <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span class="truncate">{{ $event->venue?->name ?? ($event->institution?->name ?? __('Online')) }}</span>
                                </div>

                                <div class="mt-auto pt-4 border-t border-slate-100 flex items-center justify-between">
                                    <span class="text-xs font-medium text-slate-400">
                                        <x-event-timing :event="$event" :show-date="false" class="text-xs" />
                                    </span>
                                    <a href="{{ route('events.show', $event) }}" wire:navigate
                                        class="text-sm font-semibold text-emerald-600 hover:text-emerald-700 flex items-center gap-1 group-hover:gap-2 transition-all">
                                        {{ __('Details') }} →
                                    </a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="mt-12">
                    {{ $events->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>

@endsection
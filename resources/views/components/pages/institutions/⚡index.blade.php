<?php

use App\Models\Institution;
use App\Models\District;
use App\Models\State;
use App\Models\Subdistrict;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Title('Institutions - Majlis Ilmu')]
class extends Component
{
    use WithPagination;

    #[Url]
    public ?string $search = null;

    #[Url]
    public ?string $state_id = null;

    #[Url]
    public ?string $district_id = null;

    #[Url]
    public ?string $subdistrict_id = null;

    #[Computed]
    public function institutions(): LengthAwarePaginatorContract
    {
        $search = $this->normalizedSearch();
        $baseQuery = $this->baseInstitutionsQuery();

        if ($search === null) {
            return $baseQuery
                ->orderBy('name', 'asc')
                ->paginate(12)
                ->withQueryString();
        }

        $directMatches = $this->applyDirectSearch($baseQuery, $search)
            ->orderBy('name', 'asc')
            ->paginate(12)
            ->withQueryString();

        if ($directMatches->total() > 0 || mb_strlen($search) < 3) {
            return $directMatches;
        }

        return $this->fuzzySearch($search);
    }

    private function baseInstitutionsQuery(): Builder
    {
        $query = Institution::query()
            ->where('status', 'verified')
            ->withCount(['events' => function ($query) {
                $query->active();
            }])
            ->with(['address.state', 'address.district', 'address.subdistrict', 'media']);

        return $this->applyLocationScope($query);
    }

    private function applyDirectSearch(Builder $query, string $search): Builder
    {
        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $collapsedSearch = preg_replace('/\s+/u', ' ', trim($search)) ?? '';
        $collapsedWildcardSearch = '%'.str_replace(' ', '%', $collapsedSearch).'%';
        $searchTokens = array_values(array_filter(explode(' ', $collapsedSearch), static fn (string $token): bool => $token !== ''));

        return $query->where(function (Builder $innerQuery) use ($collapsedSearch, $operator, $collapsedWildcardSearch, $searchTokens) {
            $innerQuery->where('name', $operator, "%{$collapsedSearch}%")
                ->orWhere('name', $operator, $collapsedWildcardSearch)
                ->orWhere('description', $operator, "%{$collapsedSearch}%")
                ->orWhere('description', $operator, $collapsedWildcardSearch);

            if (count($searchTokens) < 2) {
                return;
            }

            $innerQuery->orWhere(function (Builder $tokenQuery) use ($searchTokens, $operator): void {
                foreach ($searchTokens as $token) {
                    if (mb_strlen($token) < 2) {
                        continue;
                    }

                    $tokenQuery->where(function (Builder $tokenMatchQuery) use ($token, $operator): void {
                        $tokenMatchQuery->where('name', $operator, "%{$token}%")
                            ->orWhere('description', $operator, "%{$token}%");
                    });
                }
            });
        });
    }

    private function fuzzySearch(string $search): LengthAwarePaginatorContract
    {
        $normalizedSearch = $this->normalizeForSimilarity($search);
        if ($normalizedSearch === '') {
            return $this->baseInstitutionsQuery()
                ->orderBy('name', 'asc')
                ->paginate(12)
                ->withQueryString();
        }

        $rankedCandidatesQuery = Institution::query()
            ->where('status', 'verified')
            ->select(['id', 'name', 'description']);

        $rankedCandidates = $this->applyLocationScope($rankedCandidatesQuery)
            ->get()
            ->map(function (Institution $institution) use ($normalizedSearch): array {
                $normalizedName = $this->normalizeForSimilarity($institution->name);
                $normalizedDescription = $this->normalizeForSimilarity((string) $institution->description);

                $scoreCandidates = [];

                if ($normalizedName !== '') {
                    $scoreCandidates[] = $this->similarityScore($normalizedSearch, $normalizedName);

                    $nameTokens = array_values(array_filter(explode(' ', $normalizedName), static fn (string $token): bool => mb_strlen($token) >= 2));
                    foreach ($nameTokens as $token) {
                        $scoreCandidates[] = $this->similarityScore($normalizedSearch, $token);
                    }
                }

                if ($normalizedDescription !== '') {
                    $scoreCandidates[] = $this->similarityScore($normalizedSearch, $normalizedDescription);
                }

                return [
                    'id' => $institution->id,
                    'score' => $scoreCandidates === [] ? 0.0 : max($scoreCandidates),
                ];
            })
            ->filter(static fn (array $candidate): bool => $candidate['score'] >= 0.70)
            ->sortByDesc('score')
            ->values();

        $currentPage = max(1, (int) $this->getPage());
        $perPage = 12;
        $paginationMeta = [
            'path' => request()->url(),
            'query' => request()->query(),
        ];

        if ($rankedCandidates->isEmpty()) {
            return new LengthAwarePaginator(collect(), 0, $perPage, $currentPage, $paginationMeta);
        }

        $orderedIds = $rankedCandidates->pluck('id')->all();
        $paginatedIds = array_slice($orderedIds, ($currentPage - 1) * $perPage, $perPage);

        if ($paginatedIds === []) {
            return new LengthAwarePaginator(collect(), count($orderedIds), $perPage, $currentPage, $paginationMeta);
        }

        $institutions = $this->baseInstitutionsQuery()
            ->whereIn('id', $paginatedIds)
            ->get()
            ->sortBy(static function (Institution $institution) use ($paginatedIds): int {
                $position = array_search($institution->id, $paginatedIds, true);

                return is_int($position) ? $position : PHP_INT_MAX;
            })
            ->values();

        return new LengthAwarePaginator($institutions, count($orderedIds), $perPage, $currentPage, $paginationMeta);
    }

    #[Computed]
    public function states(): array
    {
        return State::query()
            ->where('country_id', 132)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    #[Computed]
    public function districts(): array
    {
        $stateId = $this->normalizedLocationId($this->state_id);

        if ($stateId === null) {
            return [];
        }

        return District::query()
            ->where('state_id', $stateId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    #[Computed]
    public function subdistricts(): array
    {
        $districtId = $this->normalizedLocationId($this->district_id);

        if ($districtId === null) {
            return [];
        }

        return Subdistrict::query()
            ->where('district_id', $districtId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private function normalizedSearch(): ?string
    {
        if (! is_string($this->search)) {
            return null;
        }

        $normalizedSearch = trim($this->search);

        return $normalizedSearch === '' ? null : $normalizedSearch;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStateId(): void
    {
        $this->district_id = null;
        $this->subdistrict_id = null;
        $this->resetPage();
    }

    public function updatedDistrictId(): void
    {
        $this->subdistrict_id = null;
        $this->resetPage();
    }

    public function updatedSubdistrictId(): void
    {
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = null;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = null;
        $this->state_id = null;
        $this->district_id = null;
        $this->subdistrict_id = null;
        $this->resetPage();
    }

    private function applyLocationScope(Builder $query): Builder
    {
        $stateId = $this->normalizedLocationId($this->state_id);
        $districtId = $this->normalizedLocationId($this->district_id);
        $subdistrictId = $this->normalizedLocationId($this->subdistrict_id);

        if ($stateId === null && $districtId === null && $subdistrictId === null) {
            return $query;
        }

        return $query->whereHas('address', function (Builder $addressQuery) use ($stateId, $districtId, $subdistrictId): void {
            if ($stateId !== null) {
                $addressQuery->where('state_id', $stateId);
            }

            if ($districtId !== null) {
                $addressQuery->where('district_id', $districtId);
            }

            if ($subdistrictId !== null) {
                $addressQuery->where('subdistrict_id', $subdistrictId);
            }
        });
    }

    private function normalizedLocationId(?string $value): ?int
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        if ($normalized === '' || ! ctype_digit($normalized)) {
            return null;
        }

        return (int) $normalized;
    }

    private function normalizeForSimilarity(string $value): string
    {
        return (string) Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9\s]+/u', ' ')
            ->replaceMatches('/\s+/u', ' ')
            ->trim();
    }

    private function similarityScore(string $search, string $candidate): float
    {
        if ($search === '' || $candidate === '') {
            return 0.0;
        }

        $distance = levenshtein($search, $candidate);
        $maxLength = max(mb_strlen($search), mb_strlen($candidate));
        $distanceScore = $maxLength > 0 ? 1 - ($distance / $maxLength) : 0.0;

        similar_text($search, $candidate, $similarityPercent);
        $similarityScore = $similarityPercent / 100;

        return max($distanceScore, $similarityScore);
    }
};
?>

@section('title', __('Direktori Institusi Islam di Malaysia') . ' - ' . config('app.name'))
@section('meta_description', __('Terokai masjid, surau, pusat pengajian, dan institusi penganjur majlis ilmu di seluruh Malaysia. Cari mengikut nama dan lokasi.'))
@section('og_url', route('institutions.index'))
@section('og_image', asset('images/placeholders/institution.png'))
@section('og_image_alt', __('Direktori institusi Islam di Malaysia'))
@section('og_image_width', '1024')
@section('og_image_height', '1024')

@php
    $institutions = $this->institutions;
    $search = $this->search;
    $states = $this->states;
    $districts = $this->districts;
    $subdistricts = $this->subdistricts;
    $stateId = $this->state_id;
    $districtId = $this->district_id;
    $subdistrictId = $this->subdistrict_id;
    $hasScopedFilters = filled($stateId) || filled($districtId) || filled($subdistrictId);
    $submitInstitutionUrl = route('contributions.submit-institution');
    $formatInstitutionLocation = static function ($addressModel): string {
        $parts = \App\Support\Location\AddressHierarchyFormatter::parts($addressModel, ['state', 'district', 'subdistrict']);

        return $parts === [] ? '-' : implode(', ', $parts);
    };
@endphp

<div class="relative min-h-screen pb-32">
        <!-- Hero Section -->
        <div class="relative pt-24 pb-16 bg-white border-b border-slate-100 overflow-hidden">
             <div class="absolute inset-0 bg-emerald-50/50"></div>
            <div class="absolute inset-0 bg-[url('/images/pattern-bg.png')] opacity-5"></div>

            <div class="container relative mx-auto px-6 lg:px-12 text-center">
                 <h1 class="font-heading text-4xl md:text-5xl font-extrabold text-slate-900 tracking-tight text-balance mb-6">
                    {{ __('Centers of') }} <br class="hidden md:block" />
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-teal-500">{{ __('Knowledge & Community') }}</span>
                </h1>
                <p class="text-slate-600 text-lg md:text-xl max-w-2xl mx-auto text-balance">
                    {{ __('Connect with the mosques, suraus, and educational centers nurturing our community.') }}
                </p>
                
                 <!-- Search Box -->
                 <div class="max-w-xl mx-auto mt-8">
                    <div class="relative group">
                        <label for="institution-search" class="sr-only">{{ __('Search institutions') }}</label>
                        <input 
                            type="text" 
                            id="institution-search"
                            wire:model.live.debounce.300ms="search"
                            wire:keydown.escape="clearSearch"
                            placeholder="{{ __('Search institutions...') }}" 
                            class="w-full h-14 pl-12 pr-4 rounded-2xl border-2 border-slate-200 bg-white shadow-lg shadow-slate-200/60 font-medium text-slate-900 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all placeholder:text-slate-400"
                        >
                        <svg class="absolute left-4 top-1/2 -translate-y-1/2 h-6 w-6 text-slate-400 group-focus-within:text-emerald-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                         @if(filled($search))
                            <button type="button" wire:click="clearSearch" class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-red-500 hover:underline">
                                {{ __('Clear') }}
                            </button>
                        @endif
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 text-left md:grid-cols-3">
                        <div>
                            <label for="institution-state-filter" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {{ __('Negeri') }}
                            </label>
                            <select
                                id="institution-state-filter"
                                wire:model.live="state_id"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/10"
                            >
                                <option value="">{{ __('Semua Negeri') }}</option>
                                @foreach($states as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="institution-district-filter" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {{ __('Daerah') }}
                            </label>
                            <select
                                id="institution-district-filter"
                                wire:model.live="district_id"
                                @disabled(! filled($stateId))
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/10 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                            >
                                <option value="">{{ __('Semua Daerah') }}</option>
                                @foreach($districts as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="institution-subdistrict-filter" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {{ __('Bandar / Mukim / Zon') }}
                            </label>
                            <select
                                id="institution-subdistrict-filter"
                                wire:model.live="subdistrict_id"
                                @disabled(! filled($districtId))
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/10 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                            >
                                <option value="">{{ __('Semua Bandar / Mukim / Zon') }}</option>
                                @foreach($subdistricts as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

	                    @if($hasScopedFilters)
	                        <div class="mt-3 flex justify-end">
	                            <button
	                                type="button"
	                                wire:click="clearFilters"
                                class="text-xs font-bold text-red-500 hover:underline"
	                            >
	                                {{ __('Clear Location Scope') }}
	                            </button>
	                        </div>
	                    @endif

		                 </div>
		            </div>
		        </div>

	        <div class="container mx-auto px-6 lg:px-12 mt-12">
                @php
                    $institutionLoadingTarget = 'search,state_id,district_id,subdistrict_id,clearSearch,clearFilters';
                @endphp

	                <div wire:loading.delay.short wire:target="{{ $institutionLoadingTarget }}">
	                    <x-ui.skeleton.institution-card-grid />
	                </div>

	            <div wire:loading.remove wire:target="{{ $institutionLoadingTarget }}">
	            @if($institutions->isEmpty())
	                <div class="text-center py-24 rounded-3xl bg-slate-50/50 border border-dashed border-slate-200">
	                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-white text-slate-300 shadow-sm mb-6">
                        <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
	                    </div>
	                    <h3 class="text-xl font-bold text-slate-900">{{ __('No institutions found') }}</h3>
	                    <p class="text-slate-500 mt-2 max-w-md mx-auto">{{ __('We couldn\'t find any institutions matching your search.') }}</p>
                        <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                            <button type="button" wire:click="clearFilters" class="font-semibold text-emerald-600 hover:text-emerald-700">
                                {{ __('Clear Filters') }} &rarr;
                            </button>
	                        </div>
		                </div>
		            @else
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    @foreach($institutions as $institution)
                        @php
                            $coverUrl = $institution->getFirstMediaUrl('cover', 'banner');
                            $cardInstitutionImageUrl = $coverUrl ?: $institution->getFirstMediaUrl('logo');
                        @endphp
                        <a href="{{ route('institutions.show', $institution) }}" wire:navigate class="group relative bg-white rounded-3xl border border-slate-200 shadow-md hover:shadow-xl hover:shadow-emerald-900/8 hover:-translate-y-1 transition-all duration-300 flex flex-col overflow-hidden">
                            <!-- Banner Area (16:9, cover-first) -->
                            <div class="aspect-video bg-slate-50 relative overflow-hidden">
                                @if($cardInstitutionImageUrl)
                                    <img src="{{ $cardInstitutionImageUrl }}" alt="{{ $institution->name }}" class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105" loading="lazy">
                                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/50 via-slate-900/15 to-transparent"></div>
                                @else
                                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-50 to-teal-50 opacity-100 group-hover:opacity-90 transition-opacity"></div>
                                    <svg class="absolute right-0 bottom-0 text-emerald-100/50 w-32 h-32 transform translate-x-8 translate-y-8" fill="currentColor" viewBox="0 0 24 24">
                                         <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                @endif
                            </div>
                            
                            <div class="p-6 pt-6 relative flex-1 flex flex-col">
                                <h3 class="font-heading text-lg font-bold text-slate-900 group-hover:text-emerald-700 transition-colors mb-2 leading-tight">
                                    {{ $institution->name }}
                                </h3>
                                
                                @php
                                    $address = $institution->addressModel;
                                    $locationDisplay = $formatInstitutionLocation($address);
                                @endphp
                                <p class="text-sm text-slate-600 flex items-start gap-1.5 mb-4 font-medium">
                                    <svg class="w-4 h-4 text-emerald-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                    <span class="line-clamp-2">{{ $locationDisplay }}</span>
                                </p>
                                
                                <div class="mt-auto pt-5 border-t border-slate-100 flex items-center justify-between">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg ring-1 ring-emerald-200">
                                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        {{ $institution->events_count }} {{ __('Events') }}
                                    </span>
                                    
                                     <span class="text-sm font-bold text-emerald-600 group-hover:translate-x-1 transition-transform inline-flex items-center">
                                        {{ __('View Details') }} <svg class="w-4 h-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                     </span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

		                <div class="mt-16">
		                    {{ $institutions->withQueryString()->links() }}
		                </div>
		            @endif

                    <section class="mt-16">
                        <div class="relative overflow-hidden rounded-[2rem] border border-emerald-200/70 bg-gradient-to-br from-emerald-600 via-teal-600 to-cyan-600 px-6 py-8 text-white shadow-[0_30px_90px_-40px_rgba(5,150,105,0.85)] md:px-10 md:py-10">
                            <div class="absolute -right-16 -top-16 h-44 w-44 rounded-full bg-white/10 blur-2xl"></div>
                            <div class="absolute -bottom-20 left-0 h-48 w-48 rounded-full bg-emerald-300/20 blur-3xl"></div>

                            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                                <div class="max-w-2xl">
                                    <span class="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-3 py-1 text-[11px] font-black uppercase tracking-[0.22em] text-emerald-50">
                                        {{ __('Sumbangan Komuniti') }}
                                    </span>
                                    <h2 class="mt-4 font-heading text-2xl font-bold tracking-tight text-balance md:text-3xl">
                                        {{ __('Tak jumpa institusi yang anda cari? Cadangkan institusi baharu.') }}
                                    </h2>
                                    <p class="mt-3 max-w-2xl text-sm leading-6 text-emerald-50/90 md:text-base">
                                        {{ __('Bantu kami tambah masjid, surau, pusat pengajian, dan komuniti ilmu yang patut ditemui ramai. Hantaran anda akan disemak dahulu sebelum dipaparkan kepada umum.') }}
                                    </p>
                                </div>

                                <div class="flex flex-col items-start gap-3 lg:items-end">
                                    <a
                                        href="{{ $submitInstitutionUrl }}"
                                        wire:navigate
                                        class="group inline-flex min-w-[18rem] items-center justify-between gap-4 rounded-[1.5rem] bg-white px-5 py-4 text-left text-emerald-700 shadow-xl shadow-emerald-950/20 transition duration-200 hover:-translate-y-0.5 hover:bg-emerald-50"
                                    >
                                        <span class="block">
                                            <span class="block text-[11px] font-black uppercase tracking-[0.2em] text-emerald-500">{{ __('Tambah ke direktori') }}</span>
                                            <span class="mt-1 block text-base font-bold text-emerald-900">{{ __('Cadangkan institusi baharu') }}</span>
                                        </span>
                                        <svg class="h-5 w-5 shrink-0 transition group-hover:translate-x-1" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.167 10h11.666m0 0-4.166-4.167M15.833 10l-4.166 4.167" />
                                        </svg>
                                    </a>

                                </div>
                            </div>
                        </div>
                    </section>
	                </div>
		        </div>
		    </div>

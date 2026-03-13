<?php

use App\Models\User;
use App\Models\Speaker;
use App\Services\ContributionEntityMutationService;
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
    #[Title('Speakers - Majlis Ilmu')]
    class extends Component
    {
        use WithPagination;

        #[Url]
        public ?string $search = null;

        /**
         * @var array<string, mixed>
         */
        public array $speakerSubmissionData = [];

        public bool $showSpeakerSubmissionForm = false;

        public function openSpeakerSubmissionForm(): void
        {
            if (! auth()->check()) {
                $this->redirectRoute('login', navigate: true);

                return;
            }

            $this->showSpeakerSubmissionForm = true;

            $prefillName = $this->normalizedSearch();

            $this->speakerSubmissionData = $prefillName !== null
                ? ['name' => $prefillName]
                : [];
        }

        public function cancelSpeakerSubmissionForm(): void
        {
            $this->showSpeakerSubmissionForm = false;
            $this->speakerSubmissionData = [];
        }

        public function submitSpeaker(): void
        {
            $user = auth()->user();

            if (! $user instanceof User) {
                $this->redirectRoute('login', navigate: true);

                return;
            }

            $payload = $this->speakerSubmissionData;
            $payload['name'] ??= $this->normalizedSearch() ?? 'Speaker';

            app(ContributionEntityMutationService::class)->createSpeaker($payload, $user);

            $this->showSpeakerSubmissionForm = false;
            $this->speakerSubmissionData = [];
        }

        #[Computed]
        public function speakers(): LengthAwarePaginatorContract
        {
            $search = $this->normalizedSearch();
            $baseQuery = $this->baseSpeakersQuery();

            if ($search === null) {
                return $baseQuery
                    ->orderBy('name', 'asc')
                    ->paginate(12);
            }

            $directMatches = $this->applyDirectSearch($baseQuery, $search)
                ->orderBy('name', 'asc')
                ->paginate(12);

            if ($directMatches->total() > 0 || mb_strlen($search) < 3) {
                return $directMatches;
            }

            return $this->fuzzySearch($search);
        }

        public function updatedSearch(): void
        {
            $this->resetPage();
        }

        public function clearSearch(): void
        {
            $this->search = null;
            $this->resetPage();
        }

        private function baseSpeakersQuery(): Builder
        {
            return Speaker::query()
                ->active()
                ->where('status', 'verified')
                ->withCount(['events' => function ($query) {
                    $query->active();
                }])
                ->with('media');
        }

        private function applyDirectSearch(Builder $query, string $search): Builder
        {
            $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $collapsedSearch = preg_replace('/\s+/u', ' ', trim($search)) ?? '';
            $collapsedWildcardSearch = '%'.str_replace(' ', '%', $collapsedSearch).'%';
            $searchTokens = array_values(array_filter(explode(' ', $collapsedSearch), static fn (string $token): bool => $token !== ''));

            return $query->where(function (Builder $innerQuery) use ($search, $operator, $collapsedWildcardSearch, $searchTokens) {
                        $innerQuery->where('name', $operator, "%{$search}%");
                        $innerQuery->orWhere('name', $operator, $collapsedWildcardSearch);

                        foreach ($searchTokens as $token) {
                            if (mb_strlen($token) < 2) {
                                continue;
                            }

                            $innerQuery->orWhere('name', $operator, "%{$token}%");
                        }

                        if (DB::connection()->getDriverName() === 'pgsql') {
                            $innerQuery->orWhereRaw('bio::text ILIKE ?', ["%{$search}%"]);
                            $innerQuery->orWhereRaw('bio::text ILIKE ?', [$collapsedWildcardSearch]);

                            foreach ($searchTokens as $token) {
                                if (mb_strlen($token) < 2) {
                                    continue;
                                }

                                $innerQuery->orWhereRaw('bio::text ILIKE ?', ["%{$token}%"]);
                            }
                        } else {
                            $innerQuery->orWhere('bio', $operator, "%{$search}%");
                            $innerQuery->orWhere('bio', $operator, $collapsedWildcardSearch);

                            foreach ($searchTokens as $token) {
                                if (mb_strlen($token) < 2) {
                                    continue;
                                }

                                $innerQuery->orWhere('bio', $operator, "%{$token}%");
                            }
                        }
                    });
        }

        private function fuzzySearch(string $search): LengthAwarePaginatorContract
        {
            $normalizedSearch = $this->normalizeForSimilarity($search);
            if ($normalizedSearch === '') {
                return $this->baseSpeakersQuery()
                    ->orderBy('name', 'asc')
                    ->paginate(12);
            }

            $rankedCandidates = $this->baseSpeakersQuery()
                ->select(['id', 'name'])
                ->get()
                ->map(function (Speaker $speaker) use ($normalizedSearch): array {
                    $normalizedName = $this->normalizeForSimilarity($speaker->name);
                    if ($normalizedName === '') {
                        return ['id' => $speaker->id, 'score' => 0.0];
                    }

                    $scoreCandidates = [
                        $this->similarityScore($normalizedSearch, $normalizedName),
                    ];

                    $nameTokens = array_values(array_filter(explode(' ', $normalizedName), static fn (string $token): bool => mb_strlen($token) >= 2));
                    foreach ($nameTokens as $token) {
                        $scoreCandidates[] = $this->similarityScore($normalizedSearch, $token);
                    }

                    return [
                        'id' => $speaker->id,
                        'score' => max($scoreCandidates),
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

            $speakers = $this->baseSpeakersQuery()
                ->whereIn('id', $paginatedIds)
                ->get()
                ->sortBy(static function (Speaker $speaker) use ($paginatedIds): int {
                    $position = array_search($speaker->id, $paginatedIds, true);

                    return is_int($position) ? $position : PHP_INT_MAX;
                })
                ->values();

            return new LengthAwarePaginator($speakers, count($orderedIds), $perPage, $currentPage, $paginationMeta);
        }

        private function normalizedSearch(): ?string
        {
            if (! is_string($this->search)) {
                return null;
            }

            $search = trim($this->search);

            return $search === '' ? null : $search;
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

@section('title', __('Direktori Penceramah Islam') . ' - ' . config('app.name'))
@section('meta_description', __('Cari profil penceramah, ustaz, dan pendakwah serta semak majlis ilmu mereka yang akan datang di seluruh Malaysia.'))
@section('og_url', route('speakers.index'))
@section('og_image', asset('images/placeholders/speaker.png'))
@section('og_image_alt', __('Direktori penceramah Islam'))
@section('og_image_width', '1024')
@section('og_image_height', '1024')

@php
    $speakers = $this->speakers;
    $search = $this->search;
    $speakerLoadingTarget = 'search,clearSearch';
@endphp

<div class="relative min-h-screen pb-32">
    <!-- Hero Section -->
    <div class="relative pt-24 pb-16 bg-white border-b border-slate-100 overflow-hidden">
        <div class="absolute inset-0 bg-emerald-50/50"></div>
        <div class="absolute inset-0 bg-[url('/images/pattern-bg.png')] opacity-5"></div>

        <div class="container relative mx-auto px-6 lg:px-12 text-center">
            <h1
                class="font-heading text-4xl md:text-5xl font-extrabold text-slate-900 tracking-tight text-balance mb-6">
                {{ __('Voices of') }} <br class="hidden md:block" />
                <span
                    class="text-transparent bg-clip-text bg-linear-to-r from-emerald-600 to-teal-500">{{ __('Knowledge & Wisdom') }}</span>
            </h1>
            <p class="text-slate-500 text-lg md:text-xl max-w-2xl mx-auto text-balance">
                {{ __('Scholars, teachers, and speakers sharing their knowledge with the community.') }}
            </p>

            <!-- Search Box -->
            <div class="max-w-xl mx-auto mt-8">
                <div class="relative group">
                    <label for="speaker-search" class="sr-only">{{ __('Search speakers') }}</label>
                    <input type="text" id="speaker-search" wire:model.live.debounce.300ms="search"
                        wire:keydown.escape="clearSearch"
                        placeholder="{{ __('Search speakers...') }}"
                        class="w-full h-14 pl-12 pr-4 rounded-2xl border-2 border-slate-100 bg-white shadow-lg shadow-slate-200/50 font-medium text-slate-900 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all placeholder:text-slate-400">
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

                <div class="mt-4">
                    <a
                        href="{{ route('contributions.submit-speaker') }}"
                        wire:navigate
                        class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50"
                    >
                        {{ __('Tak jumpa penceramah? Tambah penceramah') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-6 lg:px-12 mt-12">
        <div wire:loading.delay.short wire:target="{{ $speakerLoadingTarget }}">
            <x-ui.skeleton.speaker-card-grid />
        </div>

        <div wire:loading.remove wire:target="{{ $speakerLoadingTarget }}">
        @if($speakers->isEmpty())
            <div class="text-center py-24 rounded-3xl bg-slate-50/50 border border-dashed border-slate-200">
                <div
                    class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-white text-slate-300 shadow-sm mb-6">
                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-slate-900">{{ __('No speakers found') }}</h3>
                <p class="text-slate-500 mt-2 max-w-md mx-auto">
                    {{ __('We couldn\'t find any speakers matching your search.') }}
                </p>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                    <button type="button" wire:click="clearSearch"
                        class="font-semibold text-emerald-600 hover:text-emerald-700">
                        {{ __('Clear Search') }} &rarr;
                    </button>
                    <a
                        href="{{ route('contributions.submit-speaker') }}"
                        wire:navigate
                        class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50"
                    >
                        {{ __('Tambah Penceramah') }}
                    </a>
                </div>
            </div>
        @else
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                @foreach($speakers as $speaker)
                    <a href="{{ route('speakers.show', $speaker) }}" wire:navigate
                        class="group relative bg-white rounded-3xl border border-slate-100 shadow-sm hover:shadow-xl hover:shadow-emerald-900/5 hover:-translate-y-1 transition-all duration-300 flex flex-col items-center text-center p-8 overflow-hidden z-10">

                        <!-- Background Decoration -->
                        <div
                            class="absolute inset-x-0 top-0 h-24 bg-linear-to-b from-slate-50 to-transparent -z-10 opacity-50 transition-opacity group-hover:opacity-100">
                        </div>

                        <div
                            class="h-32 w-32 rounded-full p-1 bg-white border border-slate-100 shadow-lg mb-6 relative group-hover:scale-105 transition-transform duration-500">
                            <div class="w-full h-full rounded-full overflow-hidden bg-slate-100 relative">
                                <img src="{{ $speaker->avatar_url ?: $speaker->default_avatar_url }}" alt="{{ $speaker->name }}"
                                    class="w-full h-full object-cover" width="128" height="128" loading="lazy">
                            </div>
                        </div>

                        <h3
                            class="font-heading text-lg font-bold text-slate-900 group-hover:text-emerald-700 transition-colors mb-2 leading-tight">
                            {{ $speaker->formatted_name }}
                        </h3>

                        <div class="flex items-center gap-2 text-xs font-bold text-slate-400 mt-auto uppercase tracking-wider">
                            <span class="flex items-center gap-1.5">
                                {{ $speaker->events_count }} {{ __('Events') }}
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-16">
                {{ $speakers->links() }}
            </div>
        @endif
        </div>
    </div>

</div>

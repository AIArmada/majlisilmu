<?php

use App\Models\District;
use App\Models\Event;
use App\Models\Speaker;
use App\Models\State;
use App\Models\Topic;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'state_id')]
    public ?int $stateId = null;

    #[Url(as: 'district_id')]
    public ?int $districtId = null;

    #[Url(as: 'topic_id')]
    public ?string $topicId = null;

    #[Url(as: 'speaker_id')]
    public ?string $speakerId = null;

    #[Url(as: 'institution_id')]
    public ?string $institutionId = null;

    #[Url(as: 'series_id')]
    public ?string $seriesId = null;

    #[Url(as: 'language')]
    public ?string $language = null;

    #[Url(as: 'genre')]
    public ?string $genre = null;

    #[Url(as: 'audience')]
    public ?string $audience = null;

    #[Url(as: 'timeframe', except: 'upcoming')]
    public string $timeframe = 'upcoming';

    #[Url(as: 'sort', except: 'time')]
    public string $sort = 'time';

    public int $perPage = 12;

    public function updated(string $property): void
    {
        if (in_array($property, [
            'search',
            'stateId',
            'districtId',
            'topicId',
            'speakerId',
            'institutionId',
            'seriesId',
            'language',
            'genre',
            'audience',
            'timeframe',
            'sort',
        ], true)) {
            $this->resetPage();
        }
    }

    public function updatedStateId(): void
    {
        $this->districtId = null;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(
            'search',
            'stateId',
            'districtId',
            'topicId',
            'speakerId',
            'institutionId',
            'seriesId',
            'language',
            'genre',
            'audience',
            'timeframe',
            'sort'
        );
    }

    public function getStatesProperty(): Collection
    {
        return State::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function getDistrictsProperty(): Collection
    {
        if ($this->stateId === null) {
            return collect();
        }

        return District::query()
            ->where('state_id', $this->stateId)
            ->orderBy('name')
            ->get(['id', 'name', 'state_id']);
    }

    public function getTopicsProperty(): Collection
    {
        return Topic::query()
            ->orderByDesc('is_official')
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name']);
    }

    public function getSpeakersProperty(): Collection
    {
        return Speaker::query()
            ->orderByDesc('trust_score')
            ->orderBy('name')
            ->limit(40)
            ->get(['id', 'name']);
    }

    public function getEventsProperty(): LengthAwarePaginator
    {
        $query = $this->baseQuery();

        if ($this->search !== '') {
            $search = $this->search;

            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        if ($this->stateId !== null) {
            $query->where('state_id', $this->stateId);
        }

        if ($this->districtId !== null) {
            $query->where('district_id', $this->districtId);
        }

        if ($this->topicId !== null) {
            $query->whereHas('topics', function (Builder $builder): void {
                $builder->whereKey($this->topicId);
            });
        }

        if ($this->speakerId !== null) {
            $query->whereHas('speakers', function (Builder $builder): void {
                $builder->whereKey($this->speakerId);
            });
        }

        if ($this->institutionId !== null) {
            $query->where('institution_id', $this->institutionId);
        }

        if ($this->seriesId !== null) {
            $query->where('series_id', $this->seriesId);
        }

        if ($this->language !== null && $this->language !== '') {
            $query->where('language', $this->language);
        }

        if ($this->genre !== null && $this->genre !== '') {
            $query->where('genre', $this->genre);
        }

        if ($this->audience !== null && $this->audience !== '') {
            $query->where('audience', $this->audience);
        }

        if ($this->timeframe === 'week') {
            $query->whereBetween('starts_at', [now(), now()->addDays(7)]);
        }

        if ($this->timeframe === 'month') {
            $query->whereBetween('starts_at', [now(), now()->addDays(30)]);
        }

        if ($this->timeframe === 'upcoming') {
            $query->where('starts_at', '>=', now()->subDay());
        }

        $this->applySort($query);

        return $query->paginate($this->perPage);
    }

    protected function baseQuery(): Builder
    {
        return Event::query()
            ->where('status', 'approved')
            ->where('visibility', 'public')
            ->whereNotNull('published_at')
            ->with(['institution', 'venue', 'speakers', 'topics', 'state', 'district']);
    }

    protected function applySort(Builder $query): void
    {
        if ($this->sort === 'recent') {
            $query->orderByDesc('published_at');

            return;
        }

        if ($this->sort === 'popular') {
            $query->orderByDesc('saves_count');

            return;
        }

        $query->orderBy('starts_at');
    }
};

<?php

use App\Models\Institution;
use App\Models\State;
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

    #[Url(as: 'type')]
    public ?string $type = null;

    #[Url(as: 'sort', except: 'trust')]
    public string $sort = 'trust';

    public int $perPage = 12;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'stateId', 'type', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'stateId', 'type', 'sort');
    }

    public function getStatesProperty(): Collection
    {
        return State::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function getInstitutionsProperty(): LengthAwarePaginator
    {
        $query = $this->baseQuery();

        if ($this->search !== '') {
            $search = $this->search;

            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        if ($this->stateId !== null) {
            $query->where('state_id', $this->stateId);
        }

        if ($this->type !== null && $this->type !== '') {
            $query->where('type', $this->type);
        }

        $this->applySort($query);

        return $query->paginate($this->perPage);
    }

    protected function baseQuery(): Builder
    {
        return Institution::query()
            ->with(['state', 'district'])
            ->withCount('events');
    }

    protected function applySort(Builder $query): void
    {
        if ($this->sort === 'name') {
            $query->orderBy('name');

            return;
        }

        if ($this->sort === 'recent') {
            $query->orderByDesc('created_at');

            return;
        }

        $query->orderByDesc('trust_score');
    }
};

<?php

use App\Models\Speaker;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status')]
    public ?string $status = null;

    #[Url(as: 'sort', except: 'trust')]
    public string $sort = 'trust';

    public int $perPage = 12;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'sort');
    }

    public function getSpeakersProperty(): LengthAwarePaginator
    {
        $query = $this->baseQuery();

        if ($this->search !== '') {
            $search = $this->search;

            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('bio', 'ilike', "%{$search}%");
            });
        }

        if ($this->status !== null && $this->status !== '') {
            $query->where('verification_status', $this->status);
        }

        $this->applySort($query);

        return $query->paginate($this->perPage);
    }

    protected function baseQuery(): Builder
    {
        return Speaker::query()
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

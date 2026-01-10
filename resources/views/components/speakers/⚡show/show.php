<?php

use App\Models\Event;
use App\Models\Speaker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public Speaker $speaker;

    public function mount(Speaker $speaker): void
    {
        $this->speaker = $speaker;
    }

    public function getUpcomingEventsProperty(): Collection
    {
        return Event::query()
            ->where('status', 'approved')
            ->where('visibility', 'public')
            ->whereNotNull('published_at')
            ->where('starts_at', '>=', now()->subDay())
            ->whereHas('speakers', function (Builder $builder): void {
                $builder->whereKey($this->speaker->id);
            })
            ->with(['institution', 'topics'])
            ->orderBy('starts_at')
            ->limit(6)
            ->get();
    }
};

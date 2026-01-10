<?php

use App\Models\Event;
use App\Models\Series;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public Series $series;

    public function mount(Series $series): void
    {
        $series->load(['institution', 'venue']);

        $this->series = $series;
    }

    public function getEventsProperty(): Collection
    {
        return Event::query()
            ->where('series_id', $this->series->id)
            ->where('status', 'approved')
            ->where('visibility', 'public')
            ->whereNotNull('published_at')
            ->with(['speakers', 'topics'])
            ->orderBy('starts_at')
            ->get();
    }
};

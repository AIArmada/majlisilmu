<?php

use App\Models\Event;
use App\Models\Institution;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public Institution $institution;

    public function mount(Institution $institution): void
    {
        $institution->load([
            'state',
            'district',
            'venues',
            'donationAccounts',
        ])->loadCount('events');

        $this->institution = $institution;
    }

    public function getUpcomingEventsProperty(): Collection
    {
        return Event::query()
            ->where('institution_id', $this->institution->id)
            ->where('status', 'approved')
            ->where('visibility', 'public')
            ->whereNotNull('published_at')
            ->where('starts_at', '>=', now()->subDay())
            ->with(['speakers', 'topics'])
            ->orderBy('starts_at')
            ->limit(6)
            ->get();
    }
};

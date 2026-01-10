<?php

use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public Event $event;

    public function mount(Event $event): void
    {
        $event->load([
            'institution',
            'venue',
            'series',
            'speakers',
            'topics',
            'state',
            'district',
            'donationAccount',
            'mediaLinks',
        ]);

        $this->event = $event;
    }

    public function getRelatedEventsProperty(): Collection
    {
        return Event::query()
            ->where('status', 'approved')
            ->where('visibility', 'public')
            ->whereNotNull('published_at')
            ->where('id', '!=', $this->event->id)
            ->when($this->event->series_id !== null, function (Builder $builder): void {
                $builder->where('series_id', $this->event->series_id);
            }, function (Builder $builder): void {
                $builder->where('institution_id', $this->event->institution_id);
            })
            ->orderBy('starts_at')
            ->limit(3)
            ->get();
    }
};

<?php

namespace App\Livewire\Pages\Events;

use App\Models\Event;
use App\Services\CalendarService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Event Details')]
class Show extends Component
{
    public Event $event;

    public bool $isSaved = false;

    public bool $isInterested = false;

    public bool $isGoing = false;

    public int $interestsCount = 0;

    public int $goingCount = 0;

    public function mount(Event $event): void
    {
        $isViewable = $event->status?->equals(\App\States\EventStatus\Approved::class)
            || $event->status?->equals(\App\States\EventStatus\Pending::class);

        if (! $isViewable || $event->visibility !== \App\Enums\EventVisibility::Public) {
            abort(404);
        }

        $event->load([
            'media',
            'institution.media',
            'venue.media',
            'speakers.media',
            'tags',
            'donationChannel',
            'settings',
        ]);

        $this->event = $event;
        $this->syncEngagementStates();
    }

    #[Computed]
    public function calendarLinks(): array
    {
        return app(CalendarService::class)->getAllCalendarLinks($this->event);
    }

    public function toggleSave(): void
    {
        $this->toggleEngagement('savedEvents', 'isSaved', 'saves_count');
    }

    public function toggleInterest(): void
    {
        $this->toggleEngagement('interestedEvents', 'isInterested', 'interests_count', 'interestsCount');
    }

    public function toggleGoing(): void
    {
        $this->toggleEngagement('goingEvents', 'isGoing', 'going_count', 'goingCount');
    }

    protected function toggleEngagement(string $relation, string $stateProperty, string $countColumn, ?string $countProperty = null): void
    {
        $user = auth()->user();

        if (! $user) {
            $this->redirectRoute('login');

            return;
        }

        if (! $this->event->status?->equals(\App\States\EventStatus\Approved::class)
            && ! $this->event->status?->equals(\App\States\EventStatus\Pending::class)
            || $this->event->visibility !== \App\Enums\EventVisibility::Public) {
            abort(403);
        }

        if ($this->{$stateProperty}) {
            $user->{$relation}()->detach($this->event->id);
            $this->event->decrement($countColumn);

            if ($countProperty) {
                $this->{$countProperty} = max(0, $this->{$countProperty} - 1);
            }

            $this->{$stateProperty} = false;
        } else {
            $user->{$relation}()->syncWithoutDetaching([$this->event->id]);
            $this->event->increment($countColumn);

            if ($countProperty) {
                $this->{$countProperty}++;
            }

            $this->{$stateProperty} = true;
        }
    }

    protected function syncEngagementStates(): void
    {
        $this->interestsCount = max(0, (int) ($this->event->interests_count ?? 0));
        $this->goingCount = max(0, (int) ($this->event->going_count ?? 0));

        $user = auth()->user();

        if (! $user) {
            $this->isSaved = false;
            $this->isInterested = false;
            $this->isGoing = false;

            return;
        }

        $this->isSaved = $user->savedEvents()->where('event_id', $this->event->id)->exists();
        $this->isInterested = $user->interestedEvents()->where('event_id', $this->event->id)->exists();
        $this->isGoing = $user->goingEvents()->where('event_id', $this->event->id)->exists();
    }

    public function render()
    {
        return view('livewire.pages.events.show');
    }
}

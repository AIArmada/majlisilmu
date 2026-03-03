<?php

namespace App\States\EventStatus\Transitions;

use AIArmada\FilamentAuthz\Facades\Authz;
use App\Models\Event;
use App\Models\ModerationReview;
use App\Models\User;
use App\Notifications\EventCancelledNotification;
use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Spatie\ModelStates\Transition;

class CancelEvent extends Transition implements HasColor, HasIcon, HasLabel
{
    public function __construct(
        public Event $event,
        public ?User $moderator = null,
        public ?string $note = null
    ) {}

    #[\Override]
    public function canTransition(): bool
    {
        return $this->moderator instanceof User;
    }

    public function handle(): Event
    {
        $this->assertTransitionContext();

        /** @var User $moderator */
        $moderator = $this->moderator;

        return DB::transaction(function () use ($moderator): Event {
            ModerationReview::create([
                'event_id' => $this->event->id,
                'moderator_id' => $moderator->id,
                'decision' => 'cancelled',
                'note' => $this->note,
            ]);

            $this->event->status = \App\States\EventStatus\Cancelled::class;
            $this->event->save();

            // Cancelled events remain searchable so users can still discover status updates.
            $this->event->searchable();

            $this->notifyCancellation($this->event);

            Log::info('Event cancelled', [
                'event_id' => $this->event->id,
                'moderator_id' => $moderator->id,
            ]);

            return $this->event;
        });
    }

    protected function assertTransitionContext(): void
    {
        if (! $this->canTransition()) {
            throw new LogicException('Moderator is required to cancel an event.');
        }
    }

    protected function notifyCancellation(Event $event): void
    {
        $notifiables = collect();

        if ($event->submitter_id) {
            $notifiables->push(User::find($event->submitter_id));
        }

        if ($event->institution_id) {
            $institutionAdmins = Authz::withScope(
                $event->institution,
                fn () => User::permission('event.update')->get()
            );
            $notifiables = $notifiables->merge($institutionAdmins);
        }

        $notifiables = $notifiables
            ->merge($event->goingBy()->get())
            ->merge($event->interestedBy()->get())
            ->merge($event->savedBy()->get());

        $notifiables
            ->filter(fn (mixed $user): bool => $user instanceof User)
            ->unique('id')
            ->each(function (User $user) use ($event): void {
                $user->notify(new EventCancelledNotification($event, $this->note));
            });
    }

    public function getLabel(): string
    {
        return __('Cancel Event');
    }

    public function getColor(): string|array
    {
        return Color::Red;
    }

    public function getIcon(): string
    {
        return 'heroicon-o-x-circle';
    }
}

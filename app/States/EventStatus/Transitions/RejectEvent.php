<?php

namespace App\States\EventStatus\Transitions;

use AIArmada\FilamentAuthz\Facades\Authz;
use App\Models\Event;
use App\Models\ModerationReview;
use App\Models\User;
use App\Notifications\EventRejectedNotification;
use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\ModelStates\Transition;

class RejectEvent extends Transition implements HasColor, HasIcon, HasLabel
{
    public function __construct(
        public Event $event,
        public User $moderator,
        public string $reasonCode,
        public ?string $note = null
    ) {}

    public function handle(): Event
    {
        return DB::transaction(function () {
            // Create review record
            $review = ModerationReview::create([
                'event_id' => $this->event->id,
                'moderator_id' => $this->moderator->id,
                'decision' => 'rejected',
                'reason_code' => $this->reasonCode,
                'note' => $this->note,
            ]);

            // Update status
            $this->event->status = \App\States\EventStatus\Rejected::class;
            $this->event->save();

            // Remove from search
            $this->event->unsearchable();

            // Notify submitter
            $this->notifyRejection($this->event, $review);

            Log::info('Event rejected', [
                'event_id' => $this->event->id,
                'moderator_id' => $this->moderator->id,
                'reason_code' => $this->reasonCode,
            ]);

            return $this->event;
        });
    }

    protected function notifyRejection(Event $event, ModerationReview $review): void
    {
        $notifiables = collect();

        if ($event->submitter_id) {
            $notifiables->push(User::find($event->submitter_id));
        }

        if ($event->institution_id) {
            $admins = Authz::withScope($event->institution, function () {
                return User::permission('event.update')->get();
            });

            $notifiables = $notifiables->merge($admins);
        }

        $notifiables->filter()->unique('id')->each(function ($user) use ($event, $review) {
            $user->notify(new EventRejectedNotification($event, $review));
        });
    }

    public function getLabel(): string
    {
        return __('Reject Event');
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

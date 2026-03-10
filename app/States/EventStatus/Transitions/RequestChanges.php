<?php

namespace App\States\EventStatus\Transitions;

use App\Models\Event;
use App\Models\ModerationReview;
use App\Models\User;
use App\Notifications\EventNeedsChangesNotification;
use App\Support\Authz\MemberPermissionGate;
use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Spatie\ModelStates\Transition;

class RequestChanges extends Transition implements HasColor, HasIcon, HasLabel
{
    public function __construct(
        public Event $event,
        public ?User $moderator = null,
        public ?string $reasonCode = null,
        public ?string $note = null
    ) {}

    #[\Override]
    public function canTransition(): bool
    {
        return $this->moderator instanceof \App\Models\User && filled($this->reasonCode);
    }

    public function handle(): Event
    {
        $this->assertTransitionContext();
        $moderator = $this->moderator;
        $reasonCode = $this->reasonCode;
        if (! $moderator instanceof User || ! is_string($reasonCode)) {
            throw new LogicException('Moderator and reason code are required to request event changes.');
        }

        return DB::transaction(function () use ($moderator, $reasonCode) {
            // Create review record
            $review = ModerationReview::create([
                'event_id' => $this->event->id,
                'moderator_id' => $moderator->id,
                'decision' => 'needs_changes',
                'reason_code' => $reasonCode,
                'note' => $this->note,
            ]);

            // Update status
            $this->event->status = \App\States\EventStatus\NeedsChanges::class;
            $this->event->save();

            // Notify submitter and institution admins
            $this->notifyNeedsChanges($this->event, $review);

            Log::info('Event needs changes', [
                'event_id' => $this->event->id,
                'moderator_id' => $moderator->id,
                'reason_code' => $reasonCode,
            ]);

            return $this->event;
        });
    }

    protected function assertTransitionContext(): void
    {
        if (! $this->canTransition()) {
            throw new LogicException('Moderator and reason code are required to request event changes.');
        }
    }

    protected function notifyNeedsChanges(Event $event, ModerationReview $review): void
    {
        $notifiables = collect();

        if ($event->submitter_id) {
            $notifiables->push(User::find($event->submitter_id));
        }

        if ($event->institution) {
            $admins = app(MemberPermissionGate::class)
                ->institutionMembersWithPermission($event->institution, 'event.update');
            $notifiables = $notifiables->merge($admins);
        }

        $notifiables->filter()->unique('id')->each(function ($user) use ($event, $review) {
            $user->notify(new EventNeedsChangesNotification($event, $review));
        });
    }

    public function getLabel(): string
    {
        return __('Request Changes');
    }

    public function getColor(): string|array
    {
        return Color::Orange;
    }

    public function getIcon(): string
    {
        return 'heroicon-o-exclamation-triangle';
    }
}

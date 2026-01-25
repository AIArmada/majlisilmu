<?php

namespace App\Services;

use AIArmada\FilamentAuthz\Facades\Authz;
use App\Models\Event;
use App\Models\ModerationReview;
use App\Models\User;
use App\Notifications\EventApprovedNotification;
use App\Notifications\EventNeedsChangesNotification;
use App\Notifications\EventRejectedNotification;
use App\Notifications\EventSubmittedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Moderation Service per documentation B4 and B4a.
 */
class ModerationService
{
    /**
     * Submit an event for moderation.
     */
    public function submitForModeration(Event $event): void
    {
        $event->status->transitionTo(\App\States\EventStatus\Pending::class);
    }

    /**
     * Approve an event.
     */
    public function approve(
        Event $event,
        ?User $moderator = null,
        ?string $note = null
    ): void {
        $event->status->transitionTo(\App\States\EventStatus\Approved::class, $moderator, $note);
    }

    /**
     * Mark event as needing changes.
     */
    public function requestChanges(
        Event $event,
        User $moderator,
        string $reasonCode,
        ?string $note = null
    ): void {
        $event->status->transitionTo(\App\States\EventStatus\NeedsChanges::class, $moderator, $reasonCode, $note);
    }

    /**
     * Reject an event.
     */
    public function reject(
        Event $event,
        User $moderator,
        string $reasonCode,
        ?string $note = null
    ): void {
        $event->status->transitionTo(\App\States\EventStatus\Rejected::class, $moderator, $reasonCode, $note);
    }

    /**
     * Handle sensitive change gating.
     * Per documentation B4.
     */
    public function handleSensitiveChange(Event $event, array $changes): void
    {
        if (! $this->isSensitiveChange($changes)) {
            return;
        }

        // Set to pending for re-review
        $event->status->transitionTo(\App\States\EventStatus\Pending::class);

        // Remove from search temporarily
        $event->unsearchable();

        // Create review record
        ModerationReview::create([
            'event_id' => $event->id,
            'decision' => 'pending_review',
            'note' => 'Sensitive change detected: '.implode(', ', array_keys($changes)),
        ]);

        // Notify moderators
        $moderators = User::role(['moderator', 'super_admin'])->get();
        Notification::send($moderators, new EventSubmittedNotification($event));

        Log::info('Sensitive change requires re-moderation', [
            'event_id' => $event->id,
            'changed_fields' => array_keys($changes),
        ]);
    }

    /**
     * Determine whether a change is considered sensitive.
     *
     * @param  array<string, mixed>  $changes
     */
    protected function isSensitiveChange(array $changes): bool
    {
        $sensitiveFields = [
            'institution_id',
            'venue_id',
            'starts_at',
            'ends_at',
            'timing_mode',
            'prayer_reference',
            'prayer_offset',
            'registration_required',
            'capacity',
            'state_id',
            'district_id',
            'lat',
            'lng',
        ];

        return (bool) array_intersect($sensitiveFields, array_keys($changes));
    }

    /**
     * Notify on approval.
     */
    protected function notifyApproval(Event $event): void
    {
        $notifiables = collect();

        // Notify submitter if user exists
        if ($event->submitter_id) {
            $notifiables->push(User::find($event->submitter_id));
        }

        // Notify institution admins
        if ($event->institution_id) {
            $admins = Authz::withScope($event->institution, function () {
                return User::permission('event.update')->get();
            });

            $notifiables = $notifiables->merge($admins);
        }

        $notifiables->filter()->unique('id')->each(function ($user) use ($event) {
            $user->notify(new EventApprovedNotification($event));
        });
    }

    /**
     * Notify on needs changes.
     */
    protected function notifyNeedsChanges(Event $event, ModerationReview $review): void
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
            $user->notify(new EventNeedsChangesNotification($event, $review));
        });
    }

    /**
     * Notify on rejection.
     */
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
}

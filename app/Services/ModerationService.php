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
use Illuminate\Support\Facades\DB;
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
        // Set to pending
        $event->update(['status' => 'pending']);

        // Notify moderators
        $moderators = User::role(['moderator', 'super_admin'])->get();
        Notification::send($moderators, new EventSubmittedNotification($event));

        Log::info('Event submitted for moderation', ['event_id' => $event->id]);
    }

    /**
     * Approve an event.
     */
    public function approve(
        Event $event,
        ?User $moderator = null,
        ?string $note = null
    ): ModerationReview {
        return DB::transaction(function () use ($event, $moderator, $note) {
            // Create review record
            $review = ModerationReview::create([
                'event_id' => $event->id,
                'moderator_id' => $moderator?->id,
                'decision' => 'approved',
                'note' => $note,
            ]);

            // Update event status
            $event->update([
                'status' => 'approved',
                'published_at' => now(),
            ]);

            // Make searchable (Scout)
            $event->searchable();

            // Notify submitter and institution admins
            $this->notifyApproval($event);

            Log::info('Event approved', [
                'event_id' => $event->id,
                'moderator_id' => $moderator?->id,
            ]);

            return $review;
        });
    }

    /**
     * Mark event as needing changes.
     */
    public function requestChanges(
        Event $event,
        User $moderator,
        string $reasonCode,
        ?string $note = null
    ): ModerationReview {
        return DB::transaction(function () use ($event, $moderator, $reasonCode, $note) {
            // Create review record
            $review = ModerationReview::create([
                'event_id' => $event->id,
                'moderator_id' => $moderator->id,
                'decision' => 'needs_changes',
                'reason_code' => $reasonCode,
                'note' => $note,
            ]);

            // Keep status as pending
            $event->update(['status' => 'pending']);

            // Notify submitter and institution admins
            $this->notifyNeedsChanges($event, $review);

            Log::info('Event needs changes', [
                'event_id' => $event->id,
                'moderator_id' => $moderator->id,
                'reason_code' => $reasonCode,
            ]);

            return $review;
        });
    }

    /**
     * Reject an event.
     */
    public function reject(
        Event $event,
        User $moderator,
        string $reasonCode,
        ?string $note = null
    ): ModerationReview {
        return DB::transaction(function () use ($event, $moderator, $reasonCode, $note) {
            // Create review record
            $review = ModerationReview::create([
                'event_id' => $event->id,
                'moderator_id' => $moderator->id,
                'decision' => 'rejected',
                'reason_code' => $reasonCode,
                'note' => $note,
            ]);

            // Update event status
            $event->update(['status' => 'rejected']);

            // Remove from search
            $event->unsearchable();

            // Notify submitter
            $this->notifyRejection($event, $review);

            Log::info('Event rejected', [
                'event_id' => $event->id,
                'moderator_id' => $moderator->id,
                'reason_code' => $reasonCode,
            ]);

            return $review;
        });
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
        $event->update(['status' => 'pending']);

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

<?php

namespace App\Policies;

use AIArmada\FilamentAuthz\Facades\Authz;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        return true; // Public can view events list
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Event $event): bool
    {
        // Public events are viewable by anyone
        if ($event->is_active && $event->visibility === EventVisibility::Public && $event->status !== null && $event->status->equals(\App\States\EventStatus\Approved::class)) {
            return true;
        }

        // Unlisted events are viewable by direct link
        if ($event->visibility === EventVisibility::Unlisted) {
            return true;
        }

        // Private events require authorization
        if (! $user) {
            return false;
        }

        // Moderators and admins can view all
        if ($user->hasAnyRole(['super_admin', 'moderator'])) {
            return true;
        }

        // Submitter can view their own submissions
        if ($event->submitter_id === $user->id) {
            return true;
        }

        // Use hybrid permission check (event members + organizer/institution scope)
        return $event->userCanView($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true; // Anyone can submit an event
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Event $event): bool
    {
        // Admins and moderators can update any event
        if ($user->hasAnyRole(['super_admin', 'moderator'])) {
            return true;
        }

        // Submitter can update their draft/pending submissions
        if ($event->submitter_id === $user->id && in_array((string) $event->status, ['draft', 'pending', 'needs_changes'])) {
            return true;
        }

        // Use hybrid permission check (event members + organizer/institution scope)
        return $event->userCanManage($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Event $event): bool
    {
        // Only admins can delete approved events
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Only draft events can be deleted by non-admins
        if ($event->status === null || ! $event->status->equals(\App\States\EventStatus\Draft::class)) {
            return false;
        }

        // Use hybrid permission check for draft events (only organizer role, not co-organizer)
        return $event->userCanDelete($user);
    }

    /**
     * Determine whether the user can moderate the event.
     */
    public function moderate(User $user, Event $event): bool
    {
        return $user->hasAnyRole(['super_admin', 'moderator']);
    }

    /**
     * Determine whether the user can export registrations.
     */
    public function exportRegistrations(User $user, Event $event): bool
    {
        // Per B9d: exports require institution owner/admin role
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Check organizer scope first
        if ($event->organizer_id && $event->organizer) {
            return Authz::userCanInScope($user, 'event.export-registrations', $event->organizer);
        }

        // Fallback to institution scope
        if ($event->institution_id) {
            return Authz::userCanInScope($user, 'event.export-registrations', $event->institution);
        }

        return false;
    }

    /**
     * Determine whether the user can manage event members.
     */
    public function manageMembers(User $user, Event $event): bool
    {
        if ($user->hasAnyRole(['super_admin', 'moderator'])) {
            return true;
        }

        // Check event-scoped permission via Authz
        if (Authz::userCanInScope($user, 'event.manage-members', $event)) {
            return true;
        }

        // Check organizer scope
        if ($event->organizer_id && $event->organizer) {
            return Authz::userCanInScope($user, 'event.manage-members', $event->organizer);
        }

        // Fallback to institution scope
        if ($event->institution_id) {
            return Authz::userCanInScope($user, 'event.manage-members', $event->institution);
        }

        return false;
    }
}

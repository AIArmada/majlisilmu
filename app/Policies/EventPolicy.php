<?php

namespace App\Policies;

use AIArmada\FilamentAuthz\Facades\Authz;
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
        if ($event->visibility === 'public' && $event->status === 'approved') {
            return true;
        }

        // Unlisted events are viewable by direct link
        if ($event->visibility === 'unlisted') {
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

        // Institution members can view their events
        if ($event->institution_id) {
            return Authz::userCanInScope($user, 'event.view', $event->institution);
        }

        // Submitter can view their own submissions
        return $event->submitter_id === $user->id;
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

        // Institution members can update their institution's events
        if ($event->institution_id) {
            return Authz::userCanInScope($user, 'event.update', $event->institution);
        }

        // Submitter can update their draft/pending submissions
        if ($event->submitter_id === $user->id && in_array($event->status, ['draft', 'pending', 'needs_changes'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Event $event): bool
    {
        // Only admins can delete events
        if ($user->hasAnyRole(['super_admin'])) {
            return true;
        }

        // Institution admins can delete draft events
        if ($event->status === 'draft' && $event->institution_id) {
            return Authz::userCanInScope($user, 'event.delete', $event->institution);
        }

        return false;
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

        if ($event->institution_id) {
            return Authz::userCanInScope($user, 'event.export-registrations', $event->institution);
        }

        return false;
    }
}

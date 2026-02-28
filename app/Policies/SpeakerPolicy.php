<?php

namespace App\Policies;

use AIArmada\FilamentAuthz\Facades\Authz;
use App\Models\Speaker;
use App\Models\User;

class SpeakerPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        return true; // Public can view speakers list
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Speaker $speaker): bool
    {
        // Verified + active speakers are publicly viewable
        if ($speaker->status === 'verified' && $speaker->is_active) {
            return true;
        }

        if (! $user instanceof \App\Models\User) {
            return false;
        }

        // Admins can view any speaker
        if ($user->hasAnyRole(['super_admin', 'moderator'])) {
            return true;
        }

        return Authz::userCanInScope($user, 'speaker.view', $speaker);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Any authenticated user can create a speaker profile
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Speaker $speaker): bool
    {
        // Admins can update any speaker
        if ($user->hasAnyRole(['super_admin', 'moderator'])) {
            return true;
        }

        return Authz::userCanInScope($user, 'speaker.update', $speaker);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Speaker $speaker): bool
    {
        // Only super admins and speaker owners can delete
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return Authz::userCanInScope($user, 'speaker.delete', $speaker);
    }

    /**
     * Determine whether the user can manage speaker members.
     */
    public function manageMembers(User $user, Speaker $speaker): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return Authz::userCanInScope($user, 'speaker.manage-members', $speaker);
    }
}

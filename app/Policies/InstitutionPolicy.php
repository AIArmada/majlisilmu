<?php

namespace App\Policies;

use AIArmada\FilamentAuthz\Facades\Authz;
use App\Models\Institution;
use App\Models\User;

class InstitutionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        return true; // Public can view institutions list
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Institution $institution): bool
    {
        // Verified and pending institutions are publicly viewable
        if (in_array($institution->verification_status, ['verified', 'pending'])) {
            return true;
        }

        if (! $user) {
            return false;
        }

        // Admins can view any institution
        if ($user->hasAnyRole(['super_admin', 'moderator'])) {
            return true;
        }

        return Authz::userCanInScope($user, 'institution.view', $institution);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Any authenticated user can create an institution
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Institution $institution): bool
    {
        // Admins can update any institution
        if ($user->hasAnyRole(['super_admin', 'moderator'])) {
            return true;
        }

        return Authz::userCanInScope($user, 'institution.update', $institution);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Institution $institution): bool
    {
        // Only super admins and institution owners can delete
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return Authz::userCanInScope($user, 'institution.delete', $institution);
    }

    /**
     * Determine whether the user can manage members.
     */
    public function manageMembers(User $user, Institution $institution): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return Authz::userCanInScope($user, 'institution.manage-members', $institution);
    }

    /**
     * Determine whether the user can manage donations.
     */
    public function manageDonations(User $user, Institution $institution): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return Authz::userCanInScope($user, 'institution.manage-donations', $institution);
    }
}

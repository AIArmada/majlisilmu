<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Only moderators and admins can view reports list
        return $user->hasAnyRole(['super_admin', 'moderator']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Report $report): bool
    {
        // Moderators and admins can view any report
        if ($user->hasAnyRole(['super_admin', 'moderator'])) {
            return true;
        }

        // Reporter can view their own report
        return $report->reporter_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(?User $user): bool
    {
        // Anyone can create a report (guests too with rate limiting)
        return true;
    }

    /**
     * Determine whether the user can resolve/dismiss the report.
     */
    public function resolve(User $user, Report $report): bool
    {
        return $user->hasAnyRole(['super_admin', 'moderator']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Report $report): bool
    {
        return $user->hasRole('super_admin');
    }
}

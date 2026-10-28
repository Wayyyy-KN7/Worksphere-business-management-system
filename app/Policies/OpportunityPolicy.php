<?php

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;

class OpportunityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSales() || $user->isMarketing();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine if the user can view the opportunity.
     */
    public function view(User $user, Opportunity $opportunity): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($opportunity->assigned_to === $user->id && ($user->isSales() || $user->isMarketing())) {
            return true;
        }

        if (($user->isSales() || $user->isMarketing()) && $opportunity->assignedUser) {
            return $opportunity->assignedUser->department_id === $user->department_id;
        }

        return false;
    }

    /**
     * Determine if the user can update the opportunity.
     */
    public function update(User $user, Opportunity $opportunity): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($opportunity->assigned_to === $user->id && ($user->isSales() || $user->isMarketing())) {
            return true;
        }

        if ($user->isManager() && ($user->isSales() || $user->isMarketing()) && $opportunity->assignedUser) {
            return $opportunity->assignedUser->department_id === $user->department_id;
        }

        return false;
    }

    /**
     * Determine if the user can delete the opportunity.
     */
    public function delete(User $user, Opportunity $opportunity): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isManager() && ($user->isSales() || $user->isMarketing()) && $opportunity->assignedUser) {
            return $opportunity->assignedUser->department_id === $user->department_id;
        }

        return false;
    }

    public function assignTo(User $user, User $assignee): bool
    {
        if (!$assignee->isSales() && !$assignee->isMarketing()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (!$user->isSales() && !$user->isMarketing()) {
            return false;
        }

        return $user->isManager()
            ? $assignee->department_id === $user->department_id
            : $assignee->id === $user->id;
    }
}   
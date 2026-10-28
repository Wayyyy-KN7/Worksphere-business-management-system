<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Opportunity;
use App\Models\User;

class FollowUpPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isSales();
    }

    public function view(User $user, FollowUp $followUp): bool
    {
        if ($followUp->employee_id !== null) {
            return false;
        }

        if ($user->isAdmin() || $followUp->assigned_to === $user->id) {
            return true;
        }

        return $user->isManager()
            && $user->department_id
            && $followUp->assignedUser?->department_id === $user->department_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isSales();
    }

    public function update(User $user, FollowUp $followUp): bool
    {
        if ($followUp->employee_id !== null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->isManager()
            && $user->department_id
            && $followUp->assignedUser?->department_id === $user->department_id;
    }

    public function perform(User $user, FollowUp $followUp): bool
    {
        return $this->view($user, $followUp);
    }

    public function delete(User $user, FollowUp $followUp): bool
    {
        return $this->update($user, $followUp);
    }

    public function linkCustomer(User $user, Customer $customer): bool
    {
        return app(CustomerPolicy::class)->view($user, $customer);
    }

    public function linkOpportunity(User $user, Opportunity $opportunity): bool
    {
        return app(OpportunityPolicy::class)->view($user, $opportunity);
    }
}
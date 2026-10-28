<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSales();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine if the user can view the sale.
     */
    public function view(User $user, Sale $sale): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isSales() && $sale->assigned_to === $user->id) {
            return true;
        }

        if ($user->isManager() && $user->isSales()) {
            return ($sale->assignedUser && $sale->assignedUser->department_id === $user->department_id)
                || ($sale->customer?->assignedUser
                    && $sale->customer->assignedUser->department_id === $user->department_id);
        }

        return false;
    }

    /**
     * Determine if the user can update the sale.
     */
    public function update(User $user, Sale $sale): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isManager() && $user->isSales()) {
            return ($sale->assignedUser && $sale->assignedUser->department_id === $user->department_id)
                || ($sale->customer?->assignedUser
                    && $sale->customer->assignedUser->department_id === $user->department_id);
        }

        return false;
    }

    /**
     * Determine if the user can delete the sale.
     */
    public function delete(User $user, Sale $sale): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isManager() && $user->isSales()) {
            return ($sale->assignedUser && $sale->assignedUser->department_id === $user->department_id)
                || ($sale->customer?->assignedUser
                    && $sale->customer->assignedUser->department_id === $user->department_id);
        }

        return false;
    }
}
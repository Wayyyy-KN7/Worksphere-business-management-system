<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
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
     * Determine if the user can view the customer.
     */
    public function view(User $user, Customer $customer): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (($user->isSales() || $user->isMarketing())
            && ($customer->assigned_to === $user->id || $customer->created_by === $user->id)) {
            return true;
        }

        if ($user->isSales()) {
            return ($user->department_id !== null && (
                    $customer->assigned_department_id === $user->department_id
                    || $customer->assignedUser?->department_id === $user->department_id
                ))
                || in_array($customer->creatorDepartment?->code, ['MARKETING', 'MKT'], true);
        }

        if (!$user->isMarketing()) {
            return false;
        }

        return ($user->department_id !== null && (
                $customer->assigned_department_id === $user->department_id
                || $customer->assignedUser?->department_id === $user->department_id
                || ($customer->created_department_id === $user->department_id && $user->isManager())
            ));
    }

    /**
     * Determine if the user can update the customer.
     */
    public function update(User $user, Customer $customer): bool
    {
        // Admin can update all
        if ($user->isAdmin()) {
            return true;
        }

        if (($user->isSales() || $user->isMarketing()) && $customer->assigned_to === $user->id) {
            return true;
        }

        if (!$user->isManager() || (!$user->isSales() && !$user->isMarketing())) {
            return false;
        }

        return $user->department_id !== null && (
            $customer->assigned_department_id === $user->department_id
            || $customer->assignedUser?->department_id === $user->department_id
            || ($customer->created_department_id === $user->department_id
                && $user->isMarketing())
            || ($user->isSales()
                && $user->isManager()
                && in_array($customer->creatorDepartment?->code, ['MARKETING', 'MKT'], true))
        );
    }

    public function manageActivities(User $user, Customer $customer): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isSales()) {
            if ($user->department_id === null) {
                return false;
            }

            $assignedDepartmentId = $customer->assigned_department_id
                ?? $customer->assignedUser?->department_id;

            return $assignedDepartmentId === $user->department_id
                && ($user->isManager() || $customer->assigned_to === $user->id);
        }

        return $user->isMarketing() && $this->update($user, $customer);
    }

    /**
     * Determine if the user can delete the customer.
     */
    public function delete(User $user, Customer $customer): bool
    {
        return $user->isAdmin();
    }

    public function archive(User $user, Customer $customer): bool
    {
        return $this->update($user, $customer);
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $this->update($user, $customer);
    }
}
<?php

namespace App\Policies;

use App\Models\Reminder;
use App\Models\User;

class ReminderPolicy
{
    public function view(User $user, Reminder $reminder): bool
    {
        if ($reminder->employee_id !== null || $reminder->reminder_type !== null) {
            return false;
        }

        if ($user->isAdmin() || $reminder->user_id === $user->id) {
            return true;
        }

        return $user->isManager()
            && $user->department_id
            && $reminder->user?->department_id === $user->department_id;
    }

    public function update(User $user, Reminder $reminder): bool
    {
        return $this->view($user, $reminder);
    }

    public function delete(User $user, Reminder $reminder): bool
    {
        return $reminder->employee_id === null
            && $reminder->reminder_type === null
            && ($user->isAdmin() || $reminder->user_id === $user->id);
    }
}
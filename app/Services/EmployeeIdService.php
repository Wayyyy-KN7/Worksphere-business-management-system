<?php

namespace App\Services;

use App\Models\User;

class EmployeeIdService
{
    /**
     * Generate next employee ID
     * Format: EMP-YYYY-XXX (e.g., EMP-2024-001)
     */
    public static function generate(): string
    {
        $year = now()->year;
        $prefix = "EMP-{$year}-";

        // Get the last employee ID for current year
        $lastEmployee = User::where('employee_id', 'like', "{$prefix}%")
            ->orderBy('employee_id', 'desc')
            ->first();

        if ($lastEmployee) {
            // Extract the number part
            $lastNumber = (int) substr($lastEmployee->employee_id, -3);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        // Format with leading zeros
        return $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Check if employee ID exists
     */
    public static function exists(string $employeeId): bool
    {
        return User::where('employee_id', $employeeId)->exists();
    }

    /**
     * Generate unique employee ID (ensure no duplicates)
     */
    public static function generateUnique(): string
    {
        do {
            $employeeId = self::generate();
        } while (self::exists($employeeId));

        return $employeeId;
    }
}
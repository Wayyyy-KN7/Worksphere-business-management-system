<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $fillable = [
        'name', 'code', 'days_allowed', 'description',
        'requires_document', 'is_paid',
        'can_carry_forward', 'max_carry_forward', 'status',
    ];

    protected $casts = [
        'requires_document' => 'boolean',
        'is_paid' => 'boolean',
        'can_carry_forward' => 'boolean',
    ];

    /**
     * Boot method - runs when model is loaded
     */
    protected static function booted()
    {
        // Auto-create leave balances when new leave type is created
        static::created(function ($leaveType) {
            if ($leaveType->status === 'ACTIVE') {
                self::createBalancesForAllEmployees($leaveType);
            }
        });
    }

    /**
     * Create leave balances for all active employees
     */
    private static function createBalancesForAllEmployees($leaveType)
    {
        $currentYear = now()->year;
        $users = \App\Models\User::where('status', 'ACTIVE')->get();

        foreach ($users as $user) {
            \App\Models\LeaveBalance::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'leave_type_id' => $leaveType->id,
                    'year' => $currentYear,
                ],
                [
                    'total_days' => $leaveType->days_allowed,
                    'used_days' => 0,
                    'pending_days' => 0,
                    'carried_forward' => 0,
                ]
            );
        }
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
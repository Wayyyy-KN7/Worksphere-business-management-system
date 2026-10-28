<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class FollowUp extends Model
{
    protected $fillable = [
        'customer_id', 'opportunity_id', 'title', 'description',
        'follow_up_date', 'follow_up_time', 'type',
        'status', 'assigned_to', 'completed_at', 'notes', 'priority', 'created_by',
        'recurrence_rule', 'recurrence_until', 'recurrence_parent_id',
        'next_occurrence_created_at', 'due_notified_at',
    ];

    protected $casts = [
        'follow_up_date' => 'date',
        'recurrence_until' => 'date',
        'completed_at' => 'datetime',
        'next_occurrence_created_at' => 'datetime',
        'due_notified_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }

    public function histories()
    {
        return $this->morphMany(CrmActivityHistory::class, 'activity')->latest();
    }

    public function getDueAtAttribute(): Carbon
    {
        return $this->follow_up_date->copy()->setTimeFromTimeString($this->follow_up_time ?: '23:59:59');
    }

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isManager() && $user->department_id) {
            return $query->whereHas('assignedUser', fn ($assigned) => $assigned->where('department_id', $user->department_id));
        }

        return $query->where('assigned_to', $user->id);
    }

    public function scopeOverdue($query)
    {
        return $query->whereIn('status', ['PLANNED', 'IN_PROGRESS'])
            ->where(function ($due) {
                $due->whereDate('follow_up_date', '<', today())
                    ->orWhere(function ($todayDue) {
                        $todayDue->whereDate('follow_up_date', today())
                            ->whereNotNull('follow_up_time')
                            ->where('follow_up_time', '<', now()->format('H:i:s'));
                    });
            });
    }
}
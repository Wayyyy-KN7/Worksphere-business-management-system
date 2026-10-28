<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    protected $fillable = [
        'user_id',
        'customer_id', 
        'opportunity_id', 
        'title', 
        'description',
        'reminder_date', 
        'reminder_time',
        'status', 
        'is_notified',
        'follow_up_id', 'remindable_type', 'remindable_id', 'snoozed_until',
        'due_notified_at', 'overdue_notified_at',
    ];

    protected $casts = [
        'reminder_date' => 'date',
        'is_notified' => 'boolean',
        'snoozed_until' => 'datetime',
        'due_notified_at' => 'datetime',
        'overdue_notified_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function followUp()
    {
        return $this->belongsTo(FollowUp::class);
    }

    public function remindable()
    {
        return $this->morphTo();
    }

    public function histories()
    {
        return $this->morphMany(CrmActivityHistory::class, 'activity')->latest();
    }

    public function getDueAtAttribute()
    {
        return $this->reminder_date->copy()->setTimeFromTimeString($this->reminder_time ?: '23:59:59');
    }

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isManager() && $user->department_id) {
            return $query->whereHas('user', fn ($assigned) => $assigned->where('department_id', $user->department_id));
        }

        return $query->where('user_id', $user->id);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'user_id', 'attendance_date', 'check_in', 'check_out',
        'working_hours', 'status', 'late_minutes',
        'is_manual', 'manual_reason', 'notes','checked_in_by',   
    'checked_out_by',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'is_manual' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function checkedInBy()
{
    return $this->belongsTo(User::class, 'checked_in_by');
}

public function checkedOutBy()
{
    return $this->belongsTo(User::class, 'checked_out_by');
}
}
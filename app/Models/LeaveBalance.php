<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'leave_type_id',
        'year',
        'total_days',
        'used_days',
        'pending_days',
        'carried_forward',
    ];

    protected $casts = [
        'year'            => 'integer',
        'total_days'      => 'float',
        'used_days'       => 'float',
        'pending_days'    => 'float',
        'carried_forward' => 'float',
    ];

    /**
     * Appends remaining_days to array / JSON output automatically
     */
    protected $appends = [
        'remaining_days',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * Compute remaining leave days safely
     */
    public function getRemainingDaysAttribute(): float
    {
        $total = $this->total_days + $this->carried_forward;
        $used  = $this->used_days + $this->pending_days;

        return max(0, (float) ($total - $used));
    }
}
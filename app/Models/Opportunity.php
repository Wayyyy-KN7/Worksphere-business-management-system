<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    protected $fillable = [
        'opportunity_number', 'customer_id', 'title', 'description',
        'estimated_value', 'probability', 'expected_close_date',
        'actual_close_date', 'stage', 'status',
        'assigned_to', 'lost_reason',
    ];

    protected $casts = [
        'expected_close_date' => 'date',
        'actual_close_date' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function followUps()
    {
        return $this->hasMany(FollowUp::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
    public function documents()
{
    return $this->morphMany(Document::class, 'documentable');
}
}
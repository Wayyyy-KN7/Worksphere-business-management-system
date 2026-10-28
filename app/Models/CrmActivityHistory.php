<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmActivityHistory extends Model
{
    protected $fillable = [
        'changed_by', 'action', 'from_status', 'to_status', 'notes',
    ];

    public function activity()
    {
        return $this->morphTo();
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
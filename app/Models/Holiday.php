<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = ['name', 'holiday_date', 'description', 'is_recurring'];

    protected $casts = [
        'holiday_date' => 'date',
        'is_recurring' => 'boolean',
    ];
}
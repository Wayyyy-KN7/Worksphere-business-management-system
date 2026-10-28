<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesForecast extends Model
{
    protected $fillable = [
        'forecast_number', 'title', 'department_id',
        'forecast_period', 'start_date', 'end_date',
        'target_amount', 'forecasted_amount', 'actual_amount', 'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
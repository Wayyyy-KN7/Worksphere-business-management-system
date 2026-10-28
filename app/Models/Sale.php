<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'sale_number', 'customer_id', 'opportunity_id', 'sale_date',
        'total_amount', 'discount_amount', 'tax_amount', 'final_amount',
        'payment_status', 'payment_method', 'assigned_to', 'notes',
    ];

    protected $casts = [
        'sale_date' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSource extends Model
{
    protected $fillable = ['name'];

    public function customers()
    {
        return $this->hasMany(Customer::class, 'source_id');
    }
}
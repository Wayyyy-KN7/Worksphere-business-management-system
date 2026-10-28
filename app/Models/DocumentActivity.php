<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentActivity extends Model
{
    protected $fillable = [
        'document_id', 'user_id', 'action',
        'description', 'ip_address',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
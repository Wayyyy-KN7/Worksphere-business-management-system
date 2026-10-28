<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentApproval extends Model
{
    protected $fillable = [
        'document_id', 'reviewer_id',
        'status', 'comment', 'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
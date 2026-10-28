<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'table_name',
        'record_id', 'description', 'ip_address', 'module', 'old_values', 'new_values',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Helper to create log quickly
    public static function log(
        string $action,
        string $table,
        int $recordId,
        string $description,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $module = null,
    ): void
    {
        self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'table_name' => $table,
            'module' => $module ?? Str::headline($table),
            'record_id' => $recordId,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
        ]);
    }
}
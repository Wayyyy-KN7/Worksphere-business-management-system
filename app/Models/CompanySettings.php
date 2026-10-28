<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySettings extends Model
{
    protected $fillable = [
        'company_name', 'logo_path', 'address', 'phone', 'email', 'timezone',
        'currency', 'date_format', 'working_days', 'work_start', 'work_end',
        'max_failed_login_attempts', 'account_lockout_minutes', 'session_timeout_minutes',
    ];

    protected $casts = [
        'working_days' => 'array',
    ];

    public static function current(): self
    {
        return static::find(1) ?? new static(static::defaults());
    }

    public static function defaults(): array
    {
        return [
            'company_name' => config('app.name', 'Internal CRM'),
            'timezone' => config('app.timezone', 'Asia/Yangon'),
            'currency' => 'USD',
            'date_format' => 'Y-m-d',
            'working_days' => ['MON', 'TUE', 'WED', 'THU', 'FRI'],
            'work_start' => '09:00',
            'work_end' => '17:00',
            'max_failed_login_attempts' => 5,
            'account_lockout_minutes' => 15,
            'session_timeout_minutes' => 120,
        ];
    }
}
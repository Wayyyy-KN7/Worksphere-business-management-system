<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'employee_id', 'name', 'email', 'password', 'phone', 'profile_photo_path',
        'department_id', 'role', 'position', 'joining_date',
        'status', 'last_login',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'joining_date' => 'date',
        'last_login' => 'datetime',
        'password' => 'hashed',
    ];

    public function getInitialsAttribute(): string
    {
        $nameParts = preg_split('/\s+/', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = collect(array_slice($nameParts, 0, 2))
            ->map(fn (string $part) => mb_substr($part, 0, 1))
            ->implode('');

        return mb_strtoupper($initials ?: mb_substr($this->name, 0, 2));
    }

    // ── Relationships ──

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class, 'assigned_to');
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class, 'assigned_to');
    }

    public function followUps()
    {
        return $this->hasMany(FollowUp::class, 'assigned_to');
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'assigned_to');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'uploaded_by');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get unread custom notifications.
     */
    public function unreadNotifications()
    {
        return Notification::query()->where('is_read', false)
            ->when(!$this->isAdmin(), fn ($query) => $query->where('user_id', $this->id));
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    // ── Role Helper Methods ──

    /**
     * Check if user is system admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }

    /**
     * Check if user is a manager (any department)
     */
    public function isManager(): bool
    {
        return $this->isAdmin() || str_ends_with($this->role, '_MANAGER');
    }

    /**
     * Check if user is manager of their own department
     */
    public function isDepartmentManager(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (!$this->department) {
            return false;
        }

        $expectedRole = strtoupper($this->department->code) . '_MANAGER';
        return $this->role === $expectedRole;
    }

    /**
     * Check if user belongs to specific department
     */
    public function belongsToDepartment(string $departmentCode): bool
    {
        if (!$this->department) {
            return false;
        }
        return strtoupper($this->department->code) === strtoupper($departmentCode);
    }

    /**
     * Check if user is HR (manager or employee)
     */
    public function isHR(): bool
    {
        return $this->isAdmin() || 
               $this->role === 'HR_MANAGER' || 
               $this->role === 'HR_EMPLOYEE' ||
               $this->belongsToDepartment('HR');
    }

    public function canViewCompanyAttendance(): bool
    {
        return $this->isAdmin()
            || ($this->role === 'HR_MANAGER' && $this->belongsToDepartment('HR'));
    }

    /**
     * Check if user is Sales (manager or employee)
     */
    public function isSales(): bool
    {
        return $this->isAdmin() || 
               $this->role === 'SALES_MANAGER' || 
               $this->role === 'SALES_EMPLOYEE' ||
               $this->belongsToDepartment('SALES');
    }

    /**
     * Check if user is Marketing (manager or employee)
     */
    public function isMarketing(): bool
    {
        return $this->isAdmin() || 
               $this->role === 'MARKETING_MANAGER' || 
               $this->role === 'MARKETING_EMPLOYEE' ||
               $this->belongsToDepartment('MARKETING') ||
               $this->belongsToDepartment('MKT');
    }

    /**
     * Check if user is IT (manager or employee)
     */
    public function isIT(): bool
    {
        return $this->isAdmin() || 
               $this->role === 'IT_MANAGER' || 
               $this->role === 'IT_EMPLOYEE' ||
               $this->belongsToDepartment('IT');
    }

    /**
     * Check if user can manage a specific department
     */
    public function canManageDepartment(int $departmentId): bool
    {
        return $this->isAdmin() ||
               ($this->isDepartmentManager() && $this->department_id == $departmentId);
    }

    /**
     * Get role label (formatted)
     */
    public function getRoleLabelAttribute(): string
    {
        return str_replace('_', ' ', ucwords(strtolower($this->role)));
    }

    /**
     * Get available roles for a department
     */
    public static function getRolesForDepartment(?int $departmentId): array
    {
        $roles = ['ADMIN' => 'System Admin'];

        if ($departmentId) {
            $department = Department::find($departmentId);
            if ($department) {
                $code = strtoupper($department->code);
                $roles["{$code}_MANAGER"] = "{$department->name} Manager";
                $roles["{$code}_EMPLOYEE"] = "{$department->name} Employee";
            }
        }

        $roles['EMPLOYEE'] = 'General Employee';

        return $roles;
    }

    /**
     * Get count of unread notifications
     */
    public function unreadNotificationsCount(): int
    {
        return $this->unreadNotifications()->count();
    }
}
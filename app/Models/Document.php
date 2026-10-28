<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_number',
        'title',
        'description',
        'document_type',
        'department_id',
        'uploaded_by',
        'access_level',
        'status',
        'archived_at',
        'documentable_type',
        'documentable_id'
    ];

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->status !== 'ACTIVE') {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin()) {
            return $query;
        }

        $canReviewDepartment = $user->department_id && $user->canManageDepartment((int) $user->department_id);

        return $query->where(function ($visible) use ($user, $canReviewDepartment) {
            $visible->where('uploaded_by', $user->id)
                ->orWhere(function ($published) use ($user, $canReviewDepartment) {
                    $published->whereIn('status', ['PUBLISHED', 'APPROVED'])
                        ->where(function ($access) use ($user, $canReviewDepartment) {
                            $access->where('access_level', 'COMPANY_WIDE')
                                ->orWhere(function ($department) use ($user) {
                                    $department->where('access_level', 'DEPARTMENT')
                                        ->where('department_id', $user->department_id);
                                })
                                    ->when($canReviewDepartment, function ($access) use ($user) {
                                        $access->orWhere(function ($private) use ($user) {
                                            $private->where('access_level', 'PRIVATE')
                                                ->where('department_id', $user->department_id);
                                        });
                                    })
                                ->orWhereHas('shares', function ($share) use ($user) {
                                    $this->constrainSharesForUser($share, $user, ['VIEW', 'DOWNLOAD', 'EDIT']);
                                });
                        });
                });

            if ($canReviewDepartment) {
                $visible->orWhere(function ($pending) use ($user) {
                    $pending->where('status', 'PENDING_REVIEW')
                        ->where('department_id', $user->department_id);
                });
            }
        });
    }

    public function visibleTo(User $user): bool
    {
        return $user->status === 'ACTIVE'
            && static::query()->visibleTo($user)->whereKey($this->getKey())->exists();
    }

    public function canDownload(User $user): bool
    {
        if (!$this->visibleTo($user)) {
            return false;
        }

        if ($this->canManage($user)) {
            return true;
        }

        if ($this->status === 'PENDING_REVIEW') {
            return $this->department_id && $user->canManageDepartment((int) $this->department_id);
        }

        if ($this->access_level === 'COMPANY_WIDE'
            || ($this->access_level === 'DEPARTMENT' && (int) $this->department_id === (int) $user->department_id)) {
            return true;
        }

        return $this->hasValidShareFor($user, ['DOWNLOAD', 'EDIT']);
    }

    public function canEdit(User $user): bool
    {
        if (!$this->visibleTo($user)) {
            return false;
        }

        return $this->canManage($user)
            || $this->hasValidShareFor($user, ['EDIT']);
    }

    public function canManage(User $user): bool
    {
        return $user->isAdmin()
            || $this->uploaded_by === $user->id
            || ($this->department_id && $user->canManageDepartment((int) $this->department_id));
    }

    public function canBeSharedBy(User $user): bool
    {
        return $this->status === 'PUBLISHED' && $this->canManage($user);
    }

    public function hasValidShareFor(User $user, ?array $accessTypes = null): bool
    {
        $accessTypes ??= ['VIEW', 'DOWNLOAD', 'EDIT'];

        return $this->shares()->where(function ($share) use ($user, $accessTypes) {
            $this->constrainSharesForUser($share, $user, $accessTypes);
        })->exists();
    }

    private function constrainSharesForUser($query, User $user, ?array $accessTypes = null): void
    {
        $query->where(function ($target) use ($user) {
            $target->where('user_id', $user->id);
            if ($user->department_id) {
                $target->orWhere('department_id', $user->department_id);
            }
        })->where(function ($expires) {
            $expires->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->when($accessTypes, fn ($query) => $query->whereIn('access_type', $accessTypes));
    }

    public function canBeApprovedBy(User $user): bool
    {
        if ($this->status !== 'PENDING_REVIEW') {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (!$this->department_id) {
            return false;
        }

        return $user->department_id === $this->department_id && str_ends_with($user->role, '_MANAGER');
    }

    public static function defaultStatusForUser(User $user): string
    {
        if ($user->isAdmin() || str_ends_with($user->role, '_MANAGER')) {
            return 'PUBLISHED';
        }

        return 'PENDING_REVIEW';
    }

    public function isManagerUploadedByRole(): bool
    {
        $uploader = $this->uploader;

        if (!$uploader) {
            return false;
        }

        return $uploader->isAdmin() || str_ends_with($uploader->role, '_MANAGER');
    }

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    // Polymorphic relationship
    public function documentable()
    {
        return $this->morphTo();
    }

    // Rest of relationships remain the same...
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function versions()
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function latestVersion()
    {
        return $this->hasOne(DocumentVersion::class)->latestOfMany();
    }

    public function approvals()
    {
        return $this->hasMany(DocumentApproval::class);
    }

    public function shares()
    {
        return $this->hasMany(DocumentShare::class);
    }

    public function activities()
    {
        return $this->hasMany(DocumentActivity::class);
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($document) {
            $document->document_number = 'DOC-' . date('Y') . '-' . str_pad(Document::count() + 1, 5, '0', STR_PAD_LEFT);
        });
    }

    public function logActivity($action, $description = null)
    {
        $this->activities()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip()
        ]);
    }
}
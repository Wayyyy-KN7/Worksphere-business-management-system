<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_code', 'name', 'company_name', 'email', 'phone',
        'address', 'city', 'country', 'customer_type',
        'source_id', 'created_by', 'created_department_id', 'assigned_department_id',
        'assigned_to', 'status', 'notes',
    ];

    public function source()
    {
        return $this->belongsTo(CustomerSource::class, 'source_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function creatorDepartment()
    {
        return $this->belongsTo(Department::class, 'created_department_id');
    }

    public function assignedDepartment()
    {
        return $this->belongsTo(Department::class, 'assigned_department_id');
    }

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isSales()) {
            return $query->where(function ($visible) use ($user) {
                if ($user->department_id) {
                    $visible->where('assigned_department_id', $user->department_id)
                        ->orWhereHas('assignedUser', fn ($assigned) => $assigned->where('department_id', $user->department_id));
                }

                $visible->orWhereIn('created_department_id', Department::query()
                        ->select('id')
                        ->whereIn('code', ['MARKETING', 'MKT']));
            });
        }

        if ($user->isMarketing()) {
            return $query->where(function ($visible) use ($user) {
                if ($user->department_id) {
                    $visible->where('assigned_department_id', $user->department_id)
                        ->orWhereHas('assignedUser', fn ($assigned) => $assigned->where('department_id', $user->department_id));
                }

                if ($user->department_id) {
                    $visible->orWhere(function ($origin) use ($user) {
                        $origin->where('created_department_id', $user->department_id);

                        if (!$user->isManager()) {
                            $origin->where('created_by', $user->id);
                        }
                    });
                } else {
                    $visible->orWhere('created_by', $user->id);
                }
            });
        }

        return $query->where(function ($visible) use ($user) {
            $visible->where('assigned_to', $user->id)
                ->orWhere('created_by', $user->id);
        });
    }

    public function scopeManageableBy($query, User $user)
    {
        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isManager() && $user->department_id && ($user->isSales() || $user->isMarketing())) {
            return $query->where(function ($manageable) use ($user) {
                $manageable->where('assigned_department_id', $user->department_id)
                    ->orWhereHas('assignedUser', fn ($assigned) => $assigned->where('department_id', $user->department_id));

                if ($user->isMarketing()) {
                    $manageable->orWhere('created_department_id', $user->department_id);
                }
            });
        }

        if ($user->isSales() || $user->isMarketing()) {
            return $query->where(function ($manageable) use ($user) {
                $manageable->where('assigned_to', $user->id)
                    ->orWhere('created_by', $user->id);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }

    public function followUps()
    {
        return $this->hasMany(FollowUp::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);

    }
    public function documents()
{
    return $this->morphMany(Document::class, 'documentable');
}
}
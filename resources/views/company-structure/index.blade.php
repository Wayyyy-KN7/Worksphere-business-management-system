@extends('layouts.app')

@section('title', 'Company Structure')
@section('breadcrumb', 'Company Structure')

@push('styles')
<style>
    .structure-employee-avatar {
        display: inline-flex;
        flex: 0 0 40px;
        width: 40px;
        height: 40px;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border-radius: 50%;
        background: #DBEAFE;
        color: var(--primary-dark);
        font-size: 0.75rem;
        font-weight: 600;
    }
    .structure-employee-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .structure-employee-details { min-width: 0; }
</style>
@endpush

@section('content')
<div class="page-header">
    <h2><i class="bi bi-diagram-3 me-2"></i>Company Structure</h2>
    <p>
        @if(auth()->user()->isAdmin())
            Full company visibility across all departments and employees.
        @elseif(auth()->user()->isManager())
            Department visibility for your management scope.
        @else
            Department list and your department employee overview.
        @endif
    </p>
</div>

<div class="row g-4">
    @php
        $user = auth()->user();
        $showAllDepartments = $user->isAdmin();
        $showAllEmployees = $user->isAdmin();
        $managerScopeDepartmentId = $user->isManager() && $user->department_id ? $user->department_id : null;
    @endphp

    @foreach($departments as $department)
        @if($showAllDepartments || $department->id === $managerScopeDepartmentId || $user->department_id === $department->id)
            <div class="col-12">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h4 class="mb-1">{{ $department->name }}</h4>
                                <small class="text-muted">{{ $department->code }} · {{ $department->users_count }} employees</small>
                            </div>
                            @if($showAllDepartments || $user->department_id === $department->id || $managerScopeDepartmentId === $department->id)
                                <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">
                                    {{ $department->users->count() }} visible
                                </span>
                            @endif
                        </div>

                        @if($department->users->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Employee ID</th>
                                            <th>Employee</th>
                                            <th>Role</th>
                                            <th>Position</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($department->users as $employee)
                                            @if(
                                                $showAllEmployees ||
                                                $employee->id === $user->id ||
                                                $employee->department_id === $user->department_id ||
                                                ($user->isManager() && $employee->department_id === $managerScopeDepartmentId)
                                            )
                                                <tr>
                                                    <td><strong>{{ $employee->employee_id }}</strong></td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="structure-employee-avatar" aria-hidden="true">
                                                                @if($employee->profile_photo_url)
                                                                    <img src="{{ $employee->profile_photo_url }}" alt="" onerror="this.hidden=true; this.nextElementSibling.hidden=false;">
                                                                @endif
                                                                <span @if($employee->profile_photo_url) hidden @endif>{{ $employee->initials }}</span>
                                                            </div>
                                                            <div class="structure-employee-details">
                                                                <div>{{ $employee->name }}</div>
                                                                <small class="text-muted">{{ $employee->email }}</small>
                                                                <div class="small text-muted">{{ $department->name }}</div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle">
                                                            {{ str_replace('_', ' ', $employee->role) }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $employee->position ?? '-' }}</td>
                                                    <td>
                                                        @if($employee->status === 'ACTIVE')
                                                            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">Active</span>
                                                        @else
                                                            <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle">Inactive</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center text-muted py-3">No employees assigned.</div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    @endforeach
</div>
@endsection

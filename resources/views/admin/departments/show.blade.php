@extends('layouts.app')

@section('title', 'Department Details')
@section('breadcrumb', 'Admin / Departments / View')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="bi bi-building me-2"></i>{{ $department->name }}</h2>
        <p>Department information and employees</p>
    </div>
    <a href="{{ route('admin.departments.edit', $department) }}" class="btn btn-outline-primary">
        <i class="bi bi-pencil me-2"></i>Edit Department
    </a>
</div>

<div class="row g-4">
    <!-- Department Info -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Department Information</h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td class="text-muted" width="40%">Code:</td>
                        <td><strong>{{ $department->code }}</strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Name:</td>
                        <td>{{ $department->name }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status:</td>
                        <td>
                            @if($department->status === 'ACTIVE')
                                <span class="badge badge-active">Active</span>
                            @else
                                <span class="badge badge-inactive">Inactive</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Employees:</td>
                        <td><strong>{{ $department->users->count() }}</strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Created:</td>
                        <td>{{ $department->created_at->format('M d, Y') }}</td>
                    </tr>
                </table>

                @if($department->description)
                <div class="mt-3">
                    <small class="text-muted d-block mb-1">Description:</small>
                    <p class="small">{{ $department->description }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Employees List -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Employees ({{ $department->users->count() }})</h6>

                @if($department->users->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Employee ID</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Position</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($department->users as $user)
                            <tr>
                                <td><strong>{{ $user->employee_id }}</strong></td>
                                <td>{{ $user->name }}</td>
                                <td>
                                    <span class="badge bg-secondary">{{ str_replace('_', ' ', $user->role) }}</span>
                                </td>
                                <td>{{ $user->position ?? '-' }}</td>
                                <td>
                                    @if($user->status === 'ACTIVE')
                                        <span class="badge badge-active">Active</span>
                                    @else
                                        <span class="badge badge-inactive">Inactive</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center text-muted py-4">
                    <i class="bi bi-people display-4 d-block mb-2"></i>
                    No employees assigned to this department yet.
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
@extends('layouts.app')

@section('title', 'Employees')
@section('breadcrumb', 'Admin / Employees')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="bi bi-people me-2"></i>Employees</h2>
        <p>Manage company employees</p>
    </div>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-2"></i>Add Employee
    </a>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search name, email, ID..." 
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="department" class="form-select">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ request('department') == $dept->id ? 'selected' : '' }}>
                            {{ $dept->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="role" class="form-select">
                    <option value="">All Roles</option>
                    @foreach($roles as $role)
                        <option value="{{ $role }}" {{ request('role') == $role ? 'selected' : '' }}>
                            {{ str_replace('_', ' ', $role) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="ACTIVE" {{ request('status') == 'ACTIVE' ? 'selected' : '' }}>Active</option>
                    <option value="INACTIVE" {{ request('status') == 'INACTIVE' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-search me-2"></i>Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    <tr>
                        <td><strong>{{ $user->employee_id }}</strong></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="user-avatar" style="width: 32px; height: 32px; font-size: 0.7rem;">
                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                </div>
                                <div>
                                    <div>{{ $user->name }}</div>
                                    <small class="text-muted">{{ $user->position ?? '-' }}</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ $user->email }}</td>
                        <td>
                            <span class="badge bg-secondary">{{ $user->department->name ?? 'N/A' }}</span>
                        </td>
                        <td>
                            <small class="text-muted">{{ str_replace('_', ' ', $user->role) }}</small>
                        </td>
                        <td>
                            @if($user->status === 'ACTIVE')
                                <span class="badge badge-active">Active</span>
                            @else
                                <span class="badge badge-inactive">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <x-table-actions>
                                <li><a class="dropdown-item" href="{{ route('admin.users.show', $user) }}"><i class="bi bi-eye"></i>View</a></li>
                                <li><a class="dropdown-item" href="{{ route('admin.users.edit', $user) }}"><i class="bi bi-pencil"></i>Edit</a></li>
                                @if($user->id !== auth()->id() && $user->status === 'ACTIVE')
                                <li><form action="{{ route('admin.users.deactivate', $user) }}" method="POST" onsubmit="return confirm('Deactivate this employee? They will no longer be able to sign in, but their work history will be kept.');">@csrf @method('PATCH')<button type="submit" class="dropdown-item"><i class="bi bi-person-dash"></i>Deactivate</button></form></li>
                                @endif
                            </x-table-actions>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No employees found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $users->links() }}
        </div>
    </div>
</div>
@endsection
@extends('layouts.app')

@section('title', 'Departments')
@section('breadcrumb', 'Admin / Departments')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="bi bi-building me-2"></i>Departments</h2>
        <p>Manage company departments</p>
    </div>
    <a href="{{ route('admin.departments.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-2"></i>Add Department
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Employees</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($departments as $department)
                    <tr>
                        <td><strong>{{ $department->code }}</strong></td>
                        <td>{{ $department->name }}</td>
                        <td>{{ Str::limit($department->description, 50) }}</td>
                        <td>
                            <span class="badge bg-info">{{ $department->users_count }} employees</span>
                        </td>
                        <td>
                            @if($department->status === 'ACTIVE')
                                <span class="badge badge-active">Active</span>
                            @else
                                <span class="badge badge-inactive">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <x-table-actions>
                                <li><a class="dropdown-item" href="{{ route('admin.departments.show', $department) }}"><i class="bi bi-eye"></i>View</a></li>
                                <li><a class="dropdown-item" href="{{ route('admin.departments.edit', $department) }}"><i class="bi bi-pencil"></i>Edit</a></li>
                                <li><form action="{{ route('admin.departments.destroy', $department) }}" method="POST" onsubmit="return confirm('Delete this department?');">@csrf @method('DELETE')<button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i>Delete</button></form></li>
                            </x-table-actions>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No departments found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $departments->links() }}
        </div>
    </div>
</div>
@endsection
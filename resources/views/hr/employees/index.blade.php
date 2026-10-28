@extends('layouts.app')

@section('title', 'Employees')
@section('breadcrumb', 'HR Management / Employees')

@section('content')
<div class="page-header">
    <h2>Employees</h2>
    <p>Employee roster for HR attendance, leave, and people operations.</p>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('hr.employees.index') }}" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label for="employee-search" class="form-label small fw-semibold">Search employees</label>
                <input id="employee-search" type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Name, email, or employee ID">
            </div>
            <div class="col-md-3">
                <label for="employee-department" class="form-label small fw-semibold">Department</label>
                <select id="employee-department" name="department_id" class="form-select">
                    <option value="">All departments</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="employee-status" class="form-label small fw-semibold">Status</label>
                <select id="employee-status" name="status" class="form-select">
                    <option value="">All statuses</option>
                    <option value="ACTIVE" @selected(request('status') === 'ACTIVE')>Active</option>
                    <option value="INACTIVE" @selected(request('status') === 'INACTIVE')>Inactive</option>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-search me-1"></i>Filter</button></div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Employee</th><th>Employee ID</th><th>Email</th><th>Department</th><th>Position</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($employees as $employee)
                    <tr>
                        <td class="fw-semibold">{{ $employee->name }}</td>
                        <td>{{ $employee->employee_id }}</td>
                        <td>{{ $employee->email }}</td>
                        <td>{{ $employee->department->name ?? 'Unassigned' }}</td>
                        <td>{{ $employee->position ?? str_replace('_', ' ', $employee->role) }}</td>
                        <td><span class="badge {{ $employee->status === 'ACTIVE' ? 'badge-active' : 'badge-inactive' }}">{{ ucfirst(strtolower($employee->status)) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No employees match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $employees->links() }}</div>
</div>
@endsection

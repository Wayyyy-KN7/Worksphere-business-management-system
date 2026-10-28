@extends('layouts.app')

@section('title', 'Create Employee')
@section('breadcrumb', 'Admin / Employees / Create')

@section('content')
<div class="page-header">
    <h2><i class="bi bi-person-plus me-2"></i>Create Employee</h2>
    <p>Add a new employee to the system</p>
</div>

<div class="row">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="{{ route('admin.users.store') }}" method="POST">
                    @csrf

                    <div class="row g-3">
                        <!-- AUTO-GENERATED EMPLOYEE ID -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Employee ID</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bi bi-hash"></i>
                                </span>
                                <input type="text" class="form-control bg-light" 
                                       value="{{ $nextEmployeeId }}" readonly>
                                <span class="input-group-text bg-success text-white">
                                    <i class="bi bi-check-circle"></i> Auto-Generated
                                </span>
                            </div>
                            <small class="text-muted">This ID will be automatically assigned</small>
                        </div>

                        <!-- Name -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name') }}" placeholder="e.g., John Doe" required autofocus>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Email -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" 
                                   value="{{ old('email') }}" placeholder="employee@company.com" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Phone -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" 
                                   value="{{ old('phone') }}" placeholder="09-XXX-XXX-XXX">
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Department (triggers role dropdown update) -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Department <span class="text-danger">*</span></label>
                            <select name="department_id" id="departmentSelect" class="form-select @error('department_id') is-invalid @enderror" required>
                                <option value="">Select Department</option>
                               @foreach($departments as $dept)
    <option value="{{ $dept->id }}" 
            data-code="{{ $dept->code }}"
            data-name="{{ $dept->name }}"
            {{ old('department_id') == $dept->id ? 'selected' : '' }}>
        {{ $dept->name }}
    </option>
@endforeach
                            </select>
                            @error('department_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Role (dynamically populated) -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                            <select name="role" id="roleSelect" class="form-select @error('role') is-invalid @enderror" required>
                                <option value="">Select Department First</option>
                            </select>
                            @error('role')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted" id="roleHint">Roles will appear when you select a department</small>
                        </div>

                        <!-- Position -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Position</label>
                            <input type="text" name="position" class="form-control @error('position') is-invalid @enderror" 
                                   value="{{ old('position') }}" placeholder="e.g., Senior Developer">
                            @error('position')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Joining Date -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Joining Date <span class="text-danger">*</span></label>
                            <input type="date" name="joining_date" class="form-control @error('joining_date') is-invalid @enderror" 
                                   value="{{ old('joining_date', now()->format('Y-m-d')) }}" required>
                            @error('joining_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Password -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">
                                Use at least 8 characters.
                            </small>
                        </div>

                        <!-- Confirm Password -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" name="password_confirmation" class="form-control" required>
                        </div>

                        <!-- Status -->
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                <option value="ACTIVE" {{ old('status', 'ACTIVE') === 'ACTIVE' ? 'selected' : '' }}>Active</option>
                                <option value="INACTIVE" {{ old('status') === 'INACTIVE' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Create Employee
                        </button>
                        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>


        
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const departmentSelect = document.getElementById('departmentSelect');
    const roleSelect = document.getElementById('roleSelect');
    const roleHint = document.getElementById('roleHint');
    const oldRole = @json(old('role'));

    departmentSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const deptCode = selectedOption.getAttribute('data-code');
        const deptName = selectedOption.getAttribute('data-name');
        const rolePrefix = deptCode?.toUpperCase();

        // Clear existing options
        roleSelect.innerHTML = '';

        if (!deptCode) {
            roleSelect.innerHTML = '<option value="">Select Department First</option>';
            roleHint.textContent = 'Roles will appear when you select a department';
            return;
        }

        // Generate department-specific roles (Manager & Employee only)
        const roles = [
            { value: `${rolePrefix}_MANAGER`, label: `${deptName} Manager` },
            { value: `${rolePrefix}_EMPLOYEE`, label: `${deptName} Employee` }
        ];

        roles.forEach(role => {
            const option = document.createElement('option');
            option.value = role.value;
            option.textContent = role.label;
            roleSelect.appendChild(option);
        });

        roleSelect.value = roles.some(role => role.value === oldRole)
            ? oldRole
            : `${rolePrefix}_EMPLOYEE`;
        roleHint.innerHTML = `<i class="bi bi-check-circle text-success"></i> Roles loaded for ${deptName}`;
    });

    if (departmentSelect.value) {
        departmentSelect.dispatchEvent(new Event('change'));
    }
});
</script>
@endpush
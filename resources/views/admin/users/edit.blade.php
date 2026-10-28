@extends('layouts.app')

@section('title', 'Edit Employee')
@section('breadcrumb', 'Admin / Employees / Edit')

@section('content')
<div class="page-header">
    <h2><i class="bi bi-pencil me-2"></i>Edit Employee</h2>
    <p>Update employee information</p>
</div>

<div class="row">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="{{ route('admin.users.update', $user) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <!-- EMPLOYEE ID (Read-Only) -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Employee ID</label>
                            <input type="text" class="form-control bg-light" 
                                   value="{{ $user->employee_id }}" readonly>
                            <small class="text-muted">
                                <i class="bi bi-lock"></i> Employee ID cannot be changed
                            </small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name', $user->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" 
                                   value="{{ old('email', $user->email) }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" 
                                   value="{{ old('phone', $user->phone) }}">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <!-- Department -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Department <span class="text-danger">*</span></label>
                            <select name="department_id" id="departmentSelect" class="form-select @error('department_id') is-invalid @enderror" required>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" 
                                            data-code="{{ $dept->code }}"
                                            data-name="{{ $dept->name }}"
                                            {{ old('department_id', $user->department_id) == $dept->id ? 'selected' : '' }}>
                                        {{ $dept->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <!-- Role -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                            <select name="role" id="roleSelect" class="form-select @error('role') is-invalid @enderror" required>
                                <option value="{{ $user->role }}">{{ $user->role_label }}</option>
                            </select>
                            @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Position</label>
                            <input type="text" name="position" class="form-control @error('position') is-invalid @enderror" 
                                   value="{{ old('position', $user->position) }}">
                            @error('position')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Joining Date <span class="text-danger">*</span></label>
                            <input type="date" name="joining_date" class="form-control @error('joining_date') is-invalid @enderror" 
                                   value="{{ old('joining_date', $user->joining_date?->format('Y-m-d')) }}" required>
                            @error('joining_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-12">
                            <div class="form-check">
                                <input type="checkbox" name="change_password" id="changePassword" value="1" class="form-check-input" @checked(old('change_password') || $errors->has('password'))>
                                <label class="form-check-label fw-semibold" for="changePassword">Change password</label>
                            </div>
                            <small class="text-muted">Leave unchecked to keep the current password.</small>
                        </div>

                        <div class="col-md-6" id="passwordFields" @if(!old('change_password') && !$errors->has('password')) hidden @endif>
                            <label class="form-label fw-semibold" for="password">New Password</label>
                            <input type="password" id="password" name="password" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror" @disabled(!old('change_password') && !$errors->has('password'))>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <small class="text-muted">
                                Use at least 8 characters.
                            </small>
                        </div>

                        <div class="col-md-6" id="passwordConfirmationField" @if(!old('change_password') && !$errors->has('password')) hidden @endif>
                            <label class="form-label fw-semibold" for="password_confirmation">Confirm New Password</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="form-control" @disabled(!old('change_password') && !$errors->has('password'))>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                <option value="ACTIVE" {{ old('status', $user->status) === 'ACTIVE' ? 'selected' : '' }}>Active</option>
                                <option value="INACTIVE" {{ old('status', $user->status) === 'INACTIVE' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Update Employee
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
    const changePassword = document.getElementById('changePassword');
    const passwordFields = document.getElementById('passwordFields');
    const passwordConfirmationField = document.getElementById('passwordConfirmationField');
    const password = document.getElementById('password');
    const passwordConfirmation = document.getElementById('password_confirmation');
    const currentRole = @json(old('role', $user->role));

    function syncPasswordFields() {
        const enabled = changePassword.checked;
        passwordFields.hidden = !enabled;
        passwordConfirmationField.hidden = !enabled;
        password.disabled = !enabled;
        passwordConfirmation.disabled = !enabled;
        password.required = enabled;
        passwordConfirmation.required = enabled;

        if (!enabled) {
            password.value = '';
            passwordConfirmation.value = '';
        }
    }

    changePassword.addEventListener('change', syncPasswordFields);
    syncPasswordFields();

    departmentSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const deptCode = selectedOption.getAttribute('data-code');
        const deptName = selectedOption.getAttribute('data-name');
        const rolePrefix = deptCode?.toUpperCase();

        roleSelect.innerHTML = '';

        if (!deptCode) return;

        const roles = [
            { value: `${rolePrefix}_MANAGER`, label: `${deptName} Manager` },
            { value: `${rolePrefix}_EMPLOYEE`, label: `${deptName} Employee` },
            { value: 'EMPLOYEE', label: 'General Employee' }
        ];
        if (deptCode.toUpperCase() === 'ADMIN') {
            roles.push({ value: 'ADMIN', label: 'System Admin' });
        }

        roles.forEach(role => {
            const option = document.createElement('option');
            option.value = role.value;
            option.textContent = role.label;
            if (role.value === currentRole) {
                option.selected = true;
            }
            roleSelect.appendChild(option);
        });
    });

    // Trigger on page load
    departmentSelect.dispatchEvent(new Event('change'));
});
</script>
@endpush
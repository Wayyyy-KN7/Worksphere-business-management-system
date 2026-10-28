@extends('layouts.app')

@section('title', 'Request Leave')
@section('breadcrumb', 'Employee / Leave / Request')

@section('content')
<div class="page-header">
    <h2><i class="bi bi-calendar-plus me-2"></i>Request Leave</h2>
    <p>Submit a new leave request</p>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="{{ route('employee.leave.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Leave Type <span class="text-danger">*</span></label>
                        <select name="leave_type_id" id="leaveTypeSelect" 
                                class="form-select @error('leave_type_id') is-invalid @enderror" required>
                            <option value="">Select Leave Type</option>
                            @foreach($leaveTypes as $type)
                                @php
                                    $balance = $leaveBalances->firstWhere('leave_type_id', $type->id);
                                    $remaining = $balance ? $balance->remaining_days : $type->days_allowed;
                                @endphp
                                <option value="{{ $type->id }}" 
                                        data-days="{{ $remaining }}" 
                                        {{ old('leave_type_id') == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }} ({{ $remaining }} days available)
                                </option>
                            @endforeach
                        </select>
                        @error('leave_type_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div id="balanceInfo" class="mt-2"></div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="startDate" 
                                   class="form-control @error('start_date') is-invalid @enderror" 
                                   value="{{ old('start_date') }}" 
                                   min="{{ now()->format('Y-m-d') }}" required>
                            @error('start_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" id="endDate" 
                                   class="form-control @error('end_date') is-invalid @enderror" 
                                   value="{{ old('end_date') }}" 
                                   min="{{ now()->format('Y-m-d') }}" required>
                            @error('end_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="alert alert-info d-none" id="daysCalculation">
                            <i class="bi bi-info-circle me-2"></i>
                            Total Leave Days: <strong id="totalDays">0</strong>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" rows="4" 
                                  class="form-control @error('reason') is-invalid @enderror" 
                                  placeholder="Please provide a reason for your leave request..." required>{{ old('reason') }}</textarea>
                        @error('reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Submit Leave Request
                        </button>
                        <a href="{{ route('employee.leave.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Current Leave Balances -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Your Leave Balances</h6>
                @forelse($leaveBalances as $balance)
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <strong>{{ $balance->leaveType->name }}</strong>
                            <span class="badge bg-success">{{ $balance->remaining_days }} left</span>
                        </div>
                        <small class="text-muted">
                            Total: {{ $balance->total_days }} | 
                            Used: {{ $balance->used_days }} | 
                            Pending: {{ $balance->pending_days }}
                        </small>
                    </div>
                @empty
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        No leave balances found. Please contact HR.
                    </p>
                @endforelse
            </div>
        </div>

        <!-- Guidelines -->
        <div class="card border-0 shadow-sm mt-3 bg-light">
            <div class="card-body">
                <h6 class="fw-bold mb-2">
                    <i class="bi bi-info-circle me-2"></i>Guidelines
                </h6>
                <ul class="small mb-0 ps-3">
                    <li>Submit leave requests at least 3 days in advance</li>
                    <li>Check your leave balance before requesting</li>
                    <li>Provide a valid reason for leave</li>
                    <li>Manager approval is required</li>
                    <li>You will be notified once approved/rejected</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const leaveTypeSelect = document.getElementById('leaveTypeSelect');
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    const balanceInfo = document.getElementById('balanceInfo');
    const daysCalculation = document.getElementById('daysCalculation');
    const totalDaysSpan = document.getElementById('totalDays');

    function getRemaining() {
        const opt = leaveTypeSelect.options[leaveTypeSelect.selectedIndex];
        if (!opt || !leaveTypeSelect.value) return null;
        return parseFloat(opt.getAttribute('data-days')) || 0;
    }

    function showBalance() {
        const remaining = getRemaining();
        if (remaining === null) {
            balanceInfo.innerHTML = '';
            return;
        }
        const cls = remaining > 0 ? 'success' : 'danger';
        balanceInfo.innerHTML = `
            <div class="alert alert-${cls} small mb-0">
                <i class="bi bi-info-circle me-2"></i>
                You have <strong>${remaining} days</strong> remaining for this leave type.
            </div>
        `;
    }

    function calculateDays() {
        const start = startDateInput.value;
        const end = endDateInput.value;

        if (!start || !end) {
            daysCalculation.classList.add('d-none');
            return;
        }

        const startDate = new Date(start);
        const endDate = new Date(end);
        const diffTime = Math.abs(endDate - startDate);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;

        if (diffDays < 1) {
            daysCalculation.classList.add('d-none');
            return;
        }

        daysCalculation.classList.remove('d-none');
        totalDaysSpan.textContent = diffDays;

        const remaining = getRemaining();
        if (remaining !== null && diffDays > remaining) {
            daysCalculation.className = 'alert alert-danger';
            daysCalculation.innerHTML = `
                <i class="bi bi-exclamation-triangle me-2"></i>
                Total Leave Days: <strong>${diffDays}</strong> - 
                <span class="text-danger">Exceeds available balance (${remaining} days)</span>
            `;
        } else {
            daysCalculation.className = 'alert alert-info';
            daysCalculation.innerHTML = `
                <i class="bi bi-info-circle me-2"></i>
                Total Leave Days: <strong>${diffDays}</strong>
            `;
        }
    }

    leaveTypeSelect.addEventListener('change', function() {
        showBalance();
        calculateDays();
    });

    startDateInput.addEventListener('change', function() {
        endDateInput.min = this.value;
        calculateDays();
    });

    endDateInput.addEventListener('change', calculateDays);
});
</script>
@endpush
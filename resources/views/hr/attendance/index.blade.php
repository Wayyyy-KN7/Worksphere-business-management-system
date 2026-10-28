@extends('layouts.app')

@section('title', 'Attendance Management')
@section('breadcrumb', 'HR / Attendance')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="bi bi-calendar-check me-2"></i>Attendance Management</h2>
        <p>View and manage company-wide attendance</p>
    </div>
    @if(auth()->user()->canViewCompanyAttendance())
        <div>
            <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#manualCheckInModal">
                <i class="bi bi-person-plus me-2"></i>Manual Check-In
            </button>
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#manualCheckOutModal">
                <i class="bi bi-person-dash me-2"></i>Manual Check-Out
            </button>
        </div>
    @endif
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Total Employees</div>
                    <div class="stat-value">{{ $stats['total_employees'] }}</div>
                </div>
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-people"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Present</div>
                    <div class="stat-value text-success">{{ $stats['present'] }}</div>
                </div>
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Late</div>
                    <div class="stat-value text-warning">{{ $stats['late'] }}</div>
                </div>
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-clock"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Absent</div>
                    <div class="stat-value text-danger">{{ $stats['absent'] }}</div>
                </div>
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-x-circle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Date</label>
                <input type="date" name="date" class="form-control" value="{{ $date }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Department</label>
                <select name="department" class="form-select">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ request('department') == $dept->id ? 'selected' : '' }}>
                            {{ $dept->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="PRESENT" {{ request('status') == 'PRESENT' ? 'selected' : '' }}>Present</option>
                    <option value="LATE" {{ request('status') == 'LATE' ? 'selected' : '' }}>Late</option>
                    <option value="ABSENT" {{ request('status') == 'ABSENT' ? 'selected' : '' }}>Absent</option>
                    <option value="EARLY_DEPARTURE" {{ request('status') == 'EARLY_DEPARTURE' ? 'selected' : '' }}>Early Departure</option>
                    <option value="HALF_DAY" {{ request('status') == 'HALF_DAY' ? 'selected' : '' }}>Half-Day</option>
                    <option value="LEAVE" {{ request('status') == 'LEAVE' ? 'selected' : '' }}>On Leave</option>
                    <option value="NOT_CHECKED_IN" {{ request('status') == 'NOT_CHECKED_IN' ? 'selected' : '' }}>Not Checked In</option>
                    <option value="HOLIDAY" {{ request('status') == 'HOLIDAY' ? 'selected' : '' }}>Holiday</option>
                    <option value="NON_WORKING_DAY" {{ request('status') == 'NON_WORKING_DAY' ? 'selected' : '' }}>Non-working Day</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-2"></i>Filter
                </button>
            </div>
        </form>
    </div>
</div>

@if($holiday)
<div class="alert alert-info">
    <i class="bi bi-calendar-event me-2"></i>
    <strong>{{ $holiday->name }}</strong> - This date is a holiday
</div>
@endif

<!-- Attendance Table Section -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <!-- Export & Action Toolbar -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0">Attendance Records - {{ Carbon\Carbon::parse($date)->format('M d, Y') }}</h6>
          
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Hours</th>
                        <th>Status</th>
                        <th>Late</th>
                        <th>Type / Recorded By</th>
                        @if(auth()->user()->canViewCompanyAttendance())<th>Actions</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($attendances as $att)
                    <tr>
                        <td>
                            <div>
                                <strong>{{ $att->user->name }}</strong>
                                <small class="text-muted d-block">{{ $att->user->employee_id }}</small>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-secondary">{{ $att->user->department->name ?? 'N/A' }}</span>
                        </td>
                        <td>
                            @if($att->check_in)
                                {{ Carbon\Carbon::parse($att->check_in)->format('h:i A') }}
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if($att->check_out)
                                {{ Carbon\Carbon::parse($att->check_out)->format('h:i A') }}
                            @else
                                <span class="text-muted">Not yet</span>
                            @endif
                        </td>
                        <td>{{ number_format($att->working_hours, 1) }}h</td>
                        <td>
                            @if($att->status === 'PRESENT')
                                <span class="badge badge-active">Present</span>
                            @elseif($att->status === 'LATE')
                                <span class="badge badge-pending">Late</span>
                            @elseif(in_array($att->status, ['EARLY_DEPARTURE', 'HALF_DAY'], true))
                                <span class="badge bg-info text-dark">{{ $att->status === 'HALF_DAY' ? 'Half-Day' : 'Early Departure' }}</span>
                            @elseif($att->status === 'LEAVE')
                                <span class="badge bg-info text-dark">On Leave</span>
                            @elseif($att->status === 'ABSENT')
                                <span class="badge bg-danger">Absent</span>
                            @elseif($att->status === 'NOT_CHECKED_IN')
                                <span class="badge bg-secondary">Not Checked In</span>
                            @elseif($att->status === 'HOLIDAY')
                                <span class="badge bg-info text-dark">Holiday</span>
                            @elseif($att->status === 'NON_WORKING_DAY')
                                <span class="badge bg-secondary">Non-working Day</span>
                            @else
                                <span class="badge bg-secondary">{{ $att->status }}</span>
                            @endif
                        </td>
                        <td>
                            @if($att->late_minutes > 0)
                                <span class="text-danger">{{ $att->late_minutes }} min</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if(!$att->exists)
                                <span class="text-muted">-</span>
                            @elseif($att->is_manual)
                                <span class="badge bg-warning" title="Reason: {{ $att->manual_reason }}">Manual</span>
                                <small class="d-block text-muted" style="font-size: 11px;">
                                    In by: {{ $att->checkedInBy->name ?? 'System/HR' }}
                                    @if($att->checked_out_by)
                                        <br>Out by: {{ $att->checkedOutBy->name ?? 'System/HR' }}
                                    @endif
                                </small>
                            @else
                                <span class="badge bg-info">Self</span>
                            @endif
                        </td>
                        @if(auth()->user()->canViewCompanyAttendance())
                        <td>
                            @if($att->exists)
                                <x-table-actions>
                                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editAttendanceModal{{ $att->id }}"><i class="bi bi-pencil"></i>Edit record</button></li>
                                    <li><form action="{{ route('hr.attendance.destroy', $att->id) }}" method="POST" onsubmit="const reason = window.prompt('Why should this attendance record be deleted?'); if (!reason || !reason.trim()) return false; this.querySelector('[name=reason]').value = reason.trim(); return window.confirm('Delete this attendance record?');">@csrf @method('DELETE')<input type="hidden" name="reason"><button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i>Delete record</button></form></li>
                                </x-table-actions>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No employees match these filters</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $attendances->appends(request()->query())->links() }}
        </div>
    </div>
</div>

@if(auth()->user()->canViewCompanyAttendance())
<!-- Manual Check-In Modal -->
<div class="modal fade" id="manualCheckInModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('hr.attendance.manual-check-in') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-plus me-2"></i>Manual Check-In
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Employee <span class="text-danger">*</span></label>
                        <select name="user_id" class="form-select" required>
                            <option value="">Select Employee</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}">
                                    {{ $emp->name }} ({{ $emp->employee_id }}) - {{ $emp->department->name ?? 'N/A' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                        <input type="date" name="attendance_date" class="form-control" value="{{ $date }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Check-In Time <span class="text-danger">*</span></label>
                        <input type="time" name="check_in_time" class="form-control" value="{{ substr((string) config('company.work_start', '09:00'), 0, 5) }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="e.g., Attendance machine problem" required></textarea>
                        <small class="text-muted">Explain why manual check-in is needed</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>Record Check-In
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Manual Check-Out Modal -->
<div class="modal fade" id="manualCheckOutModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('hr.attendance.manual-check-out') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-dash me-2"></i>Manual Check-Out
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Employee Record <span class="text-danger">*</span></label>
                        <select name="attendance_id" class="form-select" required>
                            <option value="">Select Employee (Checked In)</option>
                            @foreach($attendances as $att)
                                @if($att->exists && $att->check_in && !$att->check_out)
                                    <option value="{{ $att->id }}">
                                        {{ $att->user->name }} — Dept: {{ $att->user->department->name ?? 'N/A' }} (Checked in at {{ Carbon\Carbon::parse($att->check_in)->format('h:i A') }})
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        <small class="text-muted">Only shows employees who haven't checked out for this date.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Check-Out Time <span class="text-danger">*</span></label>
                        <input type="time" name="check_out_time" class="form-control" value="{{ substr((string) config('company.work_end', '17:00'), 0, 5) }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="e.g., Employee forgot to check out" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>Record Check-Out
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Individual Edit Modals Loop -->
@foreach($attendances->where('exists', true) as $att)
<div class="modal fade" id="editAttendanceModal{{ $att->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('hr.attendance.update', $att->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil-square me-2"></i>Edit Attendance: {{ $att->user->name }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Check-In Time</label>
                        <input type="time" name="check_in" class="form-control" value="{{ $att->check_in ? Carbon\Carbon::parse($att->check_in)->format('H:i') : '' }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Check-Out Time</label>
                        <input type="time" name="check_out" class="form-control" value="{{ $att->check_out ? Carbon\Carbon::parse($att->check_out)->format('H:i') : '' }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            <option value="PRESENT" {{ $att->status === 'PRESENT' ? 'selected' : '' }}>Present</option>
                            <option value="LATE" {{ $att->status === 'LATE' ? 'selected' : '' }}>Late</option>
                            <option value="EARLY_DEPARTURE" {{ $att->status === 'EARLY_DEPARTURE' ? 'selected' : '' }}>Early Departure</option>
                            <option value="HALF_DAY" {{ $att->status === 'HALF_DAY' ? 'selected' : '' }}>Half-Day</option>
                            <option value="ABSENT" {{ $att->status === 'ABSENT' ? 'selected' : '' }}>Absent</option>
                            <option value="LEAVE" {{ $att->status === 'LEAVE' ? 'selected' : '' }}>On Leave</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason / Note <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Reason for modification..." required>{{ $att->manual_reason }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endif
@endsection
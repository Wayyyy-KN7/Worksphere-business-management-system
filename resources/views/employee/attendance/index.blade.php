@extends('layouts.app')

@section('title', 'My Attendance')
@section('breadcrumb', 'Employee / Attendance')

@section('content')
<div class="page-header">
    <h2><i class="bi bi-calendar-check me-2"></i>My Attendance</h2>
    <p>Check in/out and view your attendance records</p>
</div>

<!-- Today's Attendance Card -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3">
                    <i class="bi bi-calendar-event me-2"></i>Today: {{ now()->format('l, M d, Y') }}
                </h5>

                @if($isHoliday && !$todayAttendance)
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Today is a holiday. No attendance required.
                    </div>
                @elseif(!$isWorkingDay && !$todayAttendance)
                    <div class="alert alert-info">
                        <i class="bi bi-calendar-week me-2"></i>
                        Today is not a configured working day. No check-in is required.
                    </div>
                @elseif(!$todayAttendance)
                    <!-- Not Checked In Yet -->
                    <div class="text-center py-4">
                        <div class="display-1 text-muted mb-3">
                            <i class="bi bi-clock"></i>
                        </div>
                        <h4 class="mb-3">You haven't checked in yet</h4>
                        <p class="text-muted mb-4">Click the button below to check in for today</p>
                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <form action="{{ route('employee.attendance.check-in') }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-success btn-lg px-5">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Check In Now
                                </button>
                            </form>
                            <button type="button" class="btn btn-outline-warning btn-lg px-4" data-bs-toggle="modal" data-bs-target="#lateArrivalModal">
                                <i class="bi bi-clock-history me-2"></i>Report Late Arrival
                            </button>
                        </div>
                        <small class="text-muted d-block mt-3">
                            Current time: <strong>{{ now()->format('h:i A') }}</strong>
                        </small>
                    </div>
                @elseif(!$todayAttendance->check_out)
                    <!-- Checked In, Not Checked Out -->
                    @php
                        $attDate = Carbon\Carbon::parse($todayAttendance->attendance_date)->format('Y-m-d');
                        $fullCheckIn = Carbon\Carbon::parse($attDate . ' ' . $todayAttendance->check_in);
                    @endphp
                    <div class="bg-light p-4 rounded mb-3">
                        <div class="row text-center">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <small class="text-muted d-block">Check In</small>
                                <h4 class="mb-0 text-success">
                                    {{ $fullCheckIn->format('h:i A') }}
                                </h4>
                            </div>
                            <div class="col-md-4 mb-3 mb-md-0">
                                <small class="text-muted d-block">Status</small>
                                @if($todayAttendance->status === 'LATE')
                                    <span class="badge bg-warning text-dark fs-6">
                                        Late ({{ (int) abs($todayAttendance->late_minutes ?? 0) }} min)
                                    </span>
                                @elseif(in_array($todayAttendance->status, ['EARLY_DEPARTURE', 'HALF_DAY'], true))
                                    <span class="badge bg-info text-dark fs-6">{{ $todayAttendance->status === 'HALF_DAY' ? 'Half-Day' : 'Early Departure' }}</span>
                                @else
                                    <span class="badge bg-success fs-6">On Time</span>
                                @endif
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted d-block">Working Time</small>
                                <h4 class="mb-0 text-primary" id="workingTime">
                                    {{ $fullCheckIn->diffForHumans(null, true) }}
                                </h4>
                            </div>
                        </div>
                    </div>

                    <div class="text-center py-3">
                        <p class="text-muted mb-3">Ready to finish your work day?</p>
                        <form action="{{ route('employee.attendance.check-out') }}" method="POST">
                            @csrf
                            <div class="row g-2 justify-content-center mb-3">
                                <div class="col-md-5">
                                    <label class="form-label visually-hidden" for="departure_type">Departure type</label>
                                    <select id="departure_type" name="departure_type" class="form-select">
                                        <option value="NORMAL" {{ old('departure_type', 'NORMAL') === 'NORMAL' ? 'selected' : '' }}>Normal check-out</option>
                                        <option value="EARLY_DEPARTURE" {{ old('departure_type') === 'EARLY_DEPARTURE' ? 'selected' : '' }}>Early departure</option>
                                        <option value="HALF_DAY" {{ old('departure_type') === 'HALF_DAY' ? 'selected' : '' }}>Half-day</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label visually-hidden" for="departure_reason">Reason</label>
                                    <input id="departure_reason" type="text" name="reason" value="{{ old('reason') }}" class="form-control" maxlength="500" placeholder="Reason for early departure">
                                </div>
                            </div>
                            @error('departure_type')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                            @error('reason')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                            <button type="submit" class="btn btn-primary btn-lg px-5">
                                <i class="bi bi-box-arrow-right me-2"></i>Check Out Now
                            </button>
                        </form>
                        <small class="text-muted d-block mt-3">
                            Current time: <strong>{{ now()->format('h:i A') }}</strong>
                        </small>
                    </div>
                @else
                    <!-- Already Checked Out -->
                    <div class="bg-light p-4 rounded">
                        <div class="row text-center">
                            <div class="col-md-3 mb-3 mb-md-0">
                                <small class="text-muted d-block">Check In</small>
                                <h5 class="mb-0 text-success">
                                    {{ Carbon\Carbon::parse($todayAttendance->check_in)->format('h:i A') }}
                                </h5>
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <small class="text-muted d-block">Check Out</small>
                                <h5 class="mb-0 text-danger">
                                    {{ Carbon\Carbon::parse($todayAttendance->check_out)->format('h:i A') }}
                                </h5>
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <small class="text-muted d-block">Working Hours</small>
                                <h5 class="mb-0 text-primary">
                                    {{ number_format($todayAttendance->working_hours, 1) }}h
                                </h5>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block">Status</small>
                                @if($todayAttendance->status === 'LATE')
                                    <span class="badge bg-warning text-dark">Late</span>
                                @elseif($todayAttendance->status === 'HALF_DAY')
                                    <span class="badge bg-info text-dark">Half-Day</span>
                                @elseif($todayAttendance->status === 'EARLY_DEPARTURE')
                                    <span class="badge bg-info text-dark">Early Departure</span>
                                @else
                                    <span class="badge bg-success">On Time</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-success mt-3 mb-0">
                        <i class="bi bi-check-circle me-2"></i>
                        You have completed your attendance for today. See you tomorrow!
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Monthly Statistics -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h6 class="fw-bold mb-3">This Month Statistics</h6>
                @php
                    $daysInMonth = now()->daysInMonth;
                @endphp
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Present Days</span>
                        <strong class="text-success">{{ $stats['present'] }}</strong>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: {{ min(100, ($stats['present'] / $daysInMonth) * 100) }}%"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Late Days</span>
                        <strong class="text-warning">{{ $stats['late'] }}</strong>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-warning" style="width: {{ min(100, ($stats['late'] / $daysInMonth) * 100) }}%"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Absent Days</span>
                        <strong class="text-danger">{{ $stats['absent'] }}</strong>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-danger" style="width: {{ min(100, ($stats['absent'] / $daysInMonth) * 100) }}%"></div>
                    </div>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">Total Working Hours</span>
                    <strong class="text-primary">{{ number_format($stats['total_hours'], 1) }}h</strong>
                </div>
            </div>
        </div>

        <a href="{{ route('employee.attendance.history') }}" class="btn btn-outline-primary w-100">
            <i class="bi bi-clock-history me-2"></i>View Full History
        </a>
    </div>
</div>

<!-- Recent Attendance -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h6 class="fw-bold mb-3">Recent Attendance (This Month)</h6>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Hours</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($monthlyAttendances->take(10) as $att)
                    <tr>
                        <td>{{ Carbon\Carbon::parse($att->attendance_date)->format('M d, Y') }}</td>
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
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>{{ number_format($att->working_hours, 1) }}h</td>
                        <td>
                            @if($att->status === 'PRESENT')
                                <span class="badge bg-success">On Time</span>
                            @elseif($att->status === 'LATE')
                                <span class="badge bg-warning text-dark">Late</span>
                            @elseif($att->status === 'HALF_DAY')
                                <span class="badge bg-info text-dark">Half-Day</span>
                            @elseif($att->status === 'EARLY_DEPARTURE')
                                <span class="badge bg-info text-dark">Early Departure</span>
                            @elseif($att->status === 'ABSENT')
                                <span class="badge bg-danger">Absent</span>
                            @else
                                <span class="badge bg-secondary">{{ $att->status }}</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No attendance records yet</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="lateArrivalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('employee.attendance.report-late') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Report Late Arrival</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Expected Check-in Time</label>
                        <input type="time" name="expected_check_in" class="form-control" value="{{ now()->format('H:i') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason</label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="Traffic delay, vehicle problem, transport issue, etc."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes (optional)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional details"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
@if($todayAttendance && !$todayAttendance->check_out)
function updateTimer() {
    @php
        $jsDate = Carbon\Carbon::parse($todayAttendance->attendance_date)->format('Y-m-d');
        $jsIsoTime = Carbon\Carbon::parse($jsDate . ' ' . $todayAttendance->check_in)->toIso8601String();
    @endphp
    const checkInTime = new Date('{{ $jsIsoTime }}');
    const now = new Date();
    const diffMs = now - checkInTime;
    
    if (diffMs > 0) {
        const hours = Math.floor(diffMs / 3600000);
        const minutes = Math.floor((diffMs % 3600000) / 60000);
        document.getElementById('workingTime').textContent = hours + 'h ' + minutes + 'm';
    }
}
updateTimer();
setInterval(updateTimer, 60000);
@endif
</script>
@endpush
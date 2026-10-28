@extends('layouts.app')

@section('title', 'Attendance History')
@section('breadcrumb', ($showEmployee ?? false) ? 'Attendance / Team History' : 'Employee / Attendance History')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
    <div>
        <h2><i class="bi bi-clock-history me-2"></i>{{ ($showEmployee ?? false) ? 'Attendance History' : 'My Attendance History' }}</h2>
        <p>{{ ($showEmployee ?? false) ? (isset($department) ? 'Department: ' . $department->name : 'Search and review employee attendance records') : 'Search and review your attendance records' }}</p>
    </div>
    @if(auth()->user()->isHR())
        <a href="{{ route('hr.attendance.index') }}" class="btn btn-outline-primary">Daily attendance</a>
    @elseif(auth()->user()->isManager() && !auth()->user()->isAdmin())
        <a href="{{ route('employee.attendance.index') }}" class="btn btn-outline-primary">My attendance</a>
    @endif
</div>

<div class="row g-3 mb-4">
    @foreach([
        ['label' => 'Present days', 'value' => $summary['present'], 'tone' => 'success', 'icon' => 'bi-check-circle'],
        ['label' => 'Absent days', 'value' => $summary['absent'], 'tone' => 'danger', 'icon' => 'bi-x-circle'],
        ['label' => 'Late arrivals', 'value' => $summary['late'], 'tone' => 'warning', 'icon' => 'bi-clock'],
        ['label' => 'Leave days', 'value' => $summary['leave'], 'tone' => 'info', 'icon' => 'bi-calendar2-check'],
    ] as $item)
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">{{ $item['label'] }}</div>
                        <div class="stat-value">{{ $item['value'] }}</div>
                    </div>
                    <div class="stat-icon bg-{{ $item['tone'] }} bg-opacity-10 text-{{ $item['tone'] }}">
                        <i class="bi {{ $item['icon'] }}"></i>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            @if($showEmployee ?? false)
                <div class="col-md-4">
                    <label class="form-label" for="attendanceSearch">Employee</label>
                    <input id="attendanceSearch" type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Name or employee ID">
                </div>
                @if(isset($departments))
                    <div class="col-md-3">
                        <label class="form-label" for="attendanceDepartment">Department</label>
                        <select id="attendanceDepartment" name="department" class="form-select">
                            <option value="">All departments</option>
                            @foreach($departments as $itemDepartment)
                                <option value="{{ $itemDepartment->id }}" @selected(request('department') == $itemDepartment->id)>{{ $itemDepartment->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            @else
                <div class="col-md-4">
                    <label class="form-label" for="attendanceSearch">Search notes or reason</label>
                    <input id="attendanceSearch" type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Search your notes or reason">
                </div>
            @endif
            <div class="col-md-2">
                <label class="form-label" for="attendanceFrom">From</label>
                <input id="attendanceFrom" type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="attendanceTo">To</label>
                <input id="attendanceTo" type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="attendanceStatus">Status</label>
                <select id="attendanceStatus" name="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach(['PRESENT' => 'Present', 'LATE' => 'Late', 'ABSENT' => 'Absent', 'LEAVE' => 'Leave', 'EARLY_DEPARTURE' => 'Early departure', 'HALF_DAY' => 'Half-day', 'HOLIDAY' => 'Holiday'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="{{ request()->url() }}" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        @if($showEmployee ?? false)
                            <th>Employee</th>
                            <th>Department</th>
                        @endif
                        <th>Date</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Hours</th>
                        <th>Status</th>
                        <th>Late</th>
                        <th>Reason / Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attendances as $att)
                        <tr>
                            @if($showEmployee ?? false)
                                <td>
                                    <strong>{{ $att->user?->name ?? 'Unknown user' }}</strong>
                                    <small class="text-muted d-block">{{ $att->user?->employee_id ?? 'No employee ID' }}@if($att->user?->status === 'INACTIVE') · Inactive @endif</small>
                                </td>
                                <td>{{ $att->user?->department?->name ?? 'No department' }}</td>
                            @endif
                            <td>
                                <strong>{{ $att->attendance_date->format('M d, Y') }}</strong>
                                <small class="text-muted d-block">{{ $att->attendance_date->format('l') }}</small>
                            </td>
                            <td>
                                @if($att->check_in)
                                    {{ \Carbon\Carbon::parse($att->check_in)->format('h:i A') }}
                                    @if($att->is_manual)<span class="badge bg-warning">Manual</span>@endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>{{ $att->check_out ? \Carbon\Carbon::parse($att->check_out)->format('h:i A') : '—' }}</td>
                            <td>{{ $att->working_hours > 0 ? number_format($att->working_hours, 1) . 'h' : '—' }}</td>
                            <td>
                                @php
                                    $statusClass = match($att->status) {
                                        'PRESENT' => 'badge-active',
                                        'LATE' => 'badge-pending',
                                        'ABSENT' => 'badge-rejected',
                                        'LEAVE', 'HALF_DAY', 'EARLY_DEPARTURE', 'HOLIDAY' => 'bg-info',
                                        default => 'bg-secondary',
                                    };
                                    $statusLabel = match($att->status) {
                                        'EARLY_DEPARTURE' => 'Early departure',
                                        'HALF_DAY' => 'Half-day',
                                        default => ucfirst(strtolower($att->status)),
                                    };
                                @endphp
                                <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                            </td>
                            <td>{{ $att->late_minutes > 0 ? $att->late_minutes . ' min' : '—' }}</td>
                            <td>
                                @if($att->manual_reason || $att->notes)
                                    <span title="{{ trim(($att->manual_reason ? $att->manual_reason . ' ' : '') . ($att->notes ?? '')) }}">
                                        {{ \Illuminate\Support\Str::limit(trim(($att->manual_reason ? $att->manual_reason . ' ' : '') . ($att->notes ?? '')), 60) }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ ($showEmployee ?? false) ? 9 : 7 }}" class="text-center text-muted py-4">No attendance records match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $attendances->links() }}</div>
    </div>
</div>
@endsection

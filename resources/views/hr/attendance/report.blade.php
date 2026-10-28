@extends('layouts.app')

@section('title', 'Attendance Report')
@section('breadcrumb', 'HR / Attendance Report')

@section('content')
<div class="page-header">
    <h2><i class="bi bi-file-earmark-bar-graph me-2"></i>Attendance Report</h2>
    <p>Attendance summary based on configured workdays, approved leave, and recorded attendance.</p>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold" for="report-period">Report period</label>
                <select id="report-period" name="period" class="form-select">
                    <option value="daily" @selected($period === 'daily')>Daily</option>
                    <option value="monthly" @selected($period === 'monthly')>Monthly</option>
                    <option value="yearly" @selected($period === 'yearly')>Yearly</option>
                </select>
            </div>
            <div class="col-md-3" data-report-period="daily" @if($period !== 'daily') hidden @endif>
                <label class="form-label small fw-semibold" for="report-date">Date</label>
                <input id="report-date" type="date" name="date" class="form-control" value="{{ $date }}">
            </div>
            <div class="col-md-3" data-report-period="monthly" @if($period !== 'monthly') hidden @endif>
                <label class="form-label small fw-semibold" for="report-month">Month</label>
                <input id="report-month" type="month" name="month" class="form-control" value="{{ $month }}">
            </div>
            <div class="col-md-3" data-report-period="yearly" @if($period !== 'yearly') hidden @endif>
                <label class="form-label small fw-semibold" for="report-year">Year</label>
                <input id="report-year" type="number" name="year" class="form-control" min="2000" max="2100" value="{{ $year }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Department</label>
                <select name="department" class="form-select">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ $departmentId == $dept->id ? 'selected' : '' }}>
                            {{ $dept->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-2"></i>Generate Report
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Report Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h6 class="fw-bold mb-3">{{ ucfirst($period) }} report for {{ $periodLabel }}</h6>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Present / Partial Day</th>
                        <th>Late</th>
                        <th>Leave</th>
                        <th>Absent</th>
                        <th>Total Hours</th>
                        <th>Avg Hours/Day</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportRows as $row)
                    @php($user = $row['user'])
                    <tr>
                        <td>
                            <strong>{{ $user->name }}</strong>
                            <small class="text-muted d-block">{{ $user->employee_id }}</small>
                        </td>
                        <td><span class="badge bg-secondary">{{ $user->department->name ?? 'N/A' }}</span></td>
                        <td><strong class="text-success">{{ $row['present'] }}</strong></td>
                        <td>
                            @if($row['late'] > 0)
                                <strong class="text-warning">{{ $row['late'] }}</strong>
                            @else
                                <span class="text-muted">0</span>
                            @endif
                        </td>
                        <td>{{ $row['leave'] }}</td>
                        <td>
                            @if($row['absent'] > 0)
                                <strong class="text-danger">{{ $row['absent'] }}</strong>
                            @else
                                <span class="text-muted">0</span>
                            @endif
                        </td>
                        <td>{{ number_format($row['total_hours'], 1) }}h</td>
                        <td>{{ number_format($row['average_hours'], 1) }}h</td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No active employees match this report.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const periodSelect = document.getElementById('report-period');
        const periodFields = document.querySelectorAll('[data-report-period]');

        const updatePeriodFields = () => {
            periodFields.forEach((field) => {
                const active = field.dataset.reportPeriod === periodSelect.value;
                field.hidden = !active;
                field.querySelector('input').disabled = !active;
            });
        };

        periodSelect.addEventListener('change', updatePeriodFields);
        updatePeriodFields();
    });
</script>
@endsection
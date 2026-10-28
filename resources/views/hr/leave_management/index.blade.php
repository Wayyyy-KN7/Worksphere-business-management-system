@extends('layouts.app')

@section('title', 'Leave Management')
@section('breadcrumb', 'HR Management / Leave Management')

@section('content')
<div class="page-header">
    <h2><i class="bi bi-calendar2-week me-2"></i>Leave Management</h2>
    <p>Company-wide leave request overview. Approvals are handled by assigned department reviewers.</p>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('hr.leaves.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="leave-status" class="form-label small fw-semibold">Status</label>
                <select id="leave-status" name="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach(['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(strtolower($status)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button></div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Request #</th><th>Employee</th><th>Department</th><th>Leave Type</th><th>Period</th><th>Days</th><th>Status</th><th>Submitted</th></tr></thead>
            <tbody>
                @forelse($leaveRequests as $leaveRequest)
                    <tr>
                        <td class="fw-semibold">{{ $leaveRequest->request_number }}</td>
                        <td>{{ $leaveRequest->user->name ?? 'Employee unavailable' }}</td>
                        <td>{{ $leaveRequest->user->department->name ?? 'Unassigned' }}</td>
                        <td>{{ $leaveRequest->leaveType->name ?? 'N/A' }}</td>
                        <td>{{ $leaveRequest->start_date->format('M d, Y') }} – {{ $leaveRequest->end_date->format('M d, Y') }}</td>
                        <td>{{ $leaveRequest->total_days }}</td>
                        <td>
                            @php($statusTone = ['PENDING' => 'warning', 'APPROVED' => 'success', 'REJECTED' => 'danger', 'CANCELLED' => 'secondary'][$leaveRequest->status] ?? 'secondary')
                            <span class="badge bg-{{ $statusTone }} {{ $leaveRequest->status === 'PENDING' ? 'text-dark' : '' }}">{{ ucfirst(strtolower($leaveRequest->status)) }}</span>
                        </td>
                        <td><small class="text-muted">{{ $leaveRequest->created_at->diffForHumans() }}</small></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No leave requests found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $leaveRequests->links() }}</div>
</div>
@endsection
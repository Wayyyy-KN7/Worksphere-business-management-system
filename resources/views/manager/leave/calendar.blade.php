@extends('layouts.app')

@section('title', 'Leave Calendar')
@section('breadcrumb', 'Manager / Leave Calendar')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="bi bi-calendar3 me-2"></i>Leave Calendar</h2>
        <p class="text-muted mb-0">View pending requests and approved leave for your team</p>
    </div>
    @if(Route::has('manager.leave.index'))
    <a href="{{ route('manager.leave.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Approvals
    </a>
    @endif
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0">
                <i class="bi bi-calendar-event me-2 text-primary"></i>Team Leave — {{ now()->format('F Y') }}
            </h6>
        </div>

        @if(isset($leaveRequests) && $leaveRequests->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Employee</th>
                        <th>Leave Type</th>
                        <th>Request reason</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Days</th>
                        <th class="text-end pe-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($leaveRequests as $leave)
                    <tr>
                        <td class="ps-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" 
                                     style="width: 32px; height: 32px; font-size: 0.75rem;">
                                    {{ strtoupper(substr($leave->user->name ?? 'E', 0, 2)) }}
                                </div>
                                <div>
                                    <strong class="d-block">{{ $leave->user->name ?? 'Unknown Employee' }}</strong>
                                    <small class="text-muted">{{ $leave->user->employee_id ?? 'N/A' }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-info text-dark">{{ $leave->leaveType->name ?? 'N/A' }}</span>
                        </td>
                        <td>{{ $leave->reason ? \Illuminate\Support\Str::limit($leave->reason, 60) : '—' }}</td>
                        <td>{{ \Carbon\Carbon::parse($leave->start_date)->format('M d, Y') }}</td>
                        <td>{{ \Carbon\Carbon::parse($leave->end_date)->format('M d, Y') }}</td>
                        <td><strong class="text-primary">{{ $leave->total_days }}</strong></td>
                        <td class="text-end pe-3">
                            @if($leave->status === 'PENDING')
                                <span class="badge bg-warning text-dark">Pending approval</span>
                            @else
                                <span class="badge bg-success">Approved</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if(method_exists($leaveRequests, 'links'))
        <div class="mt-3">
            {{ $leaveRequests->links() }}
        </div>
        @endif

        @else
        <div class="text-center text-muted py-5">
            <i class="bi bi-calendar3 display-4 d-block mb-3 text-secondary"></i>
            <p class="mb-0 fs-6">No pending or approved leave requests for this period.</p>
        </div>
        @endif
    </div>
</div>
@endsection
@extends('layouts.app')

@section('title', 'Leave Approvals')
@section('breadcrumb', 'Manager / Leave Approvals')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="bi bi-calendar-check me-2"></i>Leave Approvals</h2>
        <p class="text-muted mb-0">Review and approve leave requests from your team</p>
    </div>
    @if(Route::has('manager.leave.calendar'))
    <a href="{{ route('manager.leave.calendar') }}" class="btn btn-outline-primary">
        <i class="bi bi-calendar3 me-2"></i>Calendar View
    </a>
    @endif
</div>

<!-- Pending Count Alert -->
@if(isset($pendingCount) && $pendingCount > 0)
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    You have <strong>{{ $pendingCount }}</strong> pending leave request(s) awaiting your approval.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Filter by Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="PENDING" {{ request('status') === 'PENDING' ? 'selected' : '' }}>Pending</option>
                    <option value="APPROVED" {{ request('status') === 'APPROVED' ? 'selected' : '' }}>Approved</option>
                    <option value="REJECTED" {{ request('status') === 'REJECTED' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="{{ url()->current() }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Leave Requests Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        @if($leaveRequests && $leaveRequests->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Request #</th>
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>Period</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($leaveRequests as $request)
                    @php
                        $currentUser = auth()->user();
                        $isAdmin = method_exists($currentUser, 'isAdmin') ? $currentUser->isAdmin() : false;
                        
                        // Robust check for HR or HR_MANAGER roles
                        $userRole = strtoupper($currentUser->role ?? '');
                        $isHr = str_contains($userRole, 'HR') || (method_exists($currentUser, 'isHr') && $currentUser->isHr());
                        
                        $isSameDepartment = ($request->user->department_id === $currentUser->department_id);
                        $isRequester = $request->user_id === $currentUser->id;
                        $hasPendingAssignment = $request->approvals->contains(fn ($approval) =>
                            $approval->reviewer_id === $currentUser->id && $approval->status === 'PENDING'
                        );

                        // Determine if actions are permitted for this specific row
                        if ($isRequester) {
                            $canTakeAction = false;
                        } elseif ($isAdmin) {
                            $canTakeAction = true;
                        } else {
                            $canTakeAction = $isSameDepartment && $hasPendingAssignment;
                        }
                    @endphp

                    <tr class="{{ $request->status === 'PENDING' ? 'table-warning' : '' }}">
                        <td class="ps-3"><strong>{{ $request->request_number }}</strong></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" 
                                     style="width: 32px; height: 32px; font-size: 0.75rem;">
                                    {{ strtoupper(substr($request->user->name ?? 'E', 0, 2)) }}
                                </div>
                                <div>
                                    <div class="fw-semibold">{{ $request->user->name ?? 'Unknown Employee' }}</div>
                                    <small class="text-muted">{{ $request->user->employee_id ?? 'N/A' }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-info text-dark">{{ $request->leaveType->name ?? 'N/A' }}</span>
                        </td>
                        <td>
                            <div>
                                <small class="text-muted">From:</small> {{ \Carbon\Carbon::parse($request->start_date)->format('M d, Y') }}
                            </div>
                            <div>
                                <small class="text-muted">To:</small> {{ \Carbon\Carbon::parse($request->end_date)->format('M d, Y') }}
                            </div>
                        </td>
                        <td><strong class="text-primary">{{ $request->total_days }}</strong></td>
                        <td>
                            @if($request->status === 'PENDING')
                                <span class="badge bg-warning text-dark">Pending</span>
                            @elseif($request->status === 'APPROVED')
                                <span class="badge bg-success">Approved</span>
                            @elseif($request->status === 'REJECTED')
                                <span class="badge bg-danger">Rejected</span>
                            @else
                                <span class="badge bg-secondary">{{ $request->status }}</span>
                            @endif
                        </td>
                        <td>
                            <small class="text-muted">{{ \Carbon\Carbon::parse($request->created_at)->diffForHumans() }}</small>
                        </td>
                        <td class="text-end pe-3">
                            <x-table-actions>
                                <li><a class="dropdown-item" href="{{ route('manager.leave.show', $request->id) }}"><i class="bi bi-eye"></i>View details</a></li>
                                @if($canTakeAction && $request->status === 'PENDING')
                                <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#approveModal{{ $request->id }}"><i class="bi bi-check-circle"></i>Approve</button></li>
                                <li><button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $request->id }}"><i class="bi bi-x-circle"></i>Reject</button></li>
                                @endif
                            </x-table-actions>

                            {{-- Modals are also wrapped with $canTakeAction --}}
                            @if($canTakeAction && $request->status === 'PENDING')
                            <!-- Approve Modal -->
                            <div class="modal fade text-start" id="approveModal{{ $request->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form action="{{ route('manager.leave.approve', $request->id) }}" method="POST">
                                            @csrf
                                            <div class="modal-header bg-success text-white">
                                                <h5 class="modal-title">
                                                    <i class="bi bi-check-circle me-2"></i>Approve Leave Request
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to approve this leave request?</p>
                                                
                                                <div class="bg-light p-3 rounded mb-3 border">
                                                    <div><strong>Employee:</strong> {{ $request->user->name ?? 'N/A' }}</div>
                                                    <div><strong>Leave Type:</strong> {{ $request->leaveType->name ?? 'N/A' }}</div>
                                                    <div><strong>Period:</strong> {{ \Carbon\Carbon::parse($request->start_date)->format('M d') }} - {{ \Carbon\Carbon::parse($request->end_date)->format('M d, Y') }}</div>
                                                    <div><strong>Days:</strong> {{ $request->total_days }}</div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">Comment (Optional)</label>
                                                    <textarea name="comment" class="form-control" rows="2" 
                                                              placeholder="Add an optional comment for the employee..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-success">
                                                    <i class="bi bi-check-circle me-1"></i>Approve Leave
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Reject Modal -->
                            <div class="modal fade text-start" id="rejectModal{{ $request->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form action="{{ route('manager.leave.reject', $request->id) }}" method="POST">
                                            @csrf
                                            <div class="modal-header bg-danger text-white">
                                                <h5 class="modal-title">
                                                    <i class="bi bi-x-circle me-2"></i>Reject Leave Request
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Please provide a reason for rejecting this leave request.</p>
                                                
                                                <div class="bg-light p-3 rounded mb-3 border">
                                                    <div><strong>Employee:</strong> {{ $request->user->name ?? 'N/A' }}</div>
                                                    <div><strong>Leave Type:</strong> {{ $request->leaveType->name ?? 'N/A' }}</div>
                                                    <div><strong>Period:</strong> {{ \Carbon\Carbon::parse($request->start_date)->format('M d') }} - {{ \Carbon\Carbon::parse($request->end_date)->format('M d, Y') }}</div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
                                                    <textarea name="rejection_reason" class="form-control" rows="3" 
                                                              placeholder="e.g., Insufficient staffing during this period..." required></textarea>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">Additional Comment (Optional)</label>
                                                    <textarea name="comment" class="form-control" rows="2" 
                                                              placeholder="Add a comment..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="bi bi-x-circle me-1"></i>Reject Leave
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="p-3">
            {{ $leaveRequests->appends(request()->query())->links() }}
        </div>
        @else
        <div class="text-center text-muted py-5">
            <i class="bi bi-calendar-check display-4 d-block mb-3 text-secondary"></i>
            <p class="mb-0 fs-6">No leave requests found.</p>
        </div>
        @endif
    </div>
</div>
@endsection
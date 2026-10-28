@extends('layouts.app')

@section('title', 'Leave Request Review')
@section('breadcrumb', 'Manager / Leave / Review')

@section('content')
@php
    $currentUser = auth()->user();
    
    // Explicit permission evaluations
    $isAdmin = method_exists($currentUser, 'isAdmin') ? $currentUser->isAdmin() : false;
    $isHr = (isset($currentUser->role) && strtoupper($currentUser->role) === 'HR') || (method_exists($currentUser, 'isHr') && $currentUser->isHr());
    $isSameDepartment = ($leaveRequest->user->department_id === $currentUser->department_id);

    // Determine if actions are permitted:
    // - Admins can always take action.
    // - HR and regular Managers can ONLY take action if it's within their own department ($isSameDepartment).
    if ($isAdmin) {
        $canTakeAction = true;
    } else {
        $canTakeAction = $isSameDepartment;
    }
@endphp

<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="bi bi-calendar-check me-2"></i>Leave Request Review</h2>
        <p class="text-muted mb-0">Reference Number: <strong>{{ $leaveRequest->request_number }}</strong></p>
    </div>
    <a href="{{ route('manager.leave.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to List
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-4 pb-3 border-bottom">
                    <div>
                        <h5 class="fw-bold mb-1">{{ $leaveRequest->request_number }}</h5>
                        <p class="text-muted small mb-0">
                            Submitted on {{ \Carbon\Carbon::parse($leaveRequest->created_at)->format('M d, Y h:i A') }}
                        </p>
                    </div>
                    <div>
                        @if($leaveRequest->status === 'PENDING')
                            <span class="badge bg-warning text-dark fs-6">Pending Review</span>
                        @elseif($leaveRequest->status === 'APPROVED')
                            <span class="badge bg-success fs-6">Approved</span>
                        @elseif($leaveRequest->status === 'REJECTED')
                            <span class="badge bg-danger fs-6">Rejected</span>
                        @else
                            <span class="badge bg-secondary fs-6">{{ $leaveRequest->status }}</span>
                        @endif
                    </div>
                </div>

                <h6 class="fw-bold mb-3"><i class="bi bi-person me-2"></i>Employee Information</h6>
                <div class="bg-light p-3 rounded border mb-4">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <small class="text-muted d-block">Employee Name</small>
                            <strong>{{ $leaveRequest->user->name ?? 'N/A' }}</strong>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Employee ID</small>
                            <strong>{{ $leaveRequest->user->employee_id ?? 'N/A' }}</strong>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Department</small>
                            <strong>{{ $leaveRequest->user->department->name ?? 'N/A' }}</strong>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Position</small>
                            <strong>{{ $leaveRequest->user->position ?? 'N/A' }}</strong>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold mb-3"><i class="bi bi-file-text me-2"></i>Leave Details</h6>
                <table class="table table-borderless align-middle">
                    <tbody>
                        <tr>
                            <td class="text-muted" width="30%">Leave Type:</td>
                            <td>
                                <span class="badge bg-info text-dark fs-6">{{ $leaveRequest->leaveType->name ?? 'N/A' }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Start Date:</td>
                            <td><strong>{{ \Carbon\Carbon::parse($leaveRequest->start_date)->format('l, M d, Y') }}</strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">End Date:</td>
                            <td><strong>{{ \Carbon\Carbon::parse($leaveRequest->end_date)->format('l, M d, Y') }}</strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Total Days:</td>
                            <td><strong class="fs-4 text-primary">{{ $leaveRequest->total_days }} days</strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Reason:</td>
                            <td>
                                <div class="p-3 bg-light rounded border text-secondary">
                                    {{ $leaveRequest->reason ?? 'No reason provided.' }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                @if($leaveRequest->rejection_reason)
                <div class="alert alert-danger mt-3 mb-0">
                    <strong>Rejection Reason:</strong><br>
                    {{ $leaveRequest->rejection_reason }}
                </div>
                @endif

                <!-- Action Buttons (Only shown if user can take action and request is pending) -->
                @if($canTakeAction && $leaveRequest->status === 'PENDING')
                <div class="mt-4 pt-3 border-top d-flex gap-2">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">
                        <i class="bi bi-check-circle me-1"></i> Approve Leave
                    </button>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                        <i class="bi bi-x-circle me-1"></i> Reject Leave
                    </button>
                </div>
                @elseif(!$canTakeAction && $leaveRequest->status === 'PENDING')
                <div class="alert alert-info mt-4 mb-0 py-2 small">
                    <i class="bi bi-info-circle me-1"></i> You are viewing this request in audit mode. Approval or rejection is restricted to the employee's direct department manager.
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Leave Balance Info -->
        @if(isset($leaveBalance) && $leaveBalance)
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-pie-chart me-2"></i>Leave Balance ({{ $leaveRequest->leaveType->name ?? 'N/A' }})</h6>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small text-muted">Total Days</span>
                        <strong>{{ $leaveBalance->total_days }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small text-muted">Used Days</span>
                        <strong class="text-danger">{{ $leaveBalance->used_days }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small text-muted">Pending Days</span>
                        <strong class="text-warning text-dark">{{ $leaveBalance->pending_days }}</strong>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted">Remaining Balance</span>
                        <strong class="text-success fs-5">{{ $leaveBalance->remaining_days }}</strong>
                    </div>
                </div>

                @if(($leaveBalance->remaining_days ?? 0) >= $leaveRequest->total_days)
                    <div class="alert alert-success small mb-0 py-2">
                        <i class="bi bi-check-circle me-1"></i> Sufficient balance available
                    </div>
                @else
                    <div class="alert alert-danger small mb-0 py-2">
                        <i class="bi bi-exclamation-triangle me-1"></i> Insufficient balance!
                    </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Approval History -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2"></i>Approval History</h6>

                @if($leaveRequest->approvals && $leaveRequest->approvals->count() > 0)
                    @foreach($leaveRequest->approvals as $approval)
                    <div class="mb-3 pb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div class="d-flex align-items-center mb-2">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold me-2" 
                                 style="width: 32px; height: 32px; font-size: 0.75rem;">
                                {{ strtoupper(substr($approval->reviewer->name ?? 'R', 0, 2)) }}
                            </div>
                            <div>
                                <strong class="d-block">{{ $approval->reviewer->name ?? 'Reviewer' }}</strong>
                                <small class="text-muted d-block">{{ $approval->reviewer->position ?? 'Manager' }}</small>
                            </div>
                        </div>

                        <div class="ms-1">
                            @if($approval->status === 'PENDING')
                                <span class="badge bg-warning text-dark">Pending</span>
                            @elseif($approval->status === 'APPROVED')
                                <span class="badge bg-success">Approved</span>
                                @if($approval->reviewed_at)
                                    <small class="text-muted d-block mt-1">
                                        {{ \Carbon\Carbon::parse($approval->reviewed_at)->format('M d, Y h:i A') }}
                                    </small>
                                @endif
                            @elseif($approval->status === 'REJECTED')
                                <span class="badge bg-danger">Rejected</span>
                                @if($approval->reviewed_at)
                                    <small class="text-muted d-block mt-1">
                                        {{ \Carbon\Carbon::parse($approval->reviewed_at)->format('M d, Y h:i A') }}
                                    </small>
                                @endif
                            @endif

                            @if($approval->comment)
                            <div class="mt-2 p-2 bg-light rounded small text-secondary">
                                <i class="bi bi-chat-left-quote me-1"></i>
                                {{ $approval->comment }}
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                @else
                    <p class="text-muted small mb-0">No approval records standardly generated.</p>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Approve & Reject Modals (Only rendered if user has action permissions) -->
@if($canTakeAction)
<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('manager.leave.approve', $leaveRequest->id) }}" method="POST">
                @csrf
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-check-circle me-2"></i>Approve Leave Request
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to approve this leave request for <strong>{{ $leaveRequest->user->name ?? 'the employee' }}</strong>?</p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Comment (Optional)</label>
                        <textarea name="comment" class="form-control" rows="3" 
                                  placeholder="Add an optional comment..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i> Approve Leave
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('manager.leave.reject', $leaveRequest->id) }}" method="POST">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-x-circle me-2"></i>Reject Leave Request
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" 
                                  placeholder="Provide a clear reason for rejection..." required></textarea>
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
                        <i class="bi bi-x-circle me-1"></i> Reject Leave
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
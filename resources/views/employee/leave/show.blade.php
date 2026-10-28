@extends('layouts.app')

@section('title', 'Leave Request Details')
@section('breadcrumb', 'Employee / Leave / View')

@section('content')
<div class="page-header mb-4">
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <h2><i class="bi bi-calendar-x me-2"></i>Leave Request Details</h2>
            <p class="text-muted mb-0">Request Reference: <strong>{{ $leaveRequest->request_number }}</strong></p>
        </div>
        <a href="{{ route('employee.leave.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>
    </div>
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

                <table class="table table-borderless align-middle">
                    <tbody>
                        <tr>
                            <td class="text-muted" width="30%">Leave Type:</td>
                            <td>
                                <span class="badge bg-info text-dark">{{ $leaveRequest->leaveType->name ?? 'N/A' }}</span>
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
                            <td><strong class="fs-5 text-primary">{{ $leaveRequest->total_days }} days</strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Reason:</td>
                            <td>{{ $leaveRequest->reason ?? 'No reason provided.' }}</td>
                        </tr>
                    </tbody>
                </table>

                @if($leaveRequest->rejection_reason)
                <div class="alert alert-danger mt-3 mb-0">
                    <strong>Rejection Reason:</strong><br>
                    {{ $leaveRequest->rejection_reason }}
                </div>
                @endif

                @if($leaveRequest->status === 'PENDING')
                <div class="mt-4 pt-3 border-top">
                    <form action="{{ route('employee.leave.cancel', $leaveRequest->id) }}" 
                          method="POST" 
                          onsubmit="return confirm('Are you sure you want to cancel this leave request?');">
                        @csrf
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-x-circle me-2"></i>Cancel Leave Request
                        </button>
                    </form>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Approval Status -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2"></i>Approval Status</h6>

                @if($leaveRequest->approvals && $leaveRequest->approvals->count() > 0)
                    @foreach($leaveRequest->approvals as $approval)
                    <div class="mb-3 pb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div class="d-flex align-items-center mb-2">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2 fw-bold" 
                                 style="width: 36px; height: 36px; font-size: 0.8rem;">
                                {{ strtoupper(substr($approval->reviewer->name ?? 'A', 0, 2)) }}
                            </div>
                            <div>
                                <strong class="d-block">{{ $approval->reviewer->name ?? 'Assigned Reviewer' }}</strong>
                                <small class="text-muted d-block">{{ $approval->reviewer->position ?? 'Reviewer' }}</small>
                            </div>
                        </div>

                        <div class="ms-1">
                            @if($approval->status === 'PENDING')
                                <span class="badge bg-warning text-dark">Pending Review</span>
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
                    <p class="text-muted small mb-0">No approval workflow configured.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
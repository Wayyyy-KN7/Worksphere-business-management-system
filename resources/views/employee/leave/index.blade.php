@extends('layouts.app')

@section('title', 'My Leave Requests')
@section('breadcrumb', 'Employee / Leave')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="bi bi-calendar-check me-2"></i>My Leave Requests</h2>
        <p class="text-muted">View and manage your leave applications</p>
    </div>
    <a href="{{ route('employee.leave.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-2"></i>Apply for Leave
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Leave Type</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Total Days</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leaveRequests as $request)
                        <tr>
                            <td><strong>{{ $request->request_number }}</strong></td>
                            <td>{{ $request->leaveType->name ?? 'N/A' }}</td>
                            <td>{{ $request->start_date }}</td>
                            <td>{{ $request->end_date }}</td>
                            <td>{{ $request->total_days }} day(s)</td>
                            <td>
                                @php
                                    $badgeColor = match($request->status) {
                                        'APPROVED' => 'success',
                                        'REJECTED' => 'danger',
                                        default => 'warning'
                                    };
                                @endphp
                                <span class="badge bg-{{ $badgeColor }}">{{ $request->status }}</span>
                            </td>
                            <td>
                                <x-table-actions>
                                    <li><a class="dropdown-item" href="{{ route('employee.leave.show', $request->id) }}"><i class="bi bi-eye"></i>View</a></li>
                                </x-table-actions>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                No leave requests found. Click "Apply for Leave" to create one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="mt-3">
            {{ $leaveRequests->links() }}
        </div>
    </div>
</div>
@endsection
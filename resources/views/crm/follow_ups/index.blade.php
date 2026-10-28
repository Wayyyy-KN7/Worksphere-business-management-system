@extends('layouts.app')

@section('title', 'Follow-Ups')
@section('breadcrumb', 'CRM / Follow-Ups')

@section('content')
<div class="container-fluid">
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h2><i class="bi bi-arrow-left-right me-2"></i>Follow-Ups</h2>
            <p>Review and manage customer follow-up activities</p>
        </div>
        <a href="{{ route('crm.follow-ups.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>New Follow-Up
        </a>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('crm.follow-ups.index') }}">
                <div class="row g-3">
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="PLANNED" {{ request('status') === 'PLANNED' ? 'selected' : '' }}>Planned</option>
                            <option value="IN_PROGRESS" {{ request('status') === 'IN_PROGRESS' ? 'selected' : '' }}>In Progress</option>
                            <option value="OVERDUE" {{ request('status') === 'OVERDUE' ? 'selected' : '' }}>Overdue</option>
                            <option value="COMPLETED" {{ request('status') === 'COMPLETED' ? 'selected' : '' }}>Completed</option>
                            <option value="CANCELLED" {{ request('status') === 'CANCELLED' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="type" class="form-select">
                            <option value="">All Types</option>
                            <option value="CALL" {{ request('type') === 'CALL' ? 'selected' : '' }}>Call</option>
                            <option value="EMAIL" {{ request('type') === 'EMAIL' ? 'selected' : '' }}>Email</option>
                            <option value="MEETING" {{ request('type') === 'MEETING' ? 'selected' : '' }}>Meeting</option>
                            <option value="VISIT" {{ request('type') === 'VISIT' ? 'selected' : '' }}>Visit</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-secondary">Filter</button>
                        <a href="{{ route('crm.follow-ups.index') }}" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Follow-Ups Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Customer</th>
                            <th>Opportunity</th>
                            <th>Type</th>
                            <th>Due</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($followUps as $followUp)
                        <tr class="{{ $followUp->status === 'OVERDUE' ? 'table-danger' : '' }}">
                            <td>{{ $followUp->title }}</td>
                            <td>{{ $followUp->customer->name ?? 'N/A' }}</td>
                            <td>{{ $followUp->opportunity->title ?? 'N/A' }}</td>
                            <td>
                                <span class="badge bg-info">{{ $followUp->type }}</span>
                            </td>
                            <td>{{ $followUp->due_at->format('d M Y, h:i A') }}</td>
                            <td><span class="badge bg-{{ $followUp->priority === 'URGENT' ? 'danger' : ($followUp->priority === 'HIGH' ? 'warning' : 'secondary') }}">{{ $followUp->priority }}</span></td>
                            <td>
                                @php
                                    $statusColors = [
                                        'PLANNED' => 'primary',
                                        'IN_PROGRESS' => 'info',
                                        'OVERDUE' => 'danger',
                                        'COMPLETED' => 'success',
                                        'CANCELLED' => 'secondary'
                                    ];
                                @endphp
                                <span class="badge bg-{{ $statusColors[$followUp->status] ?? 'secondary' }}">
                                    {{ $followUp->status }}
                                </span>
                            </td>
                            <td>{{ $followUp->assignedUser->name ?? 'N/A' }}</td>
                            <td>
                                <x-table-actions>
                                    <li><a class="dropdown-item" href="{{ route('crm.follow-ups.show', $followUp) }}"><i class="bi bi-eye"></i>View</a></li>
                                    @can('update', $followUp)
                                        <li><a class="dropdown-item" href="{{ route('crm.follow-ups.edit', $followUp) }}"><i class="bi bi-pencil"></i>Edit</a></li>
                                    @endcan
                                    @can('perform', $followUp)
                                        @if($followUp->status === 'PLANNED')
                                            <li><form action="{{ route('crm.follow-ups.start', $followUp) }}" method="POST">@csrf<button type="submit" class="dropdown-item"><i class="bi bi-play-fill"></i>Start</button></form></li>
                                            <li><form action="{{ route('crm.follow-ups.cancel', $followUp) }}" method="POST">@csrf<button type="submit" class="dropdown-item text-danger"><i class="bi bi-x-lg"></i>Cancel</button></form></li>
                                        @elseif(in_array($followUp->status, ['IN_PROGRESS', 'OVERDUE']))
                                            <li><a class="dropdown-item" href="{{ route('crm.follow-ups.show', $followUp) }}#complete"><i class="bi bi-check-circle"></i>Complete or reschedule</a></li>
                                        @endif
                                    @endcan
                                </x-table-actions>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center">No Follow-Ups found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $followUps->links() }}
        </div>
    </div>
</div>
@endsection
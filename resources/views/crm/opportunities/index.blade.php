@extends('layouts.app')

@section('title', 'Opportunities')
@section('breadcrumb', 'CRM / Opportunities')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="bi bi-funnel me-2"></i>Opportunities</h2>
        <p class="text-muted">Manage sales opportunities and pipeline</p>
    </div>
    <a href="{{ route('crm.opportunities.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-2"></i>Add Opportunity
    </a>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small fw-bold">Total Opportunities</div>
                    <div class="fs-4 fw-bold">{{ $stats['total'] }}</div>
                </div>
                <div class="p-2 bg-primary bg-opacity-10 text-primary rounded">
                    <i class="bi bi-funnel"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small fw-bold">Open</div>
                    <div class="fs-4 fw-bold text-info">{{ $stats['open'] }}</div>
                </div>
                <div class="p-2 bg-info bg-opacity-10 text-info rounded">
                    <i class="bi bi-hourglass-split"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small fw-bold">Won</div>
                    <div class="fs-4 fw-bold text-success">{{ $stats['won'] }}</div>
                </div>
                <div class="p-2 bg-success bg-opacity-10 text-success rounded">
                    <i class="bi bi-trophy"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small fw-bold">Lost</div>
                    <div class="fs-4 fw-bold text-danger">{{ $stats['lost'] }}</div>
                </div>
                <div class="p-2 bg-danger bg-opacity-10 text-danger rounded">
                    <i class="bi bi-x-circle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('crm.opportunities.index') }}">
            <div class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search title, number, customer..." 
                           value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <select name="stage" class="form-select">
                        <option value="">All Stages</option>
                        <option value="NEW" {{ request('stage') === 'NEW' ? 'selected' : '' }}>New</option>
                        <option value="QUALIFIED" {{ request('stage') === 'QUALIFIED' ? 'selected' : '' }}>Qualified</option>
                        <option value="PROPOSAL" {{ request('stage') === 'PROPOSAL' ? 'selected' : '' }}>Proposal</option>
                        <option value="NEGOTIATION" {{ request('stage') === 'NEGOTIATION' ? 'selected' : '' }}>Negotiation</option>
                        <option value="WON" {{ request('stage') === 'WON' ? 'selected' : '' }}>Won</option>
                        <option value="LOST" {{ request('stage') === 'LOST' ? 'selected' : '' }}>Lost</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="OPEN" {{ request('status') === 'OPEN' ? 'selected' : '' }}>Open</option>
                        <option value="CLOSED" {{ request('status') === 'CLOSED' ? 'selected' : '' }}>Closed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="assigned_to" class="form-select">
                        <option value="">All Assigned Users</option>
                        @foreach($assignedUsers as $userOption)
                            <option value="{{ $userOption->id }}" {{ request('assigned_to') == $userOption->id ? 'selected' : '' }}>
                                {{ $userOption->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary flex-grow-1">Filter</button>
                    <a href="{{ route('crm.opportunities.index') }}" class="btn btn-outline-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Opportunities Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Opportunity #</th>
                        <th>Customer</th>
                        <th>Title</th>
                        <th>Value</th>
                        <th>Probability</th>
                        <th>Stage</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($opportunities as $opportunity)
                    <tr>
                        <td><strong>{{ $opportunity->opportunity_number }}</strong></td>
                        <td>{{ $opportunity->customer->name ?? 'N/A' }}</td>
                        <td>
                            <strong>{{ $opportunity->title }}</strong>
                            @if($opportunity->expected_close_date)
                                <small class="text-muted d-block">Close: {{ $opportunity->expected_close_date->format('M d, Y') }}</small>
                            @endif
                        </td>
                        <td><strong class="text-primary">${{ number_format($opportunity->estimated_value, 2) }}</strong></td>
                        <td>
                            <div class="d-flex align-items-center gap-2" style="width: 120px;">
                                <div class="progress flex-grow-1" style="height: 6px;">
                                    <div class="progress-bar bg-success" style="width: {{ $opportunity->probability }}%"></div>
                                </div>
                                <small class="text-muted">{{ $opportunity->probability }}%</small>
                            </div>
                        </td>
                        <td>
                            @php
                                $stageColors = [
                                    'NEW' => 'secondary',
                                    'QUALIFIED' => 'info',
                                    'PROPOSAL' => 'primary',
                                    'NEGOTIATION' => 'warning',
                                    'WON' => 'success',
                                    'LOST' => 'danger'
                                ];
                            @endphp
                            <span class="badge bg-{{ $stageColors[$opportunity->stage] ?? 'secondary' }}">
                                {{ $opportunity->stage }}
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-{{ $opportunity->status === 'OPEN' ? 'success' : 'secondary' }}">
                                {{ $opportunity->status }}
                            </span>
                        </td>
                        <td>{{ $opportunity->assignedUser->name ?? 'N/A' }}</td>
                        <td>
                            <x-table-actions>
                                <li><a class="dropdown-item" href="{{ route('crm.opportunities.show', $opportunity) }}"><i class="bi bi-eye"></i>View</a></li>
                                @can('update', $opportunity)
                                <li><a class="dropdown-item" href="{{ route('crm.opportunities.edit', $opportunity) }}"><i class="bi bi-pencil"></i>Edit</a></li>
                                @endcan
                            </x-table-actions>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No opportunities found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $opportunities->appends(request()->query())->links() }}
        </div>
    </div>
</div>
@endsection
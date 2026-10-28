@extends('layouts.app')

@section('title', ($archived ?? false) ? 'Archived Customers' : 'Customers')
@section('breadcrumb', ($archived ?? false) ? 'CRM / Customers / Archived' : 'CRM / Customers')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="bi bi-person-rolodex me-2"></i>{{ ($archived ?? false) ? 'Archived Customers' : 'Customers' }}</h2>
        <p>{{ ($archived ?? false) ? 'View and restore archived customer records' : 'Manage customer relationships' }}</p>
    </div>
    <div class="d-flex gap-2">
        @if($archived ?? false)
            <a href="{{ route('crm.customers.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Active Customers
            </a>
        @else
            <a href="{{ route('crm.customers.archived') }}" class="btn btn-outline-secondary">
                <i class="bi bi-archive me-2"></i>Archived Customers
            </a>
            <a href="{{ route('crm.customers.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Add Customer
            </a>
        @endif
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" 
                       placeholder="Search name, email, phone..." 
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="source" class="form-select">
                    <option value="">All Sources</option>
                    @foreach($sources as $source)
                        <option value="{{ $source->id }}" {{ request('source') == $source->id ? 'selected' : '' }}>
                            {{ $source->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="LEAD" {{ request('status') == 'LEAD' ? 'selected' : '' }}>Lead</option>
                    <option value="PROSPECT" {{ request('status') == 'PROSPECT' ? 'selected' : '' }}>Prospect</option>
                    <option value="ACTIVE" {{ request('status') == 'ACTIVE' ? 'selected' : '' }}>Active</option>
                    <option value="INACTIVE" {{ request('status') == 'INACTIVE' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="assigned_to" class="form-select">
                    <option value="">All Salespeople</option>
                    @foreach($assignedUsers as $assignedUser)
                        <option value="{{ $assignedUser->id }}" {{ request('assigned_to') == $assignedUser->id ? 'selected' : '' }}>
                            {{ $assignedUser->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="department" class="form-select">
                    <option value="">All Departments</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" {{ request('department') == $department->id ? 'selected' : '' }}>
                            {{ $department->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="created_by" class="form-select">
                    <option value="">All Creators</option>
                    @foreach($creators as $creator)
                        <option value="{{ $creator->id }}" {{ request('created_by') == $creator->id ? 'selected' : '' }}>
                            {{ $creator->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-2"></i>Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Customers Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        @if($customers->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Customer Code</th>
                        <th>Name</th>
                        <th>Company</th>
                        <th>Contact</th>
                        <th>Source</th>
                        <th>Created By</th>
                        <th>Assigned Department</th>
                        <th>Assigned To</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customers as $customer)
                    <tr>
                        <td><strong>{{ $customer->customer_code }}</strong></td>
                        <td>
                            <div>
                                <strong>{{ $customer->name }}</strong>
                                @if($customer->customer_type)
                                    <small class="text-muted d-block">{{ $customer->customer_type }}</small>
                                @endif
                            </div>
                        </td>
                        <td>{{ $customer->company_name ?? '-' }}</td>
                        <td>
                            @if($customer->email)
                                <div><i class="bi bi-envelope me-1"></i>{{ $customer->email }}</div>
                            @endif
                            @if($customer->phone)
                                <div><i class="bi bi-telephone me-1"></i>{{ $customer->phone }}</div>
                            @endif
                        </td>
                        <td>
                            @if($customer->source)
                                <span class="badge bg-info">{{ $customer->source->name }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if($customer->creator)
                                <div>{{ $customer->creator->name }}</div>
                                <small class="text-muted">{{ $customer->creatorDepartment->name ?? '' }}</small>
                            @else
                                <span class="text-muted">Unknown</span>
                            @endif
                        </td>
                        <td>{{ $customer->assignedDepartment->name ?? 'Unassigned' }}</td>
                        <td>
                            @if($customer->assignedUser)
                                <div>{{ $customer->assignedUser->name }}</div>
                            @else
                                <span class="text-muted">No salesperson</span>
                            @endif
                        </td>
                        <td>
                            @if($customer->trashed())
                                <span class="badge bg-secondary">Archived</span>
                            @elseif($customer->status === 'ACTIVE')
                                <span class="badge badge-active">Active</span>
                            @elseif($customer->status === 'LEAD')
                                <span class="badge bg-warning">Lead</span>
                            @elseif($customer->status === 'PROSPECT')
                                <span class="badge bg-info">Prospect</span>
                            @else
                                <span class="badge badge-inactive">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <x-table-actions>
                                <li><a class="dropdown-item" href="{{ route('crm.customers.show', $customer) }}"><i class="bi bi-eye"></i>View</a></li>
                                @if(!($archived ?? false))
                                @can('update', $customer)
                                <li><a class="dropdown-item" href="{{ route('crm.customers.edit', $customer) }}"><i class="bi bi-pencil"></i>Edit</a></li>
                                @endcan
                                @endif
                                @if($archived ?? false)
                                @can('restore', $customer)
                                <li><form action="{{ route('crm.customers.restore', $customer->id) }}" method="POST">@csrf<button type="submit" class="dropdown-item"><i class="bi bi-arrow-counterclockwise"></i>Restore</button></form></li>
                                @endcan
                                @else
                                @can('archive', $customer)
                                <li><form action="{{ route('crm.customers.destroy', $customer) }}" method="POST" onsubmit="return confirm('Archive this customer? Related records will be retained.');">@csrf @method('DELETE')<button type="submit" class="dropdown-item"><i class="bi bi-archive"></i>Archive</button></form></li>
                                @endcan
                                @endif
                            </x-table-actions>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $customers->appends(request()->query())->links() }}
        </div>
        @else
        <div class="text-center text-muted py-5">
            <i class="bi bi-person-rolodex display-4 d-block mb-3"></i>
            <p>No customers found. Add your first customer to get started!</p>
        </div>
        @endif
    </div>
</div>
@endsection
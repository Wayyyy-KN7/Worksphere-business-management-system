@extends('layouts.app')

@section('title', 'Sales')
@section('breadcrumb', 'CRM / Sales')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="bi bi-cart-check me-2"></i>Sales</h2>
        <p>Manage sales transactions and invoices</p>
    </div>
    <a href="{{ route('crm.sales.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-2"></i>Create Sale
    </a>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Total Sales</div>
                    <div class="stat-value">{{ $stats['total_sales'] }}</div>
                </div>
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-cart-check"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-value text-success">${{ number_format($stats['total_revenue'], 0) }}</div>
                </div>
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-currency-dollar"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Pending Payment</div>
                    <div class="stat-value text-warning">${{ number_format($stats['pending_payment'], 0) }}</div>
                </div>
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-hourglass-split"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Paid Sales</div>
                    <div class="stat-value text-info">{{ $stats['paid'] }}</div>
                </div>
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" 
                       placeholder="Search sale #, customer..." 
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="payment_status" class="form-select">
                    <option value="">All Payment Status</option>
                    <option value="PENDING" {{ request('payment_status') == 'PENDING' ? 'selected' : '' }}>Pending</option>
                    <option value="PARTIAL" {{ request('payment_status') == 'PARTIAL' ? 'selected' : '' }}>Partial</option>
                    <option value="PAID" {{ request('payment_status') == 'PAID' ? 'selected' : '' }}>Paid</option>
                    <option value="REFUNDED" {{ request('payment_status') == 'REFUNDED' ? 'selected' : '' }}>Refunded</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="start_date" class="form-control" 
                       placeholder="Start Date" value="{{ request('start_date') }}">
            </div>
            <div class="col-md-2">
                <input type="date" name="end_date" class="form-control" 
                       placeholder="End Date" value="{{ request('end_date') }}">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-2"></i>Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Sales Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        @if($sales->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Sale #</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Payment Status</th>
                        <th>Payment Method</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sales as $sale)
                    <tr>
                        <td><strong>{{ $sale->sale_number }}</strong></td>
                        <td>
                            <div>{{ $sale->customer?->name ?? 'Customer unavailable' }}</div>
                            @if($sale->opportunity)
                                <small class="text-muted">{{ $sale->opportunity->opportunity_number }}</small>
                            @endif
                        </td>
                        <td>{{ $sale->sale_date->format('M d, Y') }}</td>
                        <td><strong class="text-primary">${{ number_format($sale->final_amount, 2) }}</strong></td>
                        <td>
                            @if($sale->payment_status === 'PAID')
                                <span class="badge badge-approved">Paid</span>
                            @elseif($sale->payment_status === 'PENDING')
                                <span class="badge badge-pending">Pending</span>
                            @elseif($sale->payment_status === 'PARTIAL')
                                <span class="badge bg-warning">Partial</span>
                            @elseif($sale->payment_status === 'REFUNDED')
                                <span class="badge bg-danger">Refunded</span>
                            @else
                                <span class="badge bg-secondary">{{ $sale->payment_status }}</span>
                            @endif
                        </td>
                        <td>{{ $sale->payment_method ?? '-' }}</td>
                        <td>
                            <x-table-actions>
                                <li><a class="dropdown-item" href="{{ route('crm.sales.show', $sale) }}"><i class="bi bi-eye"></i>View</a></li>
                                @if(Route::has('crm.sales.invoice'))
                                <li><a class="dropdown-item" href="{{ route('crm.sales.invoice', $sale) }}" target="_blank"><i class="bi bi-file-earmark-text"></i>Invoice</a></li>
                                @endif
                                @can('update', $sale)
                                <li><a class="dropdown-item" href="{{ route('crm.sales.edit', $sale) }}"><i class="bi bi-pencil"></i>Edit</a></li>
                                @endcan
                                @if(auth()->check() && auth()->user()->isAdmin())
                                <li><form action="{{ route('crm.sales.destroy', $sale) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this sale?');">@csrf @method('DELETE')<button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i>Delete</button></form></li>
                                @endif
                            </x-table-actions>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $sales->appends(request()->query())->links() }}
        </div>
        @else
        <div class="text-center text-muted py-5">
            <i class="bi bi-cart-check display-4 d-block mb-3"></i>
            <p>No sales found. Create your first sale!</p>
        </div>
        @endif
    </div>
</div>
@endsection
@extends('layouts.app')

@section('title', 'Sale Details')
@section('breadcrumb', 'CRM / Sales / View')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="bi bi-cart-check me-2"></i>{{ $sale->sale_number }}</h2>
        <p>{{ $sale->customer?->name ?? 'Customer unavailable' }} - {{ $sale->sale_date->format('M d, Y') }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('crm.sales.invoice', $sale) }}" class="btn btn-info" target="_blank">
            <i class="bi bi-file-earmark-text me-2"></i>View Invoice
        </a>
        @can('update', $sale)
        <a href="{{ route('crm.sales.edit', $sale) }}" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-2"></i>Edit
        </a>
        @endcan
        <a href="{{ route('crm.sales.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Back to List
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Sale Information -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Sale Information</h6>
                
                <table class="table table-sm table-borderless">
                    <tr>
                        <td class="text-muted" width="40%">Sale #:</td>
                        <td><strong>{{ $sale->sale_number }}</strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Date:</td>
                        <td>{{ $sale->sale_date->format('M d, Y') }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Customer:</td>
                        <td>
                            @if($sale->customer)
                                <a href="{{ route('crm.customers.show', $sale->customer) }}">{{ $sale->customer->name }}</a>
                                @if($sale->customer->trashed())
                                    <span class="badge bg-secondary">Archived</span>
                                @endif
                            @else
                                Customer unavailable
                            @endif
                        </td>
                    </tr>
                    @if($sale->opportunity)
                    <tr>
                        <td class="text-muted">Opportunity:</td>
                        <td>
                            <a href="{{ route('crm.opportunities.show', $sale->opportunity) }}">
                                {{ $sale->opportunity->opportunity_number }}
                            </a>
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td class="text-muted">Payment Status:</td>
                        <td>
                            @if($sale->payment_status === 'PAID')
                                <span class="badge badge-approved">Paid</span>
                            @elseif($sale->payment_status === 'PENDING')
                                <span class="badge badge-pending">Pending</span>
                            @elseif($sale->payment_status === 'PARTIAL')
                                <span class="badge bg-warning">Partial</span>
                            @else
                                <span class="badge bg-secondary">{{ $sale->payment_status }}</span>
                            @endif
                        </td>
                    </tr>
                    @if($sale->payment_method)
                    <tr>
                        <td class="text-muted">Payment Method:</td>
                        <td>{{ $sale->payment_method }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="text-muted">Assigned To:</td>
                        <td>{{ $sale->assignedUser->name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Created:</td>
                        <td>{{ $sale->created_at->format('M d, Y h:i A') }}</td>
                    </tr>
                </table>

                @if($sale->notes)
                <hr>
                <h6 class="fw-bold mb-2">Notes</h6>
                <p class="small text-muted">{{ $sale->notes }}</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Sale Items & Summary -->
    <div class="col-lg-8">
        <!-- Sale Items -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Sale Items</h6>
                
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Product/Service</th>
                                <th>Description</th>
                                <th>Qty</th>
                                <th>Price</th>
                                <th>Discount</th>
                                <th>Tax</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sale->items as $index => $item)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td><strong>{{ $item->product_name }}</strong></td>
                                <td>{{ $item->description ?? '-' }}</td>
                                <td>{{ number_format($item->quantity, 2) }}</td>
                                <td>${{ number_format($item->unit_price, 2) }}</td>
                                <td>${{ number_format($item->discount, 2) }}</td>
                                <td>${{ number_format($item->tax, 2) }}</td>
                                <td class="text-end"><strong>${{ number_format($item->total, 2) }}</strong></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sale Summary -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Sale Summary</h6>
                
                <div class="row">
                    <div class="col-md-6 offset-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td>Subtotal:</td>
                                <td class="text-end"><strong>${{ number_format($sale->total_amount, 2) }}</strong></td>
                            </tr>
                            <tr>
                                <td>Discount:</td>
                                <td class="text-end text-danger">-${{ number_format($sale->discount_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Tax:</td>
                                <td class="text-end text-success">+${{ number_format($sale->tax_amount, 2) }}</td>
                            </tr>
                            <tr class="border-top">
                                <td><strong class="fs-5">Grand Total:</strong></td>
                                <td class="text-end"><strong class="fs-4 text-primary">${{ number_format($sale->final_amount, 2) }}</strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@extends('layouts.app')

@section('title', 'Customer Details')
@section('breadcrumb', 'CRM / Customers / View')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="bi bi-person-circle me-2"></i>{{ $customer->name }}</h2>
        <p>{{ $customer->customer_code }} @if($customer->company_name) - {{ $customer->company_name }} @endif</p>
    </div>
    <div class="d-flex gap-2">
        @if($customer->trashed())
            @can('restore', $customer)
            <form action="{{ route('crm.customers.restore', $customer->id) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-counterclockwise me-2"></i>Restore Customer</button>
            </form>
            @endcan
        @else
        @can('archive', $customer)
            <form action="{{ route('crm.customers.destroy', $customer) }}" method="POST" onsubmit="return confirm('Archive this customer? Related records will be retained.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-archive me-2"></i>Archive Customer</button>
            </form>
        @endcan
        @can('update', $customer)
        <a href="{{ route('crm.customers.edit', $customer) }}" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-2"></i>Edit Customer
        </a>
        @endcan
        @endif
        <a href="{{ route('crm.customers.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Back to List
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Customer Information -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Customer Information</h6>
                
                <table class="table table-sm table-borderless">
                    <tr>
                        <td class="text-muted" width="40%">Code:</td>
                        <td><strong>{{ $customer->customer_code }}</strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Name:</td>
                        <td>{{ $customer->name }}</td>
                    </tr>
                    @if($customer->company_name)
                    <tr>
                        <td class="text-muted">Company:</td>
                        <td>{{ $customer->company_name }}</td>
                    </tr>
                    @endif
                    @if($customer->customer_type)
                    <tr>
                        <td class="text-muted">Type:</td>
                        <td><span class="badge bg-secondary">{{ $customer->customer_type }}</span></td>
                    </tr>
                    @endif
                    <tr>
                        <td class="text-muted">Status:</td>
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
                    </tr>
                    @if($customer->source)
                    <tr>
                        <td class="text-muted">Source:</td>
                        <td><span class="badge bg-info">{{ $customer->source->name }}</span></td>
                    </tr>
                    @endif
                </table>

                <hr>

                <h6 class="fw-bold mb-3">Contact Information</h6>
                <table class="table table-sm table-borderless">
                    @if($customer->email)
                    <tr>
                        <td class="text-muted" width="40%">Email:</td>
                        <td><a href="mailto:{{ $customer->email }}">{{ $customer->email }}</a></td>
                    </tr>
                    @endif
                    @if($customer->phone)
                    <tr>
                        <td class="text-muted">Phone:</td>
                        <td><a href="tel:{{ $customer->phone }}">{{ $customer->phone }}</a></td>
                    </tr>
                    @endif
                    @if($customer->address || $customer->city || $customer->country)
                    <tr>
                        <td class="text-muted">Address:</td>
                        <td>
                            @if($customer->address){{ $customer->address }}<br>@endif
                            @if($customer->city){{ $customer->city }}, @endif
                            @if($customer->country){{ $customer->country }}@endif
                        </td>
                    </tr>
                    @endif
                </table>

                <hr>

                <h6 class="fw-bold mb-3">Assignment</h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td class="text-muted" width="40%">Created By:</td>
                        <td>
                            {{ $customer->creator->name ?? 'Unknown' }}
                            @if($customer->creatorDepartment)
                                <small class="text-muted d-block">{{ $customer->creatorDepartment->name }}</small>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Assigned Department:</td>
                        <td>{{ $customer->assignedDepartment->name ?? 'Unassigned' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Assigned To:</td>
                        <td>
                            @if($customer->assignedUser)
                                <div>{{ $customer->assignedUser->name }}</div>
                                <small class="text-muted">{{ $customer->assignedUser->department->name ?? '' }}</small>
                            @else
                                <span class="text-muted">Unassigned</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Created:</td>
                        <td>{{ $customer->created_at->format('M d, Y') }}</td>
                    </tr>
                </table>

                @if($customer->notes)
                <hr>
                <h6 class="fw-bold mb-2">Notes</h6>
                <p class="small text-muted">{{ $customer->notes }}</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Activity Tabs -->
    <div class="col-lg-8">
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#opportunities" type="button">
                    <i class="bi bi-funnel me-2"></i>Opportunities ({{ $customer->opportunities->count() }})
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#followups" type="button">
                    <i class="bi bi-telephone-forward me-2"></i>Follow-ups ({{ $customer->followUps->count() }})
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#sales" type="button">
                    <i class="bi bi-cart-check me-2"></i>Sales ({{ $customer->sales->count() }})
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Opportunities Tab -->
            <div class="tab-pane fade show active" id="opportunities">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        @if($customer->opportunities->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Opportunity #</th>
                                        <th>Title</th>
                                        <th>Value</th>
                                        <th>Stage</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($customer->opportunities as $opp)
                                    <tr>
                                        <td><strong>{{ $opp->opportunity_number }}</strong></td>
                                        <td>{{ $opp->title }}</td>
                                        <td>${{ number_format($opp->estimated_value, 2) }}</td>
                                        <td><span class="badge bg-primary">{{ $opp->stage }}</span></td>
                                        <td><span class="badge bg-info">{{ $opp->status }}</span></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-funnel display-4 d-block mb-2"></i>
                            <p>No opportunities yet</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Follow-ups Tab -->
            <div class="tab-pane fade" id="followups">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        @if($customer->followUps->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Title</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Assigned To</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($customer->followUps as $followUp)
                                    <tr>
                                        <td>{{ $followUp->title }}</td>
                                        <td>{{ $followUp->follow_up_date->format('M d, Y') }}</td>
                                        <td>
                                            @if($followUp->status === 'COMPLETED')
                                                <span class="badge badge-approved">Completed</span>
                                            @elseif($followUp->status === 'PENDING')
                                                <span class="badge badge-pending">Pending</span>
                                            @else
                                                <span class="badge bg-secondary">{{ $followUp->status }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $followUp->assignedUser->name ?? '-' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-telephone-forward display-4 d-block mb-2"></i>
                            <p>No follow-ups yet</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Sales Tab -->
            <div class="tab-pane fade" id="sales">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        @if($customer->sales->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Sale #</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Payment Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($customer->sales as $sale)
                                    <tr>
                                        <td><strong>{{ $sale->sale_number }}</strong></td>
                                        <td>{{ $sale->sale_date->format('M d, Y') }}</td>
                                        <td><strong>${{ number_format($sale->final_amount, 2) }}</strong></td>
                                        <td>
                                            @if($sale->payment_status === 'PAID')
                                                <span class="badge badge-approved">Paid</span>
                                            @elseif($sale->payment_status === 'PENDING')
                                                <span class="badge badge-pending">Pending</span>
                                            @else
                                                <span class="badge bg-secondary">{{ $sale->payment_status }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-cart-check display-4 d-block mb-2"></i>
                            <p>No sales yet</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
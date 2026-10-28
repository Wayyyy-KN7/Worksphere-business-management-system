@extends('layouts.app')

@section('title', 'Opportunity Details')
@section('breadcrumb', 'CRM / Opportunities / View')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="bi bi-funnel me-2"></i>{{ $opportunity->title }}</h2>
        <p class="text-muted">{{ $opportunity->opportunity_number }} @if($opportunity->customer) - {{ $opportunity->customer->name }} @endif</p>
    </div>
    <div class="d-flex gap-2">
        @can('update', $opportunity)
        <a href="{{ route('crm.opportunities.edit', $opportunity) }}" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-2"></i>Edit
        </a>
        @endcan
        <a href="{{ route('crm.opportunities.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Back to List
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Opportunity Info -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Opportunity Information</h6>
                
                <table class="table table-sm table-borderless">
                    <tr>
                        <td class="text-muted" width="40%">Number:</td>
                        <td><strong>{{ $opportunity->opportunity_number }}</strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Customer:</td>
                        <td>
                            @if($opportunity->customer)
                                {{ $opportunity->customer->customer_code }} - {{ $opportunity->customer->name }}
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Assigned To:</td>
                        <td>
                            @if($opportunity->assignedUser)
                                {{ $opportunity->assignedUser->name }}
                            @else
                                <span class="text-muted">Unassigned</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status:</td>
                        <td>
                            @if($opportunity->status === 'OPEN')
                                <span class="badge bg-success">Open</span>
                            @elseif($opportunity->status === 'CLOSED')
                                <span class="badge bg-secondary">Closed</span>
                            @else
                                <span class="badge bg-secondary">{{ $opportunity->status }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Created:</td>
                        <td>{{ $opportunity->created_at->format('M d, Y') }}</td>
                    </tr>
                    @if($opportunity->actual_close_date)
                    <tr>
                        <td class="text-muted">Closed:</td>
                        <td>{{ $opportunity->actual_close_date->format('M d, Y') }}</td>
                    </tr>
                    @endif
                    @if($opportunity->lost_reason)
                    <tr>
                        <td class="text-muted">Lost Reason:</td>
                        <td>{{ $opportunity->lost_reason }}</td>
                    </tr>
                    @endif
                </table>

                <hr>

                <!-- Stage & Probability -->
                <h6 class="fw-bold mb-2">Pipeline Stage</h6>
                <div class="mb-3">
                    <span class="badge fs-6 
                        @if($opportunity->stage === 'WON') bg-success
                        @elseif($opportunity->stage === 'LOST') bg-danger
                        @elseif($opportunity->stage === 'NEGOTIATION') bg-warning text-dark
                        @elseif($opportunity->stage === 'PROPOSAL') bg-primary
                        @elseif($opportunity->stage === 'QUALIFIED') bg-info text-dark
                        @else bg-secondary @endif">
                        {{ strtoupper($opportunity->stage) }}
                    </span>
                </div>
                
                <div class="progress mb-1" style="height: 15px;">
                    <div class="progress-bar bg-success" 
                         style="width: {{ $opportunity->probability }}%"
                         aria-valuenow="{{ $opportunity->probability }}" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                        {{ $opportunity->probability }}%
                    </div>
                </div>
                <small class="text-muted">Probability of closing</small>

                <hr>

                <!-- Financial -->
                <h6 class="fw-bold mb-2">Financial</h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td class="text-muted" width="40%">Estimated Value:</td>
                        <td><strong class="text-primary">${{ number_format($opportunity->estimated_value, 2) }}</strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Expected Value:</td>
                        <td>
                            @php $expectedValue = $opportunity->estimated_value * ($opportunity->probability / 100); @endphp
                            <strong>${{ number_format($expectedValue, 2) }}</strong>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Expected Close:</td>
                        <td>{{ $opportunity->expected_close_date ? $opportunity->expected_close_date->format('M d, Y') : 'N/A' }}</td>
                    </tr>
                </table>

                <!-- Stage Action Buttons -->
                @if($opportunity->status === 'OPEN')
                <div class="mt-3">
                    <h6 class="fw-bold mb-2">Update Stage</h6>
                    <div class="d-flex flex-wrap gap-1">
                        @foreach(['NEW' => 'New', 'QUALIFIED' => 'Qualified', 'PROPOSAL' => 'Proposal', 'NEGOTIATION' => 'Negotiation', 'WON' => 'Won', 'LOST' => 'Lost'] as $stage => $label)
                        @if($opportunity->stage != $stage)
                            <button type="button" class="btn btn-outline-@if($stage == 'WON')success@elseif($stage == 'LOST')danger@else primary @endif btn-sm" 
                                    onclick="updateStage('{{ $stage }}')">
                                {{ $label }}
                            </button>
                        @endif
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Forms for stage update -->
                @foreach(['WON', 'LOST'] as $stage)
                <form id="stageForm{{ $stage }}" action="{{ route('crm.opportunities.updateStage', $opportunity) }}" method="POST" style="display: none;">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="stage" value="{{ $stage }}">
                    @if($stage === 'LOST')
                        <input type="hidden" name="lost_reason" value="Customer decision">
                    @endif
                </form>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Details & Activities -->
    <div class="col-lg-8">
        <!-- Opportunity Details -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Opportunity Details</h6>
                @if($opportunity->description)
                <div class="p-3 bg-light rounded">
                    {{ $opportunity->description }}
                </div>
                @else
                <p class="text-muted text-center py-3 mb-0">No description provided</p>
                @endif
            </div>
        </div>

        <!-- Follow-ups -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0">Follow-ups ({{ isset($opportunity->followUps) ? $opportunity->followUps->count() : 0 }})</h6>
                    <a href="{{ route('crm.follow-ups.create', ['opportunity_id' => $opportunity->id]) }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Add Follow-up
                    </a>
                </div>

                @if(isset($opportunity->followUps) && $opportunity->followUps->count() > 0)
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($opportunity->followUps as $followUp)
                            <tr>
                                <td>{{ $followUp->title }}</td>
                                <td>{{ $followUp->follow_up_date->format('M d, Y') }}</td>
                                <td>
                                    @if($followUp->status === 'COMPLETED')
                                        <span class="badge bg-success">Completed</span>
                                    @elseif($followUp->status === 'PENDING')
                                        <span class="badge bg-warning text-dark">Pending</span>
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
                    <p class="mb-0">No follow-ups yet</p>
                    <!-- Removed the duplicate button here, keeping only the icon and text -->
                </div>
                @endif
            </div>
        </div>

        <!-- Sales -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Sales ({{ isset($opportunity->sales) ? $opportunity->sales->count() : 0 }})</h6>
                @if(isset($opportunity->sales) && $opportunity->sales->count() > 0)
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Sale #</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Payment Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($opportunity->sales as $sale)
                            <tr>
                                <td><strong>{{ $sale->sale_number }}</strong></td>
                                <td>{{ $sale->sale_date->format('M d, Y') }}</td>
                                <td><strong>${{ number_format($sale->final_amount, 2) }}</strong></td>
                                <td>
                                    @if($sale->payment_status === 'PAID')
                                        <span class="badge bg-success">Paid</span>
                                    @elseif($sale->payment_status === 'PENDING')
                                        <span class="badge bg-warning text-dark">Pending</span>
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
                    <p class="mb-2">No sales yet</p>
                    @if($opportunity->stage === 'WON')
                    <a href="{{ route('crm.sales.create', ['opportunity_id' => $opportunity->id]) }}" class="btn btn-sm btn-success">
                        <i class="bi bi-plus-circle me-1"></i>Create Sale from Opportunity
                    </a>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function updateStage(stage) {
    document.getElementById('stageForm' + stage).submit();
}
</script>
@endpush
@endsection
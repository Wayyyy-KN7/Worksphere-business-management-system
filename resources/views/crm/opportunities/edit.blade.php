@extends('layouts.app')

@section('title', 'Edit Opportunity')
@section('breadcrumb', 'CRM / Opportunities / Edit')

@section('content')
<div class="page-header">
    <h2><i class="bi bi-pencil me-2"></i>Edit Opportunity</h2>
    <p>{{ $opportunity->opportunity_number }} - {{ $opportunity->title }}</p>
</div>

<div class="row">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="{{ route('crm.opportunities.update', $opportunity) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <!-- Opportunity Number (Read-only) -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Opportunity Number</label>
                            <input type="text" class="form-control bg-light" 
                                   value="{{ $opportunity->opportunity_number }}" readonly>
                            <small class="text-muted">
                                <i class="bi bi-lock me-1"></i> Cannot be changed
                            </small>
                        </div>

                        <!-- Customer -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" class="form-select @error('customer_id') is-invalid @enderror" required>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}" 
                                            {{ old('customer_id', $opportunity->customer_id) == $customer->id ? 'selected' : '' }}>
                                        {{ $customer->customer_code }} - {{ $customer->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('customer_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Title -->
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Opportunity Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" 
                                   value="{{ old('title', $opportunity->title) }}" required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Description -->
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $opportunity->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Estimated Value -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Estimated Value ($) <span class="text-danger">*</span></label>
                            <input type="number" name="estimated_value" step="0.01" min="0" class="form-control @error('estimated_value') is-invalid @enderror" 
                                   value="{{ old('estimated_value', $opportunity->estimated_value) }}" required>
                            @error('estimated_value')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Probability -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Probability (%) <span class="text-danger">*</span></label>
                            <input type="number" name="probability" min="0" max="100" class="form-control @error('probability') is-invalid @enderror" 
                                   value="{{ old('probability', $opportunity->probability) }}" required>
                            @error('probability')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Expected Close Date -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Expected Close Date <span class="text-danger">*</span></label>
                            <input type="date" name="expected_close_date" class="form-control @error('expected_close_date') is-invalid @enderror" 
                                   value="{{ old('expected_close_date', $opportunity->expected_close_date?->format('Y-m-d')) }}" required>
                            @error('expected_close_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Actual Close Date -->
                        @if($opportunity->actual_close_date)
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Actual Close Date</label>
                            <input type="text" class="form-control bg-light" 
                                   value="{{ $opportunity->actual_close_date->format('M d, Y') }}" readonly>
                        </div>
                        @endif

                        <!-- Stage -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Stage <span class="text-danger">*</span></label>
                            <select name="stage" class="form-select @error('stage') is-invalid @enderror" required>
                                <option value="NEW" {{ old('stage', $opportunity->stage) === 'NEW' ? 'selected' : '' }}>New</option>
                                <option value="QUALIFIED" {{ old('stage', $opportunity->stage) === 'QUALIFIED' ? 'selected' : '' }}>Qualified</option>
                                <option value="PROPOSAL" {{ old('stage', $opportunity->stage) === 'PROPOSAL' ? 'selected' : '' }}>Proposal</option>
                                <option value="NEGOTIATION" {{ old('stage', $opportunity->stage) === 'NEGOTIATION' ? 'selected' : '' }}>Negotiation</option>
                                <option value="WON" {{ old('stage', $opportunity->stage) === 'WON' ? 'selected' : '' }}>Won</option>
                                <option value="LOST" {{ old('stage', $opportunity->stage) === 'LOST' ? 'selected' : '' }}>Lost</option>
                            </select>
                            @error('stage')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Status -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                <option value="OPEN" {{ old('status', $opportunity->status) === 'OPEN' ? 'selected' : '' }}>Open</option>
                                <option value="CLOSED" {{ old('status', $opportunity->status) === 'CLOSED' ? 'selected' : '' }}>Closed</option>
                                <option value="CANCELLED" {{ old('status', $opportunity->status) === 'CANCELLED' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Assigned To -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Assign To <span class="text-danger">*</span></label>
                            <select name="assigned_to" class="form-select @error('assigned_to') is-invalid @enderror" required>
                                @foreach($assignableUsers as $user)
                                    <option value="{{ $user->id }}" 
                                            {{ old('assigned_to', $opportunity->assigned_to) == $user->id ? 'selected' : '' }}>
                                        {{ $user->name }} - {{ $user->department->name ?? '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('assigned_to')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Lost Reason -->
                        @if($opportunity->lost_reason || old('stage') === 'LOST')
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Lost Reason</label>
                            <textarea name="lost_reason" rows="3" class="form-control @error('lost_reason') is-invalid @enderror">{{ old('lost_reason', $opportunity->lost_reason) }}</textarea>
                            @error('lost_reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Update Opportunity
                        </button>
                        <a href="{{ route('crm.opportunities.show', $opportunity) }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
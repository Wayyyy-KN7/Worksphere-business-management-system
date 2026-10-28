@extends('layouts.app')

@section('title', 'Add Opportunity')
@section('breadcrumb', 'CRM / Opportunities / Create')

@section('content')
<div class="page-header mb-4">
    <h2><i class="bi bi-funnel me-2"></i>Add Opportunity</h2>
    <p class="text-muted">Create a new sales opportunity in the pipeline</p>
</div>

<div class="row">
    <!-- Main Form Column -->
    <div class="col-lg-10 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="{{ route('crm.opportunities.store') }}" method="POST">
                    @csrf

                    <div class="row g-3">
                        <!-- Opportunity Number (Auto-generated, Display Only) -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Opportunity Number</label>
                            <input type="text" class="form-control bg-light" value="AUTO-GENERATED" readonly>
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>Generated automatically upon saving
                            </small>
                        </div>

                        <!-- Customer Selection -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" class="form-select @error('customer_id') is-invalid @enderror" required>
                                <option value="">Select Customer</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                                        {{ $customer->name }} @if(isset($customer->company_name)) - {{ $customer->company_name }} @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('customer_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Opportunity Title -->
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Opportunity Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" 
                                   value="{{ old('title') }}" placeholder="e.g., Enterprise Software Implementation Deal" required autofocus>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Description -->
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror" 
                                      placeholder="Provide scope notes or details about the opportunity...">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Estimated Value -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Estimated Value ($) <span class="text-danger">*</span></label>
                            <input type="number" name="estimated_value" step="0.01" min="0" class="form-control @error('estimated_value') is-invalid @enderror" 
                                   value="{{ old('estimated_value', 0) }}" placeholder="0.00" required>
                            @error('estimated_value')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Probability -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Probability (%) <span class="text-danger">*</span></label>
                            <input type="number" name="probability" min="0" max="100" class="form-control @error('probability') is-invalid @enderror" 
                                   value="{{ old('probability', 50) }}" required>
                            @error('probability')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Likelihood of closing this deal (0-100)</small>
                        </div>

                        <!-- Expected Close Date -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Expected Close Date <span class="text-danger">*</span></label>
                            <input type="date" name="expected_close_date" class="form-control @error('expected_close_date') is-invalid @enderror" 
                                   value="{{ old('expected_close_date', now()->addMonth()->format('Y-m-d')) }}" required>
                            @error('expected_close_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Pipeline Stage -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Stage <span class="text-danger">*</span></label>
                            <select name="stage" class="form-select @error('stage') is-invalid @enderror" required>
                                <option value="NEW" {{ old('stage', 'NEW') === 'NEW' ? 'selected' : '' }}>New</option>
                                <option value="QUALIFIED" {{ old('stage') === 'QUALIFIED' ? 'selected' : '' }}>Qualified</option>
                                <option value="PROPOSAL" {{ old('stage') === 'PROPOSAL' ? 'selected' : '' }}>Proposal</option>
                                <option value="NEGOTIATION" {{ old('stage') === 'NEGOTIATION' ? 'selected' : '' }}>Negotiation</option>
                                <option value="WON" {{ old('stage') === 'WON' ? 'selected' : '' }}>Won</option>
                                <option value="LOST" {{ old('stage') === 'LOST' ? 'selected' : '' }}>Lost</option>
                            </select>
                            @error('stage')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Status -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                <option value="OPEN" {{ old('status', 'OPEN') === 'OPEN' ? 'selected' : '' }}>Open</option>
                                <option value="CLOSED" {{ old('status', 'CLOSED') === 'CLOSED' ? 'selected' : '' }}>Closed</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Assigned User -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Assign To <span class="text-danger">*</span></label>
                            <select name="assigned_to" class="form-select @error('assigned_to') is-invalid @enderror" required>
                                <option value="">Select Sales Representative</option>
                                @foreach($assignableUsers as $user)
                                    <option value="{{ $user->id }}" {{ old('assigned_to') == $user->id ? 'selected' : '' }}>
                                        {{ $user->name }} @if(isset($user->department)) ({{ $user->department->name }}) @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('assigned_to')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Form Action Buttons -->
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Create Opportunity
                        </button>
                        <a href="{{ route('crm.opportunities.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sidebar Guide Column -->
    <div class="col-lg-2">
        <div class="card border-0 shadow-sm bg-light">
            <div class="card-body">
                <h6 class="fw-bold mb-3">
                    <i class="bi bi-info-circle me-2"></i>Stages Guide
                </h6>
                <ul class="list-unstyled small mb-0 d-flex flex-column gap-2">
                    <li><span class="badge bg-secondary w-100 text-start py-2">NEW: Initial inquiry</span></li>
                    <li><span class="badge bg-info w-100 text-start py-2">QUALIFIED: Verified need</span></li>
                    <li><span class="badge bg-primary w-100 text-start py-2">PROPOSAL: Quote sent</span></li>
                    <li><span class="badge bg-warning text-dark w-100 text-start py-2">NEGOTIATION: Terms</span></li>
                    <li><span class="badge bg-success w-100 text-start py-2">WON: Deal closed</span></li>
                    <li><span class="badge bg-danger w-100 text-start py-2">LOST: Deal dropped</span></li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
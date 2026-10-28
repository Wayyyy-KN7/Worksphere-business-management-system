@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h4>Create New Follow-Up</h4>
                </div>
                <div class="card-body">
                   <form method="POST" action="{{ route('crm.follow-ups.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Customer</label>
                            <select name="customer_id" id="customerSelect" class="form-select @error('customer_id') is-invalid @enderror">
                                <option value="">Select Customer</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}" {{ (old('customer_id', $selectedCustomer?->id) == $customer->id) ? 'selected' : '' }}>
                                        {{ $customer->name }} - {{ $customer->company_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('customer_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Opportunity</label>
                            <select name="opportunity_id" id="opportunitySelect" class="form-select @error('opportunity_id') is-invalid @enderror">
                                <option value="">Select Opportunity</option>
                                @foreach($opportunities as $opportunity)
                                    <option value="{{ $opportunity->id }}" data-customer-id="{{ $opportunity->customer_id }}" {{ (old('opportunity_id', $selectedOpportunity?->id) == $opportunity->id) ? 'selected' : '' }}>
                                        {{ $opportunity->opportunity_number }} - {{ $opportunity->title }}
                                    </option>
                                @endforeach
                            </select>
                            @error('opportunity_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" 
                                   value="{{ old('title') }}" required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Due Date *</label>
                                <input type="date" name="follow_up_date" 
                                       class="form-control @error('follow_up_date') is-invalid @enderror" 
                                       value="{{ old('follow_up_date') }}" required>
                                @error('follow_up_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Due Time</label>
                                <input type="time" name="follow_up_time" class="form-control" 
                                       value="{{ old('follow_up_time') }}">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Type *</label>
                                <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                                    <option value="CALL" {{ old('type') === 'CALL' ? 'selected' : '' }}>Call</option>
                                    <option value="EMAIL" {{ old('type') === 'EMAIL' ? 'selected' : '' }}>Email</option>
                                    <option value="MEETING" {{ old('type') === 'MEETING' ? 'selected' : '' }}>Meeting</option>
                                    <option value="VISIT" {{ old('type') === 'VISIT' ? 'selected' : '' }}>Visit</option>
                                    <option value="QUOTATION" {{ old('type') === 'QUOTATION' ? 'selected' : '' }}>Send quotation</option>
                                    <option value="PAYMENT" {{ old('type') === 'PAYMENT' ? 'selected' : '' }}>Payment follow-up</option>
                                    <option value="PROPOSAL" {{ old('type') === 'PROPOSAL' ? 'selected' : '' }}>Proposal follow-up</option>
                                    <option value="OTHER" {{ old('type') === 'OTHER' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority *</label>
                                <select name="priority" class="form-select" required>
                                    @foreach(['LOW' => 'Low', 'NORMAL' => 'Normal', 'HIGH' => 'High', 'URGENT' => 'Urgent'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('priority', 'NORMAL') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        @if(auth()->user()->isManager())
                            <div class="mb-3">
                                <label class="form-label">Assign To</label>
                                <select name="assigned_to" class="form-select @error('assigned_to') is-invalid @enderror">
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" {{ old('assigned_to', auth()->id()) == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                                @error('assigned_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @endif

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Repeat</label>
                                <select name="recurrence_rule" class="form-select">
                                    <option value="NONE">Does not repeat</option>
                                    <option value="DAILY" {{ old('recurrence_rule') === 'DAILY' ? 'selected' : '' }}>Every day</option>
                                    <option value="WEEKLY" {{ old('recurrence_rule') === 'WEEKLY' ? 'selected' : '' }}>Every week</option>
                                    <option value="MONTHLY" {{ old('recurrence_rule') === 'MONTHLY' ? 'selected' : '' }}>Every month</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Repeat Until</label>
                                <input type="date" name="recurrence_until" class="form-control" value="{{ old('recurrence_until') }}">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('crm.follow-ups.index') }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Create Follow-Up</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const customerSelect = document.getElementById('customerSelect');
    const opportunitySelect = document.getElementById('opportunitySelect');

    opportunitySelect.addEventListener('change', function () {
        const selectedOption = this.options[this.selectedIndex];
        const customerId = selectedOption?.dataset.customerId;

        if (customerId) {
            customerSelect.value = customerId;
        }
    });
});
</script>
@endpush
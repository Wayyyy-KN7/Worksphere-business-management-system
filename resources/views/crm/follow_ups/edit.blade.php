@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h4>Edit Follow-Up</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('crm.follow-ups.update', $followUp) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label">Customer</label>
                            <select name="customer_id" class="form-select @error('customer_id') is-invalid @enderror">
                                <option value="">Select Customer (Optional)</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}" 
                                        {{ old('customer_id', $followUp->customer_id) == $customer->id ? 'selected' : '' }}>
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
                            <select name="opportunity_id" class="form-select @error('opportunity_id') is-invalid @enderror">
                                <option value="">Select Opportunity (Optional)</option>
                                @foreach($opportunities as $opportunity)
                                    <option value="{{ $opportunity->id }}" 
                                        {{ old('opportunity_id', $followUp->opportunity_id) == $opportunity->id ? 'selected' : '' }}>
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
                                   value="{{ old('title', $followUp->title) }}" required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3">{{ old('description', $followUp->description) }}</textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Follow-Up Date *</label>
                                <input type="date" name="follow_up_date" 
                                       class="form-control @error('follow_up_date') is-invalid @enderror" 
                                       value="{{ old('follow_up_date', $followUp->follow_up_date->format('Y-m-d')) }}" required>
                                @error('follow_up_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Follow-Up Time</label>
                                <input type="time" name="follow_up_time" class="form-control" 
                                       value="{{ old('follow_up_time', $followUp->follow_up_time) }}">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Type *</label>
                                <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                                    <option value="CALL" {{ old('type', $followUp->type) === 'CALL' ? 'selected' : '' }}>Call</option>
                                    <option value="EMAIL" {{ old('type', $followUp->type) === 'EMAIL' ? 'selected' : '' }}>Email</option>
                                    <option value="MEETING" {{ old('type', $followUp->type) === 'MEETING' ? 'selected' : '' }}>Meeting</option>
                                    <option value="VISIT" {{ old('type', $followUp->type) === 'VISIT' ? 'selected' : '' }}>Visit</option>
                                    <option value="QUOTATION" {{ old('type', $followUp->type) === 'QUOTATION' ? 'selected' : '' }}>Send quotation</option>
                                    <option value="PAYMENT" {{ old('type', $followUp->type) === 'PAYMENT' ? 'selected' : '' }}>Payment follow-up</option>
                                    <option value="PROPOSAL" {{ old('type', $followUp->type) === 'PROPOSAL' ? 'selected' : '' }}>Proposal follow-up</option>
                                    <option value="OTHER" {{ old('type', $followUp->type) === 'OTHER' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority *</label>
                                <select name="priority" class="form-select" required>
                                    @foreach(['LOW' => 'Low', 'NORMAL' => 'Normal', 'HIGH' => 'High', 'URGENT' => 'Urgent'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('priority', $followUp->priority) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Repeat</label>
                                <select name="recurrence_rule" class="form-select">
                                    @foreach(['NONE' => 'Does not repeat', 'DAILY' => 'Every day', 'WEEKLY' => 'Every week', 'MONTHLY' => 'Every month'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('recurrence_rule', $followUp->recurrence_rule) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Repeat Until</label>
                                <input type="date" name="recurrence_until" class="form-control" value="{{ old('recurrence_until', $followUp->recurrence_until?->format('Y-m-d')) }}">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Assign To *</label>
                            <select name="assigned_to" class="form-select @error('assigned_to') is-invalid @enderror" required>
                                <option value="">Select User</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" 
                                        {{ old('assigned_to', $followUp->assigned_to) == $user->id ? 'selected' : '' }}>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('assigned_to')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('crm.follow-ups.index') }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Follow-Up</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
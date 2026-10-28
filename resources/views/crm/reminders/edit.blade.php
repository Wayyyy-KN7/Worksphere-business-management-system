@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header"><h4>Edit Reminder</h4></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('crm.reminders.update', $reminder) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $reminder->title) }}" required>
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3">{{ old('description', $reminder->description) }}</textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Customer (Optional)</label>
                                <select name="customer_id" class="form-select @error('customer_id') is-invalid @enderror">
                                    <option value="">Select Customer</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" {{ old('customer_id', $reminder->customer_id) == $customer->id ? 'selected' : '' }}>{{ $customer->name }} - {{ $customer->company_name }}</option>
                                    @endforeach
                                </select>
                                @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Opportunity (Optional)</label>
                                <select name="opportunity_id" class="form-select @error('opportunity_id') is-invalid @enderror">
                                    <option value="">Select Opportunity</option>
                                    @foreach($opportunities as $opportunity)
                                        <option value="{{ $opportunity->id }}" {{ old('opportunity_id', $reminder->opportunity_id) == $opportunity->id ? 'selected' : '' }}>{{ $opportunity->title }}</option>
                                    @endforeach
                                </select>
                                @error('opportunity_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Reminder Date *</label>
                                <input type="date" name="reminder_date" class="form-control @error('reminder_date') is-invalid @enderror" value="{{ old('reminder_date', $reminder->reminder_date->format('Y-m-d')) }}" required>
                                @error('reminder_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Reminder Time</label>
                                <input type="time" name="reminder_time" class="form-control" value="{{ old('reminder_time', $reminder->reminder_time) }}">
                            </div>
                        </div>
                        <p class="text-muted">Status: <span class="badge bg-secondary">{{ $reminder->status }}</span>. Use reminder actions to complete, snooze, or cancel it.</p>
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('crm.reminders.index') }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Reminder</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

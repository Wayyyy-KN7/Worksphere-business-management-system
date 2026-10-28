@extends('layouts.app')

@section('title', 'Edit Customer')
@section('breadcrumb', 'CRM / Customers / Edit')

@section('content')
<div class="page-header">
    <h2><i class="bi bi-pencil me-2"></i>Edit Customer</h2>
    <p>{{ $customer->customer_code }} - {{ $customer->name }}</p>
</div>

<div class="row">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="{{ route('crm.customers.update', $customer) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <!-- Customer Code (Read-only) -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Customer Code</label>
                            <input type="text" class="form-control bg-light" 
                                   value="{{ $customer->customer_code }}" readonly>
                            <small class="text-muted">
                                <i class="bi bi-lock"></i> Cannot be changed
                            </small>
                        </div>

                        <!-- Customer Name -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" 
                                   class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name', $customer->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Company Name -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Company Name</label>
                            <input type="text" name="company_name" 
                                   class="form-control @error('company_name') is-invalid @enderror" 
                                   value="{{ old('company_name', $customer->company_name) }}">
                            @error('company_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Email -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" 
                                   class="form-control @error('email') is-invalid @enderror" 
                                   value="{{ old('email', $customer->email) }}">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Phone -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" name="phone" 
                                   class="form-control @error('phone') is-invalid @enderror" 
                                   value="{{ old('phone', $customer->phone) }}">
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Customer Type -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Customer Type</label>
                            <select name="customer_type" class="form-select @error('customer_type') is-invalid @enderror">
                                <option value="">Select Type</option>
                                <option value="Individual" {{ old('customer_type', $customer->customer_type) == 'Individual' ? 'selected' : '' }}>Individual</option>
                                <option value="Business" {{ old('customer_type', $customer->customer_type) == 'Business' ? 'selected' : '' }}>Business</option>
                                <option value="Corporate" {{ old('customer_type', $customer->customer_type) == 'Corporate' ? 'selected' : '' }}>Corporate</option>
                                <option value="Government" {{ old('customer_type', $customer->customer_type) == 'Government' ? 'selected' : '' }}>Government</option>
                            </select>
                            @error('customer_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Source -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Source</label>
                            <select name="source_id" class="form-select @error('source_id') is-invalid @enderror">
                                <option value="">Select Source</option>
                                @foreach($sources as $source)
                                    <option value="{{ $source->id }}" {{ old('source_id', $customer->source_id) == $source->id ? 'selected' : '' }}>
                                        {{ $source->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('source_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Address -->
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Address</label>
                            <textarea name="address" rows="2" 
                                      class="form-control @error('address') is-invalid @enderror">{{ old('address', $customer->address) }}</textarea>
                            @error('address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- City -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">City</label>
                            <input type="text" name="city" 
                                   class="form-control @error('city') is-invalid @enderror" 
                                   value="{{ old('city', $customer->city) }}">
                            @error('city')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Country -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Country</label>
                            <input type="text" name="country" 
                                   class="form-control @error('country') is-invalid @enderror" 
                                   value="{{ old('country', $customer->country) }}">
                            @error('country')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Assignment -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Assigned Department</label>
                            <select name="assigned_department_id" class="form-select @error('assigned_department_id') is-invalid @enderror">
                                <option value="">No department</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}" {{ old('assigned_department_id', $customer->assigned_department_id) == $department->id ? 'selected' : '' }}>
                                        {{ $department->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('assigned_department_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Assigned Salesperson</label>
                            <select name="assigned_to" class="form-select @error('assigned_to') is-invalid @enderror">
                                <option value="">Department only / Unassigned</option>
                                @foreach($assignableUsers as $user)
                                    <option value="{{ $user->id }}" {{ old('assigned_to', $customer->assigned_to) == $user->id ? 'selected' : '' }}>
                                        {{ $user->name }} - {{ $user->department->name ?? '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('assigned_to')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Status -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                <option value="LEAD" {{ old('status', $customer->status) === 'LEAD' ? 'selected' : '' }}>Lead</option>
                                <option value="PROSPECT" {{ old('status', $customer->status) === 'PROSPECT' ? 'selected' : '' }}>Prospect</option>
                                <option value="ACTIVE" {{ old('status', $customer->status) === 'ACTIVE' ? 'selected' : '' }}>Active</option>
                                <option value="INACTIVE" {{ old('status', $customer->status) === 'INACTIVE' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Notes -->
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" rows="3" 
                                      class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $customer->notes) }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Update Customer
                        </button>
                        <a href="{{ route('crm.customers.show', $customer) }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-warning text-white">
                    <h4 class="mb-0"><i class="bi bi-pencil"></i> Edit Document</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('documents.update', $document) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label">Document Number</label>
                            <input type="text" class="form-control" value="{{ $document->document_number }}" disabled>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Document Title *</label>
                            <input type="text" name="title" 
                                   class="form-control @error('title') is-invalid @enderror" 
                                   value="{{ old('title', $document->title) }}" required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3">{{ old('description', $document->description) }}</textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Document Type *</label>
                                <select name="document_type" 
                                        class="form-select @error('document_type') is-invalid @enderror" required>
                                    <option value="CONTRACT" {{ old('document_type', $document->document_type) === 'CONTRACT' ? 'selected' : '' }}>Contract</option>
                                    <option value="POLICY" {{ old('document_type', $document->document_type) === 'POLICY' ? 'selected' : '' }}>Policy</option>
                                    <option value="REPORT" {{ old('document_type', $document->document_type) === 'REPORT' ? 'selected' : '' }}>Report</option>
                                    <option value="PROPOSAL" {{ old('document_type', $document->document_type) === 'PROPOSAL' ? 'selected' : '' }}>Proposal</option>
                                    <option value="INVOICE" {{ old('document_type', $document->document_type) === 'INVOICE' ? 'selected' : '' }}>Invoice</option>
                                    <option value="AGREEMENT" {{ old('document_type', $document->document_type) === 'AGREEMENT' ? 'selected' : '' }}>Agreement</option>
                                    <option value="MANUAL" {{ old('document_type', $document->document_type) === 'MANUAL' ? 'selected' : '' }}>Manual</option>
                                    <option value="OTHER" {{ old('document_type', $document->document_type) === 'OTHER' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('document_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                @if(auth()->user()->isAdmin())
                                    <select name="department_id" class="form-select">
                                        <option value="">No Department</option>
                                        @foreach($departments as $department)
                                            <option value="{{ $department->id }}" 
                                                {{ old('department_id', $document->department_id) == $department->id ? 'selected' : '' }}>
                                                {{ $department->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="hidden" name="department_id" value="{{ auth()->user()->department_id }}">
                                    <input type="text" class="form-control" value="{{ auth()->user()->department?->name ?? 'Unassigned' }}" readonly>
                                @endif
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Access Level *</label>
                            <select name="access_level" 
                                    class="form-select @error('access_level') is-invalid @enderror" required>
                                <option value="PRIVATE" {{ old('access_level', $document->access_level) === 'PRIVATE' ? 'selected' : '' }}>Private - Only me</option>
                                <option value="DEPARTMENT" {{ old('access_level', $document->access_level) === 'DEPARTMENT' ? 'selected' : '' }}>Department - My department members</option>
                                <option value="SELECTED_USERS" {{ old('access_level', $document->access_level) === 'SELECTED_USERS' ? 'selected' : '' }}>Selected Users - Specific users only</option>
                                <option value="COMPANY_WIDE" {{ old('access_level', $document->access_level) === 'COMPANY_WIDE' ? 'selected' : '' }}>Company-Wide - All employees</option>
                            </select>
                            @error('access_level')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Current Status</label>
                            <input type="text" class="form-control" value="{{ str_replace('_', ' ', $document->status) }}" readonly>
                            <div class="form-text">Status changes through the submit, approval, and rejection workflow.</div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('documents.show', $document) }}" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-save"></i> Update Document
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
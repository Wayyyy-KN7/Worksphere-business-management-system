@extends('layouts.app')

@section('title', 'Upload Document')

@push('styles')
<style>
    .document-upload-page { max-width: 760px; }
    .document-upload-page .card-header { padding: 11px 16px; }
    .document-upload-page .card-header h4 { font-size: 1rem; font-weight: 650; }
    .document-upload-page .card-body { padding: 16px; }
    .document-upload-page form > .mb-3,
    .document-upload-page form > .row { margin-bottom: 0.75rem !important; }
    .document-upload-page .form-label { margin-bottom: 0.3rem; }
    .document-upload-page .form-control,
    .document-upload-page .form-select { min-height: 36px; padding-top: 0.35rem; padding-bottom: 0.35rem; }
    .document-upload-page textarea.form-control { min-height: 52px; }
    .document-upload-page .upload-note { padding: 0.75rem; font-size: 0.875rem; }
    .document-upload-page .upload-note { height: calc(100% - 1rem); margin-bottom: 1rem; }

    @media (max-width: 575px) {
        .document-upload-page .card-body { padding: 14px; }
        .document-upload-page .upload-actions { gap: 10px; }
        .document-upload-page .upload-actions .btn { flex: 1; }
    }
</style>
@endpush

@section('content')
<div class="container document-upload-page">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h4 class="mb-0"><i class="bi bi-upload text-primary me-2"></i>Upload New Document</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data">
                        @csrf

                        <!-- Title -->
                        <div class="mb-3">
                            <label class="form-label">Document Title *</label>
                            <input type="text" name="title" 
                                   class="form-control @error('title') is-invalid @enderror" 
                                   value="{{ old('title') }}" required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Description -->
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                            <small class="text-muted">Optional: Brief description of the document</small>
                        </div>

                        <div class="row">
                            <!-- Document Type -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Document Type *</label>
                                <select name="document_type" 
                                        class="form-select @error('document_type') is-invalid @enderror" required>
                                    <option value="">Select Type</option>
                                    <option value="CONTRACT" {{ old('document_type') === 'CONTRACT' ? 'selected' : '' }}>Contract</option>
                                    <option value="POLICY" {{ old('document_type') === 'POLICY' ? 'selected' : '' }}>Policy</option>
                                    <option value="REPORT" {{ old('document_type') === 'REPORT' ? 'selected' : '' }}>Report</option>
                                    <option value="PROPOSAL" {{ old('document_type') === 'PROPOSAL' ? 'selected' : '' }}>Proposal</option>
                                    <option value="INVOICE" {{ old('document_type') === 'INVOICE' ? 'selected' : '' }}>Invoice</option>
                                    <option value="AGREEMENT" {{ old('document_type') === 'AGREEMENT' ? 'selected' : '' }}>Agreement</option>
                                    <option value="MANUAL" {{ old('document_type') === 'MANUAL' ? 'selected' : '' }}>Manual</option>
                                    <option value="OTHER" {{ old('document_type') === 'OTHER' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('document_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Department -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                @if(auth()->user()->isAdmin())
                                    <select name="department_id" class="form-select">
                                        <option value="">No Department</option>
                                        @foreach($departments as $department)
                                            <option value="{{ $department->id }}" 
                                                {{ old('department_id', auth()->user()->department_id) == $department->id ? 'selected' : '' }}>
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

                        <div class="row">
                            <!-- Access Level -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Access Level *</label>
                                <select id="accessLevel" name="access_level" 
                                    class="form-select @error('access_level') is-invalid @enderror" required>
                                <option value="PRIVATE" {{ old('access_level') === 'PRIVATE' ? 'selected' : '' }}>
                                    Private - Owner and authorized administrators/managers
                                </option>
                                <option value="DEPARTMENT" {{ old('access_level') === 'DEPARTMENT' ? 'selected' : '' }}>
                                    Department - Authorized users in my department
                                </option>
                                <option value="SELECTED_USERS" {{ old('access_level') === 'SELECTED_USERS' ? 'selected' : '' }}>
                                    Specific Users - Selected recipients only
                                </option>
                                <option value="COMPANY_WIDE" {{ old('access_level') === 'COMPANY_WIDE' ? 'selected' : '' }}>
                                    Public - All authorized CRM users
                                </option>
                            </select>
                            @error('access_level')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            </div>

                            <!-- File Upload -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Upload File *</label>
                                <input type="file" name="file"
                                       class="form-control @error('file') is-invalid @enderror"
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png" required>
                                <small class="text-muted">PDF, Word, Excel, or image. Max size: 10MB</small>
                                @error('file')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3 {{ old('access_level') === 'SELECTED_USERS' ? '' : 'd-none' }}" id="selectedUsersField">
                            <label class="form-label" for="user_ids">Specific users *</label>
                            <select id="user_ids" name="user_ids[]" class="form-select @error('user_ids') is-invalid @enderror" multiple size="5">
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ in_array($user->id, array_map('intval', (array) old('user_ids', [])), true) ? 'selected' : '' }}>
                                        {{ $user->name }} · {{ $user->department->name ?? 'No department' }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Choose one or more active users. Recipients are notified and gain access only after publication.</small>
                            @error('user_ids')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            @error('user_ids.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div class="row align-items-stretch">
                            <!-- Change Description -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Change Description</label>
                                <textarea name="change_description" class="form-control" rows="2">{{ old('change_description', 'Initial upload') }}</textarea>
                                <small class="text-muted">Describe what's in this document or version</small>
                            </div>

                            <!-- Alert Info -->
                            <div class="col-md-6">
                                <div class="alert alert-info upload-note">
                                    <i class="bi bi-info-circle me-1"></i>
                                    @if(auth()->user()->isAdmin() || str_ends_with(auth()->user()->role, '_MANAGER'))
                                        <strong>Note:</strong> Your upload will be published immediately according to your permissions. Specific recipients will be notified after publication.
                                    @else
                                        <strong>Note:</strong> Your upload remains private until your department manager approves it. Specific recipients receive access and a notification after approval.
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="d-flex justify-content-between upload-actions">
                            <a href="{{ route('documents.index') }}" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-upload"></i> Upload Document
                            </button>
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
document.addEventListener('DOMContentLoaded', () => {
    const accessLevel = document.getElementById('accessLevel');
    const selectedUsersField = document.getElementById('selectedUsersField');
    const selectedUsers = document.getElementById('user_ids');

    const syncSelectedUsers = () => {
        const isSelectedUsers = accessLevel.value === 'SELECTED_USERS';
        selectedUsersField.classList.toggle('d-none', !isSelectedUsers);
        selectedUsers.required = isSelectedUsers;
    };

    accessLevel.addEventListener('change', syncSelectedUsers);
    syncSelectedUsers();
});
</script>
@endpush
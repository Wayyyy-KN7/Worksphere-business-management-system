@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col">
            <h2><i class="bi bi-file-earmark-text"></i> Document Management</h2>
            <p class="text-muted">Manage company documents with version control and approvals</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('documents.create') }}" class="btn btn-primary">
                <i class="bi bi-upload"></i> Upload Document
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1">Total Documents</p>
                            <h3 class="mb-0">{{ $documentCountForUser }}</h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded">
                            <i class="bi bi-files fs-3 text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1">Pending Approval</p>
                            <h3 class="mb-0">{{ $pendingApprovalCount }}</h3>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-3 rounded">
                            <i class="bi bi-clock-history fs-3 text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1">My Documents</p>
                            <h3 class="mb-0">{{ $myDocumentCount }}</h3>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="bi bi-person-check fs-3 text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1">Shared With Me</p>
                            <h3 class="mb-0">{{ $sharedDocumentCount }}</h3>
                        </div>
                        <div class="bg-info bg-opacity-10 p-3 rounded">
                            <i class="bi bi-share fs-3 text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3 border-0 shadow-sm">
        <div class="card-body">
            <form method="GET" action="{{ route('documents.index') }}">
                <div class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search documents..." 
                               value="{{ request('search') }}">
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="DRAFT" {{ request('status') === 'DRAFT' ? 'selected' : '' }}>Draft</option>
                            <option value="PENDING_REVIEW" {{ request('status') === 'PENDING_REVIEW' ? 'selected' : '' }}>Pending Review</option>
                            <option value="APPROVED" {{ request('status') === 'APPROVED' ? 'selected' : '' }}>Approved</option>
                            <option value="REJECTED" {{ request('status') === 'REJECTED' ? 'selected' : '' }}>Rejected</option>
                            <option value="PUBLISHED" {{ request('status') === 'PUBLISHED' ? 'selected' : '' }}>Published</option>
                            <option value="ARCHIVED" {{ request('status') === 'ARCHIVED' ? 'selected' : '' }}>Archived</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="document_type" class="form-select">
                            <option value="">All Types</option>
                            <option value="CONTRACT" {{ request('document_type') === 'CONTRACT' ? 'selected' : '' }}>Contract</option>
                            <option value="POLICY" {{ request('document_type') === 'POLICY' ? 'selected' : '' }}>Policy</option>
                            <option value="REPORT" {{ request('document_type') === 'REPORT' ? 'selected' : '' }}>Report</option>
                            <option value="PROPOSAL" {{ request('document_type') === 'PROPOSAL' ? 'selected' : '' }}>Proposal</option>
                            <option value="INVOICE" {{ request('document_type') === 'INVOICE' ? 'selected' : '' }}>Invoice</option>
                            <option value="OTHER" {{ request('document_type') === 'OTHER' ? 'selected' : '' }}>Other</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="access_level" class="form-select">
                            <option value="">All Access Levels</option>
                            <option value="PRIVATE" {{ request('access_level') === 'PRIVATE' ? 'selected' : '' }}>Private</option>
                            <option value="DEPARTMENT" {{ request('access_level') === 'DEPARTMENT' ? 'selected' : '' }}>Department</option>
                            <option value="COMPANY_WIDE" {{ request('access_level') === 'COMPANY_WIDE' ? 'selected' : '' }}>Company-Wide</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-secondary">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                        <a href="{{ route('documents.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Documents List -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Document #</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Department</th>
                            <th>Version</th>
                            <th>Status</th>
                            <th>Access Level</th>
                            <th>Uploaded By</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documents as $document)
                        <tr>
                            <td>
                                <strong>{{ $document->document_number }}</strong>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-file-earmark-pdf fs-4 text-danger me-2"></i>
                                    <div>
                                        <a href="{{ route('documents.show', $document) }}" class="text-decoration-none">
                                            {{ $document->title }}
                                        </a>
                                        @if($document->description)
                                        <br><small class="text-muted">{{ Str::limit($document->description, 50) }}</small>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ $document->document_type }}</span>
                            </td>
                            <td>{{ $document->department->name ?? 'N/A' }}</td>
                            <td>
                                @if($document->latestVersion)
                                <span class="badge bg-info">v{{ $document->latestVersion->version_number }}</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $statusColors = [
                                        'DRAFT' => 'secondary',
                                        'PENDING_REVIEW' => 'warning',
                                        'APPROVED' => 'success',
                                        'REJECTED' => 'danger',
                                        'PUBLISHED' => 'primary',
                                        'ARCHIVED' => 'dark'
                                    ];
                                @endphp
                                <span class="badge bg-{{ $statusColors[$document->status] ?? 'secondary' }}">
                                    {{ str_replace('_', ' ', $document->status) }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $accessIcons = [
                                        'PRIVATE' => 'lock',
                                        'DEPARTMENT' => 'building',
                                        'SELECTED_USERS' => 'people',
                                        'COMPANY_WIDE' => 'globe'
                                    ];
                                @endphp
                                <i class="bi bi-{{ $accessIcons[$document->access_level] ?? 'lock' }}"></i>
                                {{ str_replace('_', ' ', $document->access_level) }}
                            </td>
                            <td>{{ $document->uploader->name }}</td>
                            <td>{{ $document->created_at->format('d M Y') }}</td>
                            <td>
                                <x-table-actions>
                                    <li><a class="dropdown-item" href="{{ route('documents.show', $document) }}"><i class="bi bi-eye"></i>View</a></li>
                                    @if($document->latestVersion && auth()->user()->can('download', $document))
                                    <li><a class="dropdown-item" href="{{ route('documents.versions.download', [$document, $document->latestVersion]) }}"><i class="bi bi-download"></i>Download</a></li>
                                    @endif
                                    
                                    @can('update', $document)
                                    <li><a class="dropdown-item" href="{{ route('documents.edit', $document) }}"><i class="bi bi-pencil"></i>Edit</a></li>
                                    @endcan
                                    
                                    @can('delete', $document)
                                    <li><form action="{{ route('documents.destroy', $document) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this document?')">@csrf @method('DELETE')<button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i>Delete</button></form></li>
                                    @endcan
                                </x-table-actions>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center py-4">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <p class="text-muted mt-2">No documents found</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $documents->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
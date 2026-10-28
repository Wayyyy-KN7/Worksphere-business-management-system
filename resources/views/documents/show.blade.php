@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('documents.index') }}">Documents</a></li>
                    <li class="breadcrumb-item active">{{ $document->document_number }}</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <!-- Document Info Card -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">{{ $document->title }}</h4>
                        <small class="text-muted">{{ $document->document_number }}</small>
                    </div>
                    <div>
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
                        <span class="badge bg-{{ $statusColors[$document->status] ?? 'secondary' }} fs-6">
                            {{ str_replace('_', ' ', $document->status) }}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#details" type="button">
                                <i class="bi bi-info-circle"></i> Details
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#versions" type="button">
                                <i class="bi bi-clock-history"></i> Versions ({{ $document->versions->count() }})
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#approvals" type="button">
                                <i class="bi bi-check-circle"></i> Approvals ({{ $document->approvals->count() }})
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#activity" type="button">
                                <i class="bi bi-activity"></i> Activity ({{ $document->activities->count() }})
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content">
                        <!-- Details Tab -->
                        <div class="tab-pane fade show active" id="details" role="tabpanel">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Document Type:</strong>
                                    <p><span class="badge bg-secondary">{{ $document->document_type }}</span></p>
                                </div>
                                <div class="col-md-6">
                                    <strong>Department:</strong>
                                    <p>{{ $document->department->name ?? 'None' }}</p>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <strong>Description:</strong>
                                    <p>{{ $document->description ?? 'No description provided' }}</p>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Access Level:</strong>
                                    <p>
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
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <strong>Uploaded By:</strong>
                                    <p>{{ $document->uploader->name }}</p>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Created:</strong>
                                    <p>{{ $document->created_at->format('d M Y H:i') }}</p>
                                </div>
                                <div class="col-md-6">
                                    <strong>Last Updated:</strong>
                                    <p>{{ $document->updated_at->format('d M Y H:i') }}</p>
                                </div>
                            </div>

                            @if($document->latestVersion)
                            <div class="alert alert-info">
                                <strong>Latest Version:</strong> v{{ $document->latestVersion->version_number }} 
                                ({{ $document->latestVersion->file_size_human }})
                                <br>
                                <small>{{ $document->latestVersion->change_description }}</small>
                            </div>
                            @endif

                            @if(auth()->user()->can('view', $document) && !auth()->user()->can('download', $document))
                            <div class="alert alert-info mb-0">
                                <strong>View only:</strong> This document can be opened here, but downloading is not allowed for your current access level.
                            </div>
                            @endif
                        </div>

                        <!-- Versions Tab -->
                        <div class="tab-pane fade" id="versions" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Version</th>
                                            <th>File Name</th>
                                            <th>Size</th>
                                            <th>Uploaded By</th>
                                            <th>Date</th>
                                            <th>Description</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($document->versions()->latest()->get() as $version)
                                        <tr>
                                            <td>
                                                <span class="badge bg-info">v{{ $version->version_number }}</span>
                                                @if($version->id === $document->latestVersion->id)
                                                <span class="badge bg-success">Latest</span>
                                                @endif
                                            </td>
                                            <td>{{ $version->file_name }}</td>
                                            <td>{{ $version->file_size_human }}</td>
                                            <td>{{ $version->uploader->name }}</td>
                                            <td>{{ $version->created_at->format('d M Y H:i') }}</td>
                                            <td>{{ Str::limit($version->change_description, 50) }}</td>
                                            <td>
                                                @can('view', $document)
                                                <x-table-actions>
                                                    <li><a class="dropdown-item" href="{{ route('documents.show', $document) }}"><i class="bi bi-eye"></i>View</a></li>
                                                    @can('download', $document)
                                                    <li><a class="dropdown-item" href="{{ route('documents.versions.download', [$document, $version]) }}"><i class="bi bi-download"></i>Download</a></li>
                                                    @endcan
                                                </x-table-actions>
                                                @endcan
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @can('update', $document)
                            <hr>
                            <h6>Upload New Version</h6>
                            <form action="{{ route('documents.versions.upload', $document) }}" 
                                  method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Version Number *</label>
                                        <input type="text" name="version_number" class="form-control" 
                                               placeholder="e.g., 2.0" required>
                                    </div>
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">Change Description *</label>
                                        <input type="text" name="change_description" class="form-control" 
                                               placeholder="What changed in this version?" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Upload File *</label>
                                    <input type="file" name="file" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-upload"></i> Upload New Version
                                </button>
                            </form>
                            @endcan
                        </div>

                        <!-- Approvals Tab -->
                        <div class="tab-pane fade" id="approvals" role="tabpanel">
                            @if($document->approvals->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Reviewer</th>
                                            <th>Status</th>
                                            <th>Comment</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($document->approvals as $approval)
                                        <tr>
                                            <td>{{ $approval->reviewer->name }}</td>
                                            <td>
                                                @php
                                                    $approvalColors = [
                                                        'PENDING' => 'warning',
                                                        'APPROVED' => 'success',
                                                        'REJECTED' => 'danger'
                                                    ];
                                                @endphp
                                                <span class="badge bg-{{ $approvalColors[$approval->status] ?? 'secondary' }}">
                                                    {{ $approval->status }}
                                                </span>
                                            </td>
                                            <td>{{ $approval->comment ?? 'No comment' }}</td>
                                            <td>{{ $approval->reviewed_at ? $approval->reviewed_at->format('d M Y H:i') : 'Pending' }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @else
                            <p class="text-muted text-center">No approvals yet</p>
                            @endif

                            @if(in_array(auth()->user()->role, ['ADMIN', 'HR_MANAGER', 'SALES_MANAGER', 'MARKETING_MANAGER', 'IT_MANAGER']))
                            @if($document->status === 'PENDING_REVIEW')
                            <hr>
                            <h6>Review Document</h6>
                            <form action="{{ route('documents.approve', $document) }}" method="POST" class="d-inline">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">Comment (Optional)</label>
                                    <textarea name="comment" class="form-control" rows="2"></textarea>
                                </div>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-circle"></i> Approve
                                </button>
                            </form>

                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                <i class="bi bi-x-circle"></i> Reject
                            </button>
                            @endif
                            @endif
                        </div>

                        <!-- Activity Tab -->
                        <div class="tab-pane fade" id="activity" role="tabpanel">
                            <div class="timeline">
                                @foreach($document->activities()->latest()->get() as $activity)
                                <div class="timeline-item mb-3">
                                    <div class="d-flex">
                                        <div class="me-3">
                                            @php
                                                $activityIcons = [
                                                    'CREATED' => 'plus-circle text-success',
                                                    'VIEWED' => 'eye text-info',
                                                    'DOWNLOADED' => 'download text-primary',
                                                    'EDITED' => 'pencil text-warning',
                                                    'SHARED' => 'share text-info',
                                                    'APPROVED' => 'check-circle text-success',
                                                    'REJECTED' => 'x-circle text-danger',
                                                    'ARCHIVED' => 'archive text-secondary'
                                                ];
                                            @endphp
                                            <i class="bi bi-{{ $activityIcons[$activity->action] ?? 'circle' }} fs-4"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <strong>{{ $activity->user->name }}</strong>
                                            <span class="text-muted">{{ strtolower($activity->action) }}</span>
                                            @if($activity->description)
                                            <p class="mb-0 text-muted small">{{ $activity->description }}</p>
                                            @endif
                                            <small class="text-muted">
                                                {{ $activity->created_at->diffForHumans() }}
                                                @if($activity->ip_address)
                                                · IP: {{ $activity->ip_address }}
                                                @endif
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions Sidebar -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Actions</h6>
                </div>
                <div class="card-body">
                    @can('view', $document)
                    <a href="{{ route('documents.show', $document) }}" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-eye"></i> View Document
                    </a>
                    @endcan

                    @if(auth()->user()->can('view', $document) && !auth()->user()->can('download', $document))
                    <div class="alert alert-info small mb-2">
                        View only — downloading is not allowed.
                    </div>
                    @endif

                    @if($document->latestVersion && auth()->user()->can('download', $document))
                    <a href="{{ route('documents.versions.download', [$document, $document->latestVersion]) }}" 
                       class="btn btn-success w-100 mb-2">
                        <i class="bi bi-download"></i> Download Latest Version
                    </a>
                    @endif

                    @can('update', $document)
                    <a href="{{ route('documents.edit', $document) }}" class="btn btn-warning w-100 mb-2">
                        <i class="bi bi-pencil"></i> Edit Document
                    </a>

                    @if($document->status === 'DRAFT')
                    <form action="{{ route('documents.submit-approval', $document) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-primary w-100 mb-2">
                            <i class="bi bi-send"></i> Submit for Approval
                        </button>
                    </form>
                    @endif

                    <button type="button" class="btn btn-info w-100 mb-2" data-bs-toggle="modal" data-bs-target="#shareModal">
                        <i class="bi bi-share"></i> Share Document
                    </button>
                    @endcan

                    @can('delete', $document)
                    <form action="{{ route('documents.destroy', $document) }}" method="POST"
                          onsubmit="return confirm('Are you sure?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="bi bi-trash"></i> Delete Document
                        </button>
                    </form>
                    @endcan
                </div>
            </div>

            <!-- Document Info -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Document Information</h6>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        <strong>Total Versions:</strong>
                        <span class="float-end">{{ $document->versions->count() }}</span>
                    </p>
                    <p class="mb-2">
                        <strong>Total Views:</strong>
                        <span class="float-end">{{ $document->activities()->where('action', 'VIEWED')->count() }}</span>
                    </p>
                    <p class="mb-2">
                        <strong>Total Downloads:</strong>
                        <span class="float-end">{{ $document->activities()->where('action', 'DOWNLOADED')->count() }}</span>
                    </p>
                    <p class="mb-0">
                        <strong>Active Shares:</strong>
                        <span class="float-end">{{ $document->shares()->where(function ($shares) { $shares->whereNull('expires_at')->orWhere('expires_at', '>', now()); })->count() }}</span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('documents.reject', $document) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Reject Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Reason for Rejection *</label>
                        <textarea name="comment" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Share Modal -->
<div class="modal fade" id="shareModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('documents.share', $document) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Share Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <strong>Document was not shared.</strong>
                        <ul class="mb-0 mt-1">
                            @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label">Share with Users</label>
                        <select name="user_ids[]" class="form-select @error('user_ids') is-invalid @enderror @error('user_ids.*') is-invalid @enderror" multiple size="5">
                            @foreach(\App\Models\User::where('status', 'ACTIVE')->whereKeyNot(auth()->id())->orderBy('name')->get() as $user)
                            <option value="{{ $user->id }}" {{ in_array($user->id, array_map('intval', (array) old('user_ids', [])), true) ? 'selected' : '' }}>{{ $user->name }} ({{ $user->role }})</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Hold Ctrl/Cmd to select multiple users</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Share with Departments</label>
                        <select name="department_ids[]" class="form-select @error('department_ids') is-invalid @enderror @error('department_ids.*') is-invalid @enderror" multiple size="4">
                            @foreach($departments as $department)
                            <option value="{{ $department->id }}" {{ in_array($department->id, array_map('intval', (array) old('department_ids', [])), true) ? 'selected' : '' }}>{{ $department->name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Everyone in the selected department can view the document.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Access Type *</label>
                        <select name="access_type" class="form-select @error('access_type') is-invalid @enderror" required>
                            <option value="VIEW" {{ old('access_type', 'VIEW') === 'VIEW' ? 'selected' : '' }}>View Only</option>
                            <option value="DOWNLOAD" {{ old('access_type') === 'DOWNLOAD' ? 'selected' : '' }}>View & Download</option>
                            <option value="EDIT" {{ old('access_type') === 'EDIT' ? 'selected' : '' }}>View, Download & Edit</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Expires At (Optional)</label>
                        <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}" min="{{ now()->addMinute()->startOfMinute()->format('Y-m-d\\TH:i') }}" class="form-control @error('expires_at') is-invalid @enderror">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Share Document</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@if($errors->any())
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const shareModal = document.getElementById('shareModal');
        if (shareModal) bootstrap.Modal.getOrCreateInstance(shareModal).show();
    });
</script>
@endpush
@endif
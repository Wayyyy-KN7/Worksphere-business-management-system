@extends('layouts.app')

@section('title', 'Document Approvals')
@section('breadcrumb', 'Documents / Approvals')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
    <div>
        <h2>Document Approvals</h2>
        <p class="text-muted mb-0">Review documents awaiting approval in your scope.</p>
    </div>
    <a href="{{ route('documents.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-folder2-open me-1" aria-hidden="true"></i>All Documents
    </a>
</div>

<section class="card border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <span>Pending review</span>
        <span class="text-muted small">{{ $documents->total() }} {{ \Illuminate\Support\Str::plural('document', $documents->total()) }}</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Document</th>
                    <th scope="col">Department</th>
                    <th scope="col">Submitted By</th>
                    <th scope="col">Submitted</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($documents as $document)
                    <tr>
                        <td>
                            <a class="fw-semibold" href="{{ route('documents.show', $document) }}">{{ $document->title }}</a>
                            <span class="d-block text-muted small">{{ $document->document_number }} · {{ \Illuminate\Support\Str::headline($document->document_type) }}</span>
                        </td>
                        <td>{{ $document->department?->name ?? 'Company-wide' }}</td>
                        <td>{{ $document->uploader?->name ?? 'Unknown user' }}</td>
                        <td class="text-nowrap">{{ $document->updated_at->format('M j, Y') }}</td>
                        <td><span class="badge bg-warning">Pending review</span></td>
                        <td class="text-end">
                            @can('approve', $document)
                                <a href="{{ route('documents.show', $document) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye me-1" aria-hidden="true"></i>Review
                                </a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-check2-circle d-block fs-3 mb-2" aria-hidden="true"></i>
                            No documents are waiting for review.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($documents->hasPages())
        <div class="card-body border-top">{{ $documents->links() }}</div>
    @endif
</section>
@endsection
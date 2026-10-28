@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">Preview unavailable</h5>
                <small class="text-muted">{{ $document->title }}</small>
            </div>
            <a href="{{ route('documents.versions.download', [$document, $version]) }}" class="btn btn-success btn-sm">
                <i class="bi bi-download"></i> Download
            </a>
        </div>
        <div class="card-body">
            <div class="alert alert-warning mb-0">
                {{ $message ?? 'This file type cannot be previewed in the browser. Use the download option to open it securely on your device.' }}
            </div>
        </div>
    </div>
</div>
@endsection

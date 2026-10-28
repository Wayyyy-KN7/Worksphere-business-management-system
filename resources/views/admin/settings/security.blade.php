@extends('layouts.app')

@section('title', 'Login Protection')
@section('breadcrumb', 'Settings / Login Protection')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
    <div>
        <h2>Login Protection</h2>
        <p class="text-muted mb-0">Configure sign-in attempt limits and inactive session expiry.</p>
    </div>
    <a href="{{ route('admin.settings.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Settings
    </a>
</div>

<form method="POST" action="{{ route('admin.settings.security.update') }}">
    @csrf
    @method('PUT')
    <div class="card border-0 shadow-sm">
        <div class="card-header"><i class="bi bi-shield-lock me-2 text-primary" aria-hidden="true"></i>Account protection</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="max_failed_login_attempts">Maximum failed sign-in attempts</label>
                    <input id="max_failed_login_attempts" name="max_failed_login_attempts" type="number" min="1" max="20" required
                           class="form-control @error('max_failed_login_attempts') is-invalid @enderror"
                           value="{{ old('max_failed_login_attempts', $settings->max_failed_login_attempts) }}">
                    @error('max_failed_login_attempts')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="account_lockout_minutes">Account lockout duration (minutes)</label>
                    <input id="account_lockout_minutes" name="account_lockout_minutes" type="number" min="1" max="1440" required
                           class="form-control @error('account_lockout_minutes') is-invalid @enderror"
                           value="{{ old('account_lockout_minutes', $settings->account_lockout_minutes) }}">
                    @error('account_lockout_minutes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="session_timeout_minutes">Inactive session timeout (minutes)</label>
                    <input id="session_timeout_minutes" name="session_timeout_minutes" type="number" min="5" max="1440" required
                           class="form-control @error('session_timeout_minutes') is-invalid @enderror"
                           value="{{ old('session_timeout_minutes', $settings->session_timeout_minutes) }}">
                    @error('session_timeout_minutes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Save settings</button>
                <a href="{{ route('admin.settings.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>
@endsection

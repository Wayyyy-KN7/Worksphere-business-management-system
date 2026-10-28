@extends('layouts.app')

@section('title', 'Settings')
@section('breadcrumb', 'Settings')

@push('styles')
<style>
    .settings-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .settings-tile {
        min-height: 112px;
        display: flex;
        align-items: flex-start;
        gap: 16px;
        padding: 20px;
        color: inherit;
        background: var(--panel-bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        text-decoration: none;
        transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
    }

    a.settings-tile:hover {
        color: inherit;
        border-color: var(--primary);
        box-shadow: 0 6px 18px rgba(20, 54, 74, 0.08);
        transform: translateY(-1px);
    }

    .settings-tile-icon {
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        display: grid;
        place-items: center;
        color: var(--primary);
        background: rgba(18, 163, 139, 0.12);
        border-radius: 8px;
        font-size: 1.2rem;
    }

    .settings-tile-copy {
        min-width: 0;
        flex: 1;
    }

    .settings-tile-title {
        margin: 1px 0 4px;
        color: var(--navy);
        font-size: 1rem;
        font-weight: 650;
    }

    .settings-tile-description {
        margin: 0;
        color: var(--muted);
        font-size: 0.9rem;
    }

    .settings-tile-arrow {
        margin: 5px 0 0 auto;
        color: var(--muted);
    }

    .company-fact {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding-top: 5px;
        color: var(--muted);
        font-size: 0.82rem;
    }

    .company-fact strong {
        color: var(--navy);
        font-weight: 600;
        text-align: right;
        overflow-wrap: anywhere;
    }

    @media (max-width: 700px) {
        .settings-grid { grid-template-columns: minmax(0, 1fr); }
        .settings-tile { min-height: 100px; padding: 16px; }
    }
</style>
@endpush

@section('content')
<div class="page-header">
    <h2>Settings</h2>
    <p class="text-muted">Manage your CRM system configuration</p>
</div>

<div class="settings-grid">
    <a class="settings-tile" id="company-information" href="{{ route('admin.settings.company') }}" aria-labelledby="company-settings-title">
        <span class="settings-tile-icon"><i class="bi bi-building" aria-hidden="true"></i></span>
        <span class="settings-tile-copy">
            <h3 class="settings-tile-title" id="company-settings-title">Company</h3>
            <p class="settings-tile-description">Company information</p>
        </span>
        <i class="bi bi-arrow-up-right settings-tile-arrow" aria-hidden="true"></i>
    </a>

    <a class="settings-tile" href="{{ route('admin.settings.security') }}">
        <span class="settings-tile-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
        <span class="settings-tile-copy"><span class="settings-tile-title d-block">Login Protection</span><span class="settings-tile-description">Sign-in attempt limits and inactive sessions</span></span>
        <i class="bi bi-arrow-up-right settings-tile-arrow" aria-hidden="true"></i>
    </a>
</div>
@endsection
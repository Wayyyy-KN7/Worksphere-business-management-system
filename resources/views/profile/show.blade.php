@extends('layouts.app')

@section('title', 'My Profile')
@section('breadcrumb', 'My Profile')

@push('styles')
<style>
    .profile-shell {
        display: grid;
        gap: 1.25rem;
        max-width: 1140px;
        margin: 0 auto;
    }

    .profile-card {
        background: #ffffff;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 18px;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.04);
        padding: 1.5rem 1.6rem;
    }

    .profile-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .profile-user {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .profile-avatar {
        position: relative;
        display: grid;
        place-items: center;
        width: 74px;
        height: 74px;
        border-radius: 50%;
        overflow: hidden;
        background: linear-gradient(135deg, #d4d4d8, #a1a1aa);
        border: 3px solid #f3f4f6;
        font-size: 1.5rem;
        font-weight: 700;
        color: #1f2937;
        flex-shrink: 0;
    }

    .profile-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .profile-name {
        margin: 0;
        font-size: 2rem;
        font-weight: 700;
        color: #111827;
    }

    .profile-role {
        margin: 0.25rem 0 0;
        color: #4b5563;
        font-size: 0.95rem;
    }

    .profile-department {
        margin: 0.1rem 0 0;
        color: #6b7280;
        font-size: 0.9rem;
    }

    .profile-edit-btn,
    .profile-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(15, 23, 42, 0.12);
        background: #f8fafc;
        color: #111827;
        border-radius: 10px;
        padding: 0.6rem 0.95rem;
        font-weight: 600;
        transition: all 0.2s ease;
    }

    .profile-action-btn {
        background: #f5f5f4;
        border-color: rgba(15, 23, 42, 0.1);
    }

    .profile-edit-btn:hover,
    .profile-action-btn:hover {
        background: #eef2ff;
        border-color: rgba(59, 130, 246, 0.28);
    }

    .section-title {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 700;
        color: #111827;
    }

    .profile-password-form {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .field-group {
        display: grid;
        gap: 0.4rem;
    }

    .field-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #4b5563;
    }

    .profile-input {
        width: 100%;
        min-height: 44px;
        border: 1px solid rgba(148, 163, 184, 0.5);
        border-radius: 10px;
        background: #f8fafc;
        padding: 0.7rem 0.8rem;
        color: #111827;
    }

    .profile-input:focus {
        outline: none;
        border-color: rgba(96, 165, 250, 0.8);
        box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.12);
    }

    .profile-info-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1.2rem 1rem;
        margin-top: 1rem;
    }

    .profile-info-item {
        display: grid;
        gap: 0.25rem;
        min-height: 62px;
        padding-top: 0.75rem;
        border-top: 1px solid rgba(148, 163, 184, 0.2);
    }

    .profile-info-item span {
        color: #6b7280;
        font-size: 0.82rem;
    }

    .profile-info-item strong {
        color: #111827;
        font-size: 0.96rem;
        font-weight: 600;
    }

    @media (max-width: 767px) {
        .profile-header,
        .profile-user {
            flex-direction: column;
            align-items: flex-start;
        }

        .profile-password-form,
        .profile-info-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<div class="profile-shell">
    <div class="profile-card">
        <div class="profile-header">
            <div class="profile-user">
                <div class="profile-avatar">
                    @if($user->profile_photo_path)
                        <img src="{{ asset('storage/' . $user->profile_photo_path) }}" alt="{{ $user->name }} profile photo">
                    @else
                        <span>{{ $user->initials }}</span>
                    @endif
                </div>

                <div>
                    <h2 class="profile-name">{{ $user->name }}</h2>
                    <p class="profile-role">{{ $user->position ?: $user->getRoleLabelAttribute() }}</p>
                    <p class="profile-department">{{ $user->department?->name ?? 'No department assigned' }}</p>
                </div>
            </div>

            <a href="{{ route('profile.edit') }}" class="profile-edit-btn">
                <i class="bi bi-pencil-square me-2"></i>Edit
            </a>
        </div>
    </div>



    <div class="profile-card">
        <h3 class="section-title">Information</h3>

        <div class="profile-info-grid">
            <div class="profile-info-item">
                <span>Department</span>
                <strong>{{ $user->department?->name ?? 'Not assigned' }}</strong>
            </div>
            <div class="profile-info-item">
                <span>Employee ID</span>
                <strong>{{ $user->employee_id ?? 'Not assigned' }}</strong>
            </div>
            <div class="profile-info-item">
                <span>Email</span>
                <strong>{{ $user->email }}</strong>
            </div>
            <div class="profile-info-item">
                <span>Phone</span>
                <strong>{{ $user->phone ?: 'Not provided' }}</strong>
            </div>
            <div class="profile-info-item">
                <span>Position</span>
                <strong>{{ $user->position ?: $user->getRoleLabelAttribute() }}</strong>
            </div>
            <div class="profile-info-item">
                <span>Joining Date</span>
                <strong>{{ $user->joining_date?->format('M d, Y') ?? 'Not provided' }}</strong>
            </div>
            <div class="profile-info-item">
                <span>Role</span>
                <strong>{{ $user->getRoleLabelAttribute() }}</strong>
            </div>
            <div class="profile-info-item">
                <span>Status</span>
                <strong>{{ ucfirst(strtolower($user->status)) }}</strong>
            </div>
        </div>
    </div>
</div>
@endsection
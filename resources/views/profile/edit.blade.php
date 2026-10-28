@extends('layouts.app')

@section('title', 'Edit Profile')
@section('breadcrumb', 'Profile / Edit')

@push('styles')
<style>
    .profile-edit-shell {
        max-width: 860px;
        margin: 0 auto;
    }

    .profile-edit-card {
        background: #ffffff;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 18px;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.04);
        padding: 1.5rem 1.6rem;
    }

    .profile-edit-form {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.25rem;
    }

    .profile-edit-field {
        display: grid;
        gap: 0.45rem;
    }

    .profile-edit-field.full {
        grid-column: 1 / -1;
    }

    .field-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #4b5563;
    }

    .profile-edit-input,
    .profile-edit-file {
        width: 100%;
        min-height: 44px;
        border: 1px solid rgba(148, 163, 184, 0.5);
        border-radius: 10px;
        background: #f8fafc;
        padding: 0.7rem 0.8rem;
        color: #111827;
    }

    .profile-edit-file {
        padding: 0.55rem 0.75rem;
    }

    .profile-edit-input:focus {
        outline: none;
        border-color: rgba(96, 165, 250, 0.8);
        box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.12);
    }

    .profile-edit-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        margin-top: 1rem;
    }

    .btn-primary-soft,
    .btn-light-soft {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        padding: 0.7rem 1rem;
        font-weight: 600;
        border: 1px solid transparent;
        text-decoration: none;
    }

    .btn-primary-soft {
        background: #111827;
        color: #ffffff;
    }

    .btn-light-soft {
        background: #f8fafc;
        border-color: rgba(15, 23, 42, 0.08);
        color: #111827;
    }

    .avatar-preview {
        width: 108px;
        height: 108px;
        border-radius: 50%;
        overflow: hidden;
        border: 3px solid #f3f4f6;
        background: linear-gradient(135deg, #d4d4d8, #a1a1aa);
        display: grid;
        place-items: center;
        font-size: 2rem;
        font-weight: 700;
        color: #111827;
    }

    .avatar-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    @media (max-width: 767px) {
        .profile-edit-form {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<div class="profile-edit-shell">
    <div class="profile-edit-card">
        <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
                <div class="avatar-preview">
                    @if($user->profile_photo_path)
                        <img src="{{ asset('storage/' . $user->profile_photo_path) }}" alt="{{ $user->name }} preview">
                    @else
                        <span>{{ $user->initials }}</span>
                    @endif
                </div>
                <div>
                    <h3 class="mb-1">Edit Profile</h3>
                    <p class="text-muted mb-0">Update your basic information and profile image.</p>
                </div>
            </div>

            <div class="profile-edit-form">
                <div class="profile-edit-field">
                    <label for="name" class="field-label">Full Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" class="profile-edit-input @error('name') is-invalid @enderror" required>
                    @error('name')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="profile-edit-field">
                    <label for="phone" class="field-label">Phone</label>
                    <input id="phone" name="phone" type="text" value="{{ old('phone', $user->phone) }}" class="profile-edit-input @error('phone') is-invalid @enderror">
                    @error('phone')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="profile-edit-field full">
                    <label for="profile_photo" class="field-label">Profile Photo</label>
                    <input id="profile_photo" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp" class="profile-edit-file @error('profile_photo') is-invalid @enderror">
                    <small class="text-muted">Optional. JPG, PNG or WebP, max 2 MB.</small>
                    @error('profile_photo')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="profile-edit-actions">
                <a href="{{ route('profile.show') }}" class="btn-light-soft">Cancel</a>
                <button type="submit" class="btn-primary-soft">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection

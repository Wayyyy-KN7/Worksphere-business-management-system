@extends('layouts.app')

@section('title', 'Employee Profile')
@section('breadcrumb', 'Employees / View')

@push('styles')
<style>
    .employee-profile-shell {
        display: grid;
        gap: 1.25rem;
        max-width: 1140px;
        margin: 0 auto;
    }

    .employee-profile-card {
        background: #ffffff;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 18px;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.04);
        padding: 1.5rem 1.6rem;
    }

    .employee-profile-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .employee-profile-user {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .employee-profile-avatar {
        display: grid;
        place-items: center;
        width: 74px;
        height: 74px;
        border-radius: 50%;
        background: linear-gradient(135deg, #d4d4d8, #a1a1aa);
        color: #111827;
        font-size: 1.5rem;
        font-weight: 700;
        overflow: hidden;
        border: 3px solid #f3f4f6;
    }

    .employee-profile-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .employee-profile-name {
        margin: 0;
        font-size: 2rem;
        font-weight: 700;
        color: #111827;
    }

    .employee-profile-role {
        margin: 0.25rem 0 0;
        color: #4b5563;
        font-size: 0.95rem;
    }

    .employee-profile-department {
        margin: 0.1rem 0 0;
        color: #6b7280;
        font-size: 0.9rem;
    }

    .employee-profile-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(15, 23, 42, 0.12);
        background: #f8fafc;
        color: #111827;
        border-radius: 10px;
        padding: 0.6rem 0.95rem;
        font-weight: 600;
        text-decoration: none;
    }

    .employee-profile-button:hover {
        background: #eef2ff;
        text-decoration: none;
        color: #111827;
    }

    .employee-section-title {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 700;
        color: #111827;
    }

    .employee-profile-info {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1.2rem 1rem;
        margin-top: 1rem;
    }

    .employee-profile-item {
        display: grid;
        gap: 0.25rem;
        min-height: 62px;
        padding-top: 0.75rem;
        border-top: 1px solid rgba(148, 163, 184, 0.2);
    }

    .employee-profile-item span {
        color: #6b7280;
        font-size: 0.82rem;
    }

    .employee-profile-item strong {
        color: #111827;
        font-size: 0.96rem;
        font-weight: 600;
    }

    @media (max-width: 767px) {
        .employee-profile-header,
        .employee-profile-user {
            flex-direction: column;
            align-items: flex-start;
        }

        .employee-profile-info {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<div class="employee-profile-shell">
    <div class="employee-profile-card">
        <div class="employee-profile-header">
            <div class="employee-profile-user">
                <div class="employee-profile-avatar">
                    @if($user->profile_photo_path)
                        <img src="{{ asset('storage/' . $user->profile_photo_path) }}" alt="{{ $user->name }} profile photo">
                    @else
                        <span>{{ $user->initials }}</span>
                    @endif
                </div>

                <div>
                    <h2 class="employee-profile-name">{{ $user->name }}</h2>
                    <p class="employee-profile-role">{{ $user->position ?: $user->getRoleLabelAttribute() }}</p>
                    <p class="employee-profile-department">{{ $user->department?->name ?? 'No department assigned' }}</p>
                </div>
            </div>

            <a href="{{ route('admin.users.edit', $user) }}" class="employee-profile-button">
                <i class="bi bi-pencil-square me-2"></i>Edit Employee
            </a>
        </div>
    </div>

    <div class="employee-profile-card">
        <h3 class="employee-section-title">Information</h3>

        <div class="employee-profile-info">
            <div class="employee-profile-item">
                <span>Employee ID</span>
                <strong>{{ $user->employee_id }}</strong>
            </div>
            <div class="employee-profile-item">
                <span>Department</span>
                <strong>{{ $user->department?->name ?? 'Not assigned' }}</strong>
            </div>
            <div class="employee-profile-item">
                <span>Email</span>
                <strong>{{ $user->email }}</strong>
            </div>
            <div class="employee-profile-item">
                <span>Phone</span>
                <strong>{{ $user->phone ?: 'Not provided' }}</strong>
            </div>
            <div class="employee-profile-item">
                <span>Position</span>
                <strong>{{ $user->position ?: $user->getRoleLabelAttribute() }}</strong>
            </div>
            <div class="employee-profile-item">
                <span>Joining Date</span>
                <strong>{{ $user->joining_date?->format('M d, Y') ?? 'Not provided' }}</strong>
            </div>
            <div class="employee-profile-item">
                <span>Role</span>
                <strong>{{ $user->getRoleLabelAttribute() }}</strong>
            </div>
            <div class="employee-profile-item">
                <span>Status</span>
                <strong>{{ ucfirst(strtolower($user->status)) }}</strong>
            </div>
        </div>
    </div>

    <div class="employee-profile-card">
        <h3 class="employee-section-title">Recent Attendance</h3>
        @if($user->attendances->count() > 0)
            <div class="table-responsive mt-3">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Hours</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($user->attendances as $attendance)
                            <tr>
                                <td>{{ $attendance->attendance_date->format('M d, Y') }}</td>
                                <td>{{ $attendance->check_in ? \Carbon\Carbon::parse($attendance->check_in)->format('H:i') : '-' }}</td>
                                <td>{{ $attendance->check_out ? \Carbon\Carbon::parse($attendance->check_out)->format('H:i') : '-' }}</td>
                                <td>{{ number_format($attendance->working_hours, 1) }}h</td>
                                <td>
                                    @if($attendance->status === 'PRESENT')
                                        <span class="badge bg-success">Present</span>
                                    @elseif($attendance->status === 'LATE')
                                        <span class="badge bg-warning text-dark">Late</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $attendance->status }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0 mt-3">No attendance records yet.</p>
        @endif
    </div>
</div>
@endsection
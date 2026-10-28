@extends('layouts.app')

@section('title', 'Notifications')
@section('breadcrumb', 'Notifications')

@push('styles')
<style>
    .notifications-panel {
        background: var(--panel-bg);
        border: 1px solid var(--border);
        border-radius: 6px;
        box-shadow: var(--shadow);
        overflow: hidden;
    }

    .notifications-list .list-group-item {
        padding: 0.85rem 1rem;
        border-color: var(--border);
    }

    .notification-item-unread { background: #F0F7F6; }
</style>
@endpush

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2>Notifications</h2>
        <p class="text-muted">Alerts for records and actions requiring your attention.</p>
    </div>
    @if(auth()->user()->unreadNotificationsCount() > 0)
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button class="btn btn-outline-primary" type="submit"><i class="bi bi-check2-all"></i> Mark All Read</button>
        </form>
    @endif
</div>

<div class="notifications-panel">
    <div class="list-group list-group-flush notifications-list">
        @forelse($notifications as $notification)
            <div class="list-group-item py-3 {{ $notification->is_read ? '' : 'notification-item-unread' }}">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div class="d-flex gap-3">
                        <span class="text-{{ $notification->is_read ? 'secondary' : 'primary' }}" aria-hidden="true">
                            <i class="bi bi-{{ $notification->is_read ? 'bell' : 'bell-fill' }} fs-5"></i>
                        </span>
                        <div>
                            <div class="fw-semibold">{{ $notification->title }}</div>
                            <p class="mb-1">{{ $notification->message }}</p>
                            <small class="text-muted" title="{{ $notification->created_at->format('M j, Y g:i A') }}">{{ $notification->created_at->diffForHumans() }}</small>
                            @unless($notification->is_read)<span class="badge bg-primary ms-2">Unread</span>@endunless
                        </div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <x-table-actions>
                        @if($notification->related_type && $notification->related_id)
                            <li><a class="dropdown-item" href="{{ route('notifications.open', $notification) }}"><i class="bi bi-box-arrow-up-right"></i>Open</a></li>
                        @endif
                        @if($notification->is_read)
                            <li><form method="POST" action="{{ route('notifications.unread', $notification) }}">@csrf @method('PATCH')<button class="dropdown-item" type="submit"><i class="bi bi-envelope"></i>Mark unread</button></form></li>
                        @else
                            <li><form method="POST" action="{{ route('notifications.read', $notification) }}">@csrf @method('PATCH')<button class="dropdown-item" type="submit"><i class="bi bi-envelope-open"></i>Mark as read</button></form></li>
                        @endif
                        <li><form method="POST" action="{{ route('notifications.destroy', $notification) }}" onsubmit="return confirm('Delete this notification?')">@csrf @method('DELETE')<button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash"></i>Delete</button></form></li>
                        </x-table-actions>
                    </div>
                </div>
            </div>
        @empty
            <div class="list-group-item text-center text-muted py-5">You have no notifications.</div>
        @endforelse
    </div>
</div>

<div class="mt-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
    <small class="text-muted">
        Showing {{ $notifications->firstItem() ?? 0 }}-{{ $notifications->lastItem() ?? 0 }} of {{ $notifications->total() }} notifications
    </small>
    {{ $notifications->links('pagination::bootstrap-5') }}
</div>
@endsection
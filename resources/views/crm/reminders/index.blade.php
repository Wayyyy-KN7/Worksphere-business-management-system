@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col">
            <h2>My Reminders</h2>
        </div>
        <div class="col-auto">
            <a href="{{ route('crm.reminders.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> New Reminder
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Customer</th>
                            <th>Opportunity</th>
                            <th>Due</th>
                            <th>Assigned To</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reminders as $reminder)
                        <tr class="{{ $reminder->status === 'OVERDUE' ? 'table-danger' : '' }}">
                            <td>{{ $reminder->title }}</td>
                            <td>{{ $reminder->customer->name ?? 'N/A' }}</td>
                            <td>{{ $reminder->opportunity->title ?? 'N/A' }}</td>
                            <td>{{ $reminder->due_at->format('d M Y, h:i A') }}</td>
                            <td>{{ $reminder->user->name ?? 'N/A' }}</td>
                            <td>
                                @php
                                    $statusColors = [
                                        'PENDING' => 'warning',
                                        'SNOOZED' => 'info',
                                        'OVERDUE' => 'danger',
                                        'COMPLETED' => 'success',
                                        'CANCELLED' => 'secondary'
                                    ];
                                @endphp
                                <span class="badge bg-{{ $statusColors[$reminder->status] ?? 'secondary' }}">
                                    {{ $reminder->status }}
                                </span>
                            </td>
                            <td>
                                <x-table-actions>
                                    @can('view', $reminder)
                                    <li><a class="dropdown-item" href="{{ route('crm.reminders.show', $reminder) }}"><i class="bi bi-eye"></i>View</a></li>
                                    @endcan
                                    @can('update', $reminder)
                                    <li><a class="dropdown-item" href="{{ route('crm.reminders.edit', $reminder) }}"><i class="bi bi-pencil"></i>Edit</a></li>
                                    @if(in_array($reminder->status, ['PENDING', 'SNOOZED', 'OVERDUE']))
                                    <li><form action="{{ route('crm.reminders.complete', $reminder) }}" method="POST">@csrf<button type="submit" class="dropdown-item"><i class="bi bi-check-circle"></i>Complete</button></form></li>
                                    <li><form action="{{ route('crm.reminders.snooze', $reminder) }}" method="POST">@csrf<input type="hidden" name="reminder_date" value="{{ now()->addDay()->toDateString() }}"><input type="hidden" name="reminder_time" value="{{ $reminder->reminder_time }}"><button type="submit" class="dropdown-item"><i class="bi bi-alarm"></i>Snooze 1 day</button></form></li>
                                    <li><form action="{{ route('crm.reminders.cancel', $reminder) }}" method="POST">@csrf<button type="submit" class="dropdown-item"><i class="bi bi-x-lg"></i>Cancel</button></form></li>
                                    @endif
                                    @endcan
                                    @can('delete', $reminder)
                                    <li><form action="{{ route('crm.reminders.destroy', $reminder) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this reminder?')">@csrf @method('DELETE')<button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i>Delete</button></form></li>
                                    @endcan
                                </x-table-actions>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center">No reminders found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $reminders->links() }}
        </div>
    </div>
</div>
@endsection
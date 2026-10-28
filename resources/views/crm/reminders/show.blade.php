@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Reminder Details</h4>
                    <div>
                        <a href="{{ route('crm.reminders.edit', $reminder) }}" class="btn btn-sm btn-warning">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <a href="{{ route('crm.reminders.index') }}" class="btn btn-sm btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Title:</strong>
                            <p>{{ $reminder->title }}</p>
                        </div>
                        <div class="col-md-6">
                            <strong>Status:</strong>
                            <p>
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
                            </p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Customer:</strong>
                            <p>{{ $reminder->customer->name ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            <strong>Opportunity:</strong>
                            <p>{{ $reminder->opportunity->title ?? 'N/A' }}</p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Reminder Date:</strong>
                            <p>{{ $reminder->due_at->format('d M Y') }}</p>
                        </div>
                        <div class="col-md-6">
                            <strong>Reminder Time:</strong>
                            <p>{{ $reminder->due_at->format('h:i A') }}</p>
                        </div>
                    </div>

                    <div class="mb-3">
                        <strong>Description:</strong>
                        <p class="text-muted">{{ $reminder->description ?: 'No description provided.' }}</p>
                    </div>

                    @if($reminder->followUp)
                        <div class="mb-3"><strong>Follow-Up:</strong> <a href="{{ route('crm.follow-ups.show', $reminder->followUp) }}">{{ $reminder->followUp->title }}</a></div>
                    @endif
                    @if($reminder->remindable && !$reminder->followUp)
                        <div class="mb-3">
                            <strong>Linked CRM Record:</strong>
                            <span>{{ $reminder->remindable->name ?? $reminder->remindable->title ?? $reminder->remindable->sale_number ?? ('Record #' . $reminder->remindable_id) }}</span>
                        </div>
                    @endif

                    @can('update', $reminder)
                        @if(in_array($reminder->status, ['PENDING', 'SNOOZED', 'OVERDUE']))
                            <div class="d-flex flex-wrap gap-2 mb-4">
                                <form method="POST" action="{{ route('crm.reminders.complete', $reminder) }}">@csrf<button class="btn btn-success" type="submit"><i class="bi bi-check-circle"></i> Complete Reminder</button></form>
                                <form method="POST" action="{{ route('crm.reminders.snooze', $reminder) }}" class="d-flex gap-2">
                                    @csrf
                                    <input type="hidden" name="reminder_date" value="{{ now()->addDay()->toDateString() }}">
                                    <input type="hidden" name="reminder_time" value="{{ $reminder->reminder_time }}">
                                    <button class="btn btn-outline-primary" type="submit"><i class="bi bi-alarm"></i> Snooze Until Tomorrow</button>
                                </form>
                            </div>
                        @endif
                    @endcan

                    <div class="border-top pt-3">
                        <h5>Reminder History</h5>
                        @forelse($reminder->histories as $history)
                            <div class="border-bottom py-2">
                                <div class="d-flex justify-content-between"><strong>{{ str_replace('_', ' ', $history->action) }}</strong><small class="text-muted">{{ $history->created_at->format('d M Y, h:i A') }}</small></div>
                                <div class="text-muted">{{ $history->actor->name ?? 'System' }}@if($history->from_status || $history->to_status) · {{ $history->from_status ?? 'New' }} → {{ $history->to_status }}@endif</div>
                                @if($history->notes)<p class="mb-0">{{ $history->notes }}</p>@endif
                            </div>
                        @empty
                            <p class="text-muted mb-0">No reminder history.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
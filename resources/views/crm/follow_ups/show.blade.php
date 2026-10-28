@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Follow-Up Details</h4>
                    <div>
                        @can('update', $followUp)
                            <a href="{{ route('crm.follow-ups.edit', $followUp) }}" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i> Edit</a>
                        @endcan
                        <a href="{{ route('crm.follow-ups.index') }}" class="btn btn-sm btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Title:</strong>
                            <p>{{ $followUp->title }}</p>
                        </div>
                        <div class="col-md-6">
                            <strong>Status:</strong>
                            <p>
                                @php
                                    $statusColors = [
                                        'PLANNED' => 'primary',
                                        'IN_PROGRESS' => 'info',
                                        'OVERDUE' => 'danger',
                                        'COMPLETED' => 'success',
                                        'CANCELLED' => 'secondary'
                                    ];
                                @endphp
                                <span class="badge bg-{{ $statusColors[$followUp->status] ?? 'secondary' }}">
                                    {{ $followUp->status }}
                                </span>
                            </p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Customer:</strong>
                            <p>{{ $followUp->customer->name ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            <strong>Opportunity:</strong>
                            <p>{{ $followUp->opportunity->title ?? 'N/A' }}</p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Type:</strong>
                            <p><span class="badge bg-info">{{ $followUp->type }}</span></p>
                        </div>
                        <div class="col-md-6">
                            <strong>Assigned To:</strong>
                            <p>{{ $followUp->assignedUser->name ?? 'N/A' }}</p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Follow-Up Date:</strong>
                            <p>{{ $followUp->due_at->format('d M Y') }}</p>
                        </div>
                        <div class="col-md-6">
                            <strong>Follow-Up Time:</strong>
                            <p>{{ $followUp->due_at->format('h:i A') }}</p>
                        </div>
                    </div>

                    <div class="mb-3">
                        <strong>Description:</strong>
                        <p class="text-muted">{{ $followUp->description ?: 'No description provided.' }}</p>
                    </div>

                    <div class="mb-3">
                        <strong>Notes / Outcome:</strong>
                        <p class="text-muted">{{ $followUp->notes ?: 'No notes recorded.' }}</p>
                    </div>

                    @if($followUp->completed_at)
                    <div class="mb-3">
                        <strong>Completed At:</strong>
                        <p>{{ \Carbon\Carbon::parse($followUp->completed_at)->format('d M Y, h:i A') }}</p>
                    </div>
                    @endif

                    @can('perform', $followUp)
                        @if($followUp->status === 'PLANNED')
                            <div class="d-flex gap-2 mb-4">
                                <form method="POST" action="{{ route('crm.follow-ups.start', $followUp) }}">@csrf<button class="btn btn-primary" type="submit"><i class="bi bi-play-fill"></i> Start Activity</button></form>
                                <form method="POST" action="{{ route('crm.follow-ups.cancel', $followUp) }}">@csrf<button class="btn btn-outline-danger" type="submit"><i class="bi bi-x-lg"></i> Cancel</button></form>
                            </div>
                        @endif

                        @if(in_array($followUp->status, ['IN_PROGRESS', 'OVERDUE']))
                            <div class="border-top pt-3 mb-4" id="complete">
                                <h5>Complete Activity</h5>
                                <form method="POST" action="{{ route('crm.follow-ups.complete', $followUp) }}">
                                    @csrf
                                    <label class="form-label" for="completion_notes">Completion notes</label>
                                    <textarea id="completion_notes" name="completion_notes" class="form-control mb-2" rows="3" placeholder="Record the outcome of the call, meeting, or other activity."></textarea>
                                    <button class="btn btn-success" type="submit"><i class="bi bi-check-circle"></i> Mark Completed</button>
                                </form>
                            </div>

                            <div class="border-top pt-3 mb-4">
                                <h5>Reschedule Activity</h5>
                                <form method="POST" action="{{ route('crm.follow-ups.reschedule', $followUp) }}" class="row g-2">
                                    @csrf
                                    <div class="col-md-5"><label class="form-label" for="follow_up_date">New due date</label><input id="follow_up_date" type="date" name="follow_up_date" class="form-control" min="{{ today()->toDateString() }}" required></div>
                                    <div class="col-md-4"><label class="form-label" for="follow_up_time">New due time</label><input id="follow_up_time" type="time" name="follow_up_time" class="form-control"></div>
                                    <div class="col-md-12"><label class="form-label" for="reschedule_note">Reason</label><input id="reschedule_note" name="reschedule_note" class="form-control" maxlength="2000"></div>
                                    <div class="col-12"><button class="btn btn-outline-primary" type="submit">Reschedule</button></div>
                                </form>
                            </div>
                        @endif
                    @endcan

                    <div class="border-top pt-3 mb-4">
                        <h5>Reminder</h5>
                        @forelse($followUp->reminders as $reminder)
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <span>{{ $reminder->title }} · {{ $reminder->due_at->format('d M Y, h:i A') }}</span>
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('crm.reminders.show', $reminder) }}">{{ $reminder->status }}</a>
                            </div>
                        @empty
                            <p class="text-muted mb-0">No reminder linked.</p>
                        @endforelse
                    </div>

                    <div class="border-top pt-3">
                        <h5>Activity History</h5>
                        @forelse($followUp->histories as $history)
                            <div class="border-bottom py-2">
                                <div class="d-flex justify-content-between"><strong>{{ str_replace('_', ' ', $history->action) }}</strong><small class="text-muted">{{ $history->created_at->format('d M Y, h:i A') }}</small></div>
                                <div class="text-muted">{{ $history->actor->name ?? 'System' }}@if($history->from_status || $history->to_status) · {{ $history->from_status ?? 'New' }} → {{ $history->to_status }}@endif</div>
                                @if($history->notes)<p class="mb-0">{{ $history->notes }}</p>@endif
                            </div>
                        @empty
                            <p class="text-muted mb-0">No activity history.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
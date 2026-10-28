<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Notification;
use App\Models\Opportunity;
use App\Models\Reminder;
use App\Models\User;
use App\Services\CrmNotificationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FollowUpController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $user = $request->user();
        $this->authorize('viewAny', FollowUp::class);

        $query = FollowUp::query()->visibleTo($user)->whereNull('employee_id')
            ->with(['customer', 'opportunity', 'assignedUser']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        $followUps = $query->orderBy('follow_up_date')->orderBy('follow_up_time')->paginate(15)->withQueryString();

        return view('crm.follow_ups.index', compact('followUps'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', FollowUp::class);
        [$customers, $opportunities, $users] = $this->formOptions($request->user());
        $selectedOpportunity = $opportunities->firstWhere('id', $request->integer('opportunity_id'));
        $selectedCustomer = $selectedOpportunity
            ? $customers->firstWhere('id', $selectedOpportunity->customer_id)
            : $customers->firstWhere('id', $request->integer('customer_id'));

        return view('crm.follow_ups.create', compact('customers', 'opportunities', 'users', 'selectedCustomer', 'selectedOpportunity'));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $this->authorize('create', FollowUp::class);

        $validated = $request->validate([
            'customer_id' => 'nullable|required_without:opportunity_id|exists:customers,id',
            'opportunity_id' => 'nullable|required_without:customer_id|exists:opportunities,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'follow_up_date' => 'required|date|after_or_equal:today',
            'follow_up_time' => 'nullable|date_format:H:i',
            'type' => 'required|string|max:60',
            'priority' => 'required|in:LOW,NORMAL,HIGH,URGENT',
            'assigned_to' => 'nullable|exists:users,id',
            'recurrence_rule' => 'nullable|in:NONE,DAILY,WEEKLY,MONTHLY',
            'recurrence_until' => 'nullable|date|after_or_equal:follow_up_date',
        ]);

        $customer = !empty($validated['customer_id']) ? Customer::findOrFail($validated['customer_id']) : null;
        $opportunity = !empty($validated['opportunity_id']) ? Opportunity::findOrFail($validated['opportunity_id']) : null;
        $this->authorizeLinkedRecords($user, $customer, $opportunity);

        if ($opportunity && $customer && $opportunity->customer_id !== $customer->id) {
            return back()->withErrors(['opportunity_id' => 'The opportunity must belong to the selected customer.'])->withInput();
        }

        $assigneeId = $this->resolveAssignee($user, $validated['assigned_to'] ?? null);

        DB::transaction(function () use ($validated, $customer, $opportunity, $user, $assigneeId) {
            $followUp = FollowUp::create([
                'customer_id' => $customer?->id ?? $opportunity?->customer_id,
                'opportunity_id' => $opportunity?->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'follow_up_date' => $validated['follow_up_date'],
                'follow_up_time' => $validated['follow_up_time'] ?? null,
                'type' => $validated['type'],
                'priority' => $validated['priority'],
                'status' => 'PLANNED',
                'assigned_to' => $assigneeId,
                'created_by' => $user->id,
                'recurrence_rule' => $validated['recurrence_rule'] ?? 'NONE',
                'recurrence_until' => $validated['recurrence_until'] ?? null,
            ]);

            $this->recordHistory($followUp, 'CREATED', null, 'PLANNED', 'Follow-Up created.');
            $this->createLinkedReminder($followUp);

            if ($assigneeId !== $user->id) {
                $this->notify($assigneeId, 'FOLLOW_UP_ASSIGNED', 'Follow-Up assigned', "{$followUp->title} has been assigned to you.", $followUp, 'follow_up.assigned.' . $followUp->id);
            }
        });

        return redirect()->route('crm.follow-ups.index')->with('success', 'Follow-Up created and reminder scheduled.');
    }

    public function show(FollowUp $followUp)
    {
        $this->authorize('view', $followUp);
        $followUp->load(['customer', 'opportunity', 'assignedUser', 'creator', 'reminders', 'histories.actor']);

        return view('crm.follow_ups.show', compact('followUp'));
    }

    public function edit(FollowUp $followUp, Request $request)
    {
        $this->authorize('update', $followUp);
        [$customers, $opportunities, $users] = $this->formOptions($request->user());

        return view('crm.follow_ups.edit', compact('followUp', 'customers', 'opportunities', 'users'));
    }

    public function update(Request $request, FollowUp $followUp)
    {
        $this->authorize('update', $followUp);
        $validated = $request->validate([
            'customer_id' => 'nullable|required_without:opportunity_id|exists:customers,id',
            'opportunity_id' => 'nullable|required_without:customer_id|exists:opportunities,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'follow_up_date' => 'required|date|after_or_equal:today',
            'follow_up_time' => 'nullable|date_format:H:i',
            'type' => 'required|string|max:60',
            'priority' => 'required|in:LOW,NORMAL,HIGH,URGENT',
            'assigned_to' => 'required|exists:users,id',
            'recurrence_rule' => 'required|in:NONE,DAILY,WEEKLY,MONTHLY',
            'recurrence_until' => 'nullable|date|after_or_equal:follow_up_date',
        ]);

        $user = $request->user();
        $customer = !empty($validated['customer_id']) ? Customer::findOrFail($validated['customer_id']) : null;
        $opportunity = !empty($validated['opportunity_id']) ? Opportunity::findOrFail($validated['opportunity_id']) : null;
        $this->authorizeLinkedRecords($user, $customer, $opportunity);
        if ($opportunity && $customer && $opportunity->customer_id !== $customer->id) {
            return back()->withErrors(['opportunity_id' => 'The opportunity must belong to the selected customer.'])->withInput();
        }

        $assigneeId = $this->resolveAssignee($user, $validated['assigned_to']);
        $oldAssignee = $followUp->assigned_to;
        $wasRescheduled = $followUp->follow_up_date->toDateString() !== $validated['follow_up_date']
            || $followUp->follow_up_time !== ($validated['follow_up_time'] ?? null);

        DB::transaction(function () use ($validated, $followUp, $customer, $opportunity, $assigneeId, $oldAssignee, $wasRescheduled, $user) {
            $previousStatus = $followUp->status;
            $followUp->update([
                'customer_id' => $customer?->id ?? $opportunity?->customer_id,
                'opportunity_id' => $opportunity?->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'follow_up_date' => $validated['follow_up_date'],
                'follow_up_time' => $validated['follow_up_time'] ?? null,
                'type' => $validated['type'],
                'priority' => $validated['priority'],
                'assigned_to' => $assigneeId,
                'recurrence_rule' => $validated['recurrence_rule'],
                'recurrence_until' => $validated['recurrence_until'] ?? null,
                'status' => $wasRescheduled && $previousStatus === 'OVERDUE' ? 'PLANNED' : $previousStatus,
                'due_notified_at' => $wasRescheduled ? null : $followUp->due_notified_at,
            ]);

            $this->recordHistory($followUp, $wasRescheduled ? 'RESCHEDULED' : 'UPDATED', $previousStatus, $previousStatus, $wasRescheduled ? 'Schedule changed.' : 'Follow-Up details updated.');
            $followUp->reminders()->whereIn('status', ['PENDING', 'SNOOZED', 'OVERDUE'])->update([
                'user_id' => $assigneeId,
                'customer_id' => $followUp->customer_id,
                'opportunity_id' => $followUp->opportunity_id,
                'title' => $followUp->title,
                'reminder_date' => $followUp->follow_up_date,
                'reminder_time' => $followUp->follow_up_time,
                'status' => 'PENDING',
                'snoozed_until' => null,
                'due_notified_at' => null,
                'overdue_notified_at' => null,
            ]);

            if ($assigneeId !== $oldAssignee) {
                $this->notify($assigneeId, 'FOLLOW_UP_REASSIGNED', 'Follow-Up reassigned', "{$followUp->title} has been assigned to you.", $followUp, 'follow_up.reassigned.' . $followUp->id . '.' . $followUp->updated_at->timestamp);
            } elseif ($wasRescheduled) {
                $this->notify($assigneeId, 'FOLLOW_UP_RESCHEDULED', 'Follow-Up rescheduled', "{$followUp->title} has been rescheduled.", $followUp, 'follow_up.rescheduled.' . $followUp->id . '.' . $followUp->updated_at->timestamp);
            }
        });

        return redirect()->route('crm.follow-ups.show', $followUp)->with('success', 'Follow-Up updated.');
    }

    public function start(FollowUp $followUp)
    {
        $this->authorize('perform', $followUp);
        abort_unless($followUp->status === 'PLANNED', 422, 'Only planned Follow-Ups can be started.');

        $followUp->update(['status' => 'IN_PROGRESS']);
        $this->recordHistory($followUp, 'STARTED', 'PLANNED', 'IN_PROGRESS');

        return back()->with('success', 'Follow-Up is now in progress.');
    }

    public function complete(Request $request, FollowUp $followUp)
    {
        $this->authorize('perform', $followUp);
        abort_unless(in_array($followUp->status, ['IN_PROGRESS', 'OVERDUE'], true), 422, 'Start the Follow-Up before completing it.');
        $validated = $request->validate(['completion_notes' => 'nullable|string|max:10000']);
        $previousStatus = $followUp->status;

        DB::transaction(function () use ($followUp, $validated, $request, $previousStatus) {
            $followUp = FollowUp::query()->whereKey($followUp->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($followUp->status, ['IN_PROGRESS', 'OVERDUE'], true), 422, 'This Follow-Up has already been completed.');
            $previousStatus = $followUp->status;
            $followUp->update([
                'status' => 'COMPLETED',
                'completed_at' => now(),
                'notes' => $validated['completion_notes'] ?? $followUp->notes,
            ]);
            $this->recordHistory($followUp, 'COMPLETED', $previousStatus, 'COMPLETED', $validated['completion_notes'] ?? null);
            $followUp->reminders()->whereIn('status', ['PENDING', 'SNOOZED', 'OVERDUE'])->update(['status' => 'COMPLETED']);

            $notificationService = app(CrmNotificationService::class);
            $notificationService->notifyUsers(
                $notificationService->departmentManagerIds($followUp->assignedUser?->department_id, $request->user()->id),
                'FOLLOW_UP_COMPLETED',
                'Follow-Up completed',
                "{$followUp->title} has been completed.",
                $followUp,
                'follow_up.completed.' . $followUp->id,
            );

            $this->createNextOccurrence($followUp, $request->user());
        });

        return back()->with('success', 'Follow-Up completed and activity history saved.');
    }

    public function cancel(FollowUp $followUp)
    {
        $this->authorize('perform', $followUp);
        abort_unless($followUp->status === 'PLANNED', 422, 'Only planned Follow-Ups can be cancelled.');

        DB::transaction(function () use ($followUp) {
            $followUp->update(['status' => 'CANCELLED']);
            $this->recordHistory($followUp, 'CANCELLED', 'PLANNED', 'CANCELLED');
            $followUp->reminders()->whereIn('status', ['PENDING', 'SNOOZED', 'OVERDUE'])->update(['status' => 'CANCELLED']);
        });

        return back()->with('success', 'Follow-Up cancelled.');
    }

    public function reschedule(Request $request, FollowUp $followUp)
    {
        $this->authorize('perform', $followUp);
        abort_unless(in_array($followUp->status, ['PLANNED', 'IN_PROGRESS', 'OVERDUE'], true), 422, 'This Follow-Up cannot be rescheduled.');
        $validated = $request->validate([
            'follow_up_date' => 'required|date|after_or_equal:today',
            'follow_up_time' => 'nullable|date_format:H:i',
            'reschedule_note' => 'nullable|string|max:2000',
        ]);

        $previousStatus = $followUp->status;
        DB::transaction(function () use ($followUp, $validated, $previousStatus, $request) {
            $followUp->update([
                'follow_up_date' => $validated['follow_up_date'],
                'follow_up_time' => $validated['follow_up_time'] ?? null,
                'status' => 'PLANNED',
                'due_notified_at' => null,
            ]);
            $this->recordHistory($followUp, 'RESCHEDULED', $previousStatus, 'PLANNED', $validated['reschedule_note'] ?? null);
            $followUp->reminders()->whereIn('status', ['PENDING', 'SNOOZED', 'OVERDUE'])->update([
                'reminder_date' => $followUp->follow_up_date,
                'reminder_time' => $followUp->follow_up_time,
                'status' => 'PENDING',
                'snoozed_until' => null,
                'due_notified_at' => null,
                'overdue_notified_at' => null,
            ]);
            $this->notify($followUp->assigned_to, 'FOLLOW_UP_RESCHEDULED', 'Follow-Up rescheduled', "{$followUp->title} has been rescheduled.", $followUp, 'follow_up.rescheduled.' . $followUp->id . '.' . $followUp->updated_at->timestamp);
        });

        return back()->with('success', 'Follow-Up rescheduled.');
    }

    public function destroy(FollowUp $followUp)
    {
        $this->authorize('delete', $followUp);
        $followUp->delete();

        return redirect()->route('crm.follow-ups.index')->with('success', 'Follow-Up deleted.');
    }

    private function formOptions(User $user): array
    {
        $customers = Customer::query()->manageableBy($user);
        $opportunities = Opportunity::query()->where('status', 'OPEN');
        $users = User::query()->where('status', 'ACTIVE');

        if (!$user->isAdmin()) {
            if ($user->isManager() && $user->department_id) {
                $opportunities->whereHas('assignedUser', fn ($assigned) => $assigned->where('department_id', $user->department_id));
                $users->where('department_id', $user->department_id);
            } else {
                $opportunities->where('assigned_to', $user->id);
                $users->whereKey($user->id);
            }
        }

        return [$customers->get(), $opportunities->get(), $users->get()];
    }

    private function resolveAssignee(User $actor, ?int $requestedId): int
    {
        if (!$actor->isManager()) {
            return $actor->id;
        }

        $assignee = User::whereKey($requestedId ?: $actor->id)->where('status', 'ACTIVE')->firstOrFail();
        abort_unless($actor->isAdmin() || $assignee->department_id === $actor->department_id, 403);

        return $assignee->id;
    }

    private function authorizeLinkedRecords(User $user, ?Customer $customer, ?Opportunity $opportunity): void
    {
        if ($customer) {
            $this->authorize('manageActivities', $customer);
        }
        if ($opportunity) {
            $this->authorize('view', $opportunity);
        }
    }

    private function createLinkedReminder(FollowUp $followUp): Reminder
    {
        $reminder = $followUp->reminders()->create([
            'user_id' => $followUp->assigned_to,
            'customer_id' => $followUp->customer_id,
            'opportunity_id' => $followUp->opportunity_id,
            'title' => $followUp->title,
            'description' => $followUp->description,
            'reminder_date' => $followUp->follow_up_date,
            'reminder_time' => $followUp->follow_up_time,
            'status' => 'PENDING',
            'is_notified' => false,
            'remindable_type' => FollowUp::class,
            'remindable_id' => $followUp->id,
        ]);
        $this->recordHistory($reminder, 'CREATED', null, 'PENDING', 'Created from Follow-Up.');

        return $reminder;
    }

    private function createNextOccurrence(FollowUp $followUp, User $actor): void
    {
        if ($followUp->recurrence_rule === 'NONE' || $followUp->next_occurrence_created_at) {
            return;
        }

        $nextDate = match ($followUp->recurrence_rule) {
            'DAILY' => $followUp->follow_up_date->copy()->addDay(),
            'WEEKLY' => $followUp->follow_up_date->copy()->addWeek(),
            'MONTHLY' => $followUp->follow_up_date->copy()->addMonthNoOverflow(),
            default => null,
        };

        if (!$nextDate || ($followUp->recurrence_until && $nextDate->gt($followUp->recurrence_until))) {
            return;
        }

        $followUp->update(['next_occurrence_created_at' => now()]);
        $next = FollowUp::create([
            'customer_id' => $followUp->customer_id,
            'opportunity_id' => $followUp->opportunity_id,
            'title' => $followUp->title,
            'description' => $followUp->description,
            'follow_up_date' => $nextDate,
            'follow_up_time' => $followUp->follow_up_time,
            'type' => $followUp->type,
            'priority' => $followUp->priority,
            'status' => 'PLANNED',
            'assigned_to' => $followUp->assigned_to,
            'created_by' => $actor->id,
            'recurrence_rule' => $followUp->recurrence_rule,
            'recurrence_until' => $followUp->recurrence_until,
            'recurrence_parent_id' => $followUp->recurrence_parent_id ?? $followUp->id,
        ]);
        $this->recordHistory($next, 'CREATED', null, 'PLANNED', 'Next recurring occurrence created.');
        $this->createLinkedReminder($next);
    }

    private function recordHistory(FollowUp|Reminder $activity, string $action, ?string $from, ?string $to, ?string $notes = null): void
    {
        $activity->histories()->create([
            'changed_by' => auth()->id(),
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
        ]);
    }

    private function notify(?int $userId, string $type, string $title, string $message, FollowUp|Reminder $activity, ?string $eventKey = null): void
    {
        if (!$userId) {
            return;
        }

        app(CrmNotificationService::class)->notifyUser($userId, $type, $title, $message, $activity, $eventKey);
    }
}
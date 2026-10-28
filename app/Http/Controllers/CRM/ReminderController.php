<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Document;
use App\Models\FollowUp;
use App\Models\Notification;
use App\Models\Opportunity;
use App\Models\Reminder;
use App\Models\Sale;
use App\Models\User;
use App\Services\CrmNotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReminderController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $reminders = Reminder::query()->visibleTo($request->user())
            ->whereNull('employee_id')
            ->whereNull('reminder_type')
            ->with(['customer', 'opportunity', 'followUp', 'user'])
            ->orderBy('reminder_date')->orderBy('reminder_time')
            ->paginate(15)->withQueryString();

        return view('crm.reminders.index', compact('reminders'));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $customers = Customer::query()->manageableBy($user);
        $opportunities = Opportunity::query();
        $followUps = FollowUp::query()->visibleTo($user)->whereIn('status', ['PLANNED', 'IN_PROGRESS', 'OVERDUE']);

        if (!$user->isAdmin()) {
            if ($user->isManager() && $user->department_id) {
                $opportunities->whereHas('assignedUser', fn ($assigned) => $assigned->where('department_id', $user->department_id));
            } else {
                $opportunities->where('assigned_to', $user->id);
            }
        }

        $customers = $customers->get();
        $opportunities = $opportunities->get();
        $followUps = $followUps->get();
        $sales = Sale::query()->with('customer');
        if (!$user->isAdmin()) {
            if ($user->isManager() && $user->isSales() && $user->department_id) {
                $sales->where(function ($visible) use ($user) {
                    $visible->whereHas('assignedUser', fn ($assigned) => $assigned->where('department_id', $user->department_id))
                        ->orWhereHas('customer.assignedUser', fn ($assigned) => $assigned->where('department_id', $user->department_id));
                });
            } else {
                $sales->where('assigned_to', $user->id);
            }
        }
        $sales = $sales->get(['id', 'sale_number', 'customer_id', 'opportunity_id']);
        $documents = Document::query()->visibleTo($user)->with('documentable')
            ->get(['id', 'document_number', 'title', 'documentable_type', 'documentable_id']);
        $linkedRecordOptions = [
            'sale' => $sales->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'label' => $sale->sale_number . ' · ' . ($sale->customer?->name ?? 'Unknown customer'),
                'customer_id' => $sale->customer_id,
                'opportunity_id' => $sale->opportunity_id,
            ])->values(),
            'document' => $documents->map(function (Document $document) {
                $relatedRecord = $document->documentable;

                return [
                    'id' => $document->id,
                    'label' => $document->document_number . ' · ' . $document->title,
                    'customer_id' => $relatedRecord instanceof Customer
                        ? $relatedRecord->id
                        : ($relatedRecord instanceof Opportunity || $relatedRecord instanceof Sale
                            ? $relatedRecord->customer_id
                            : null),
                    'opportunity_id' => $relatedRecord instanceof Opportunity
                        ? $relatedRecord->id
                        : ($relatedRecord instanceof Sale ? $relatedRecord->opportunity_id : null),
                ];
            })->values(),
        ];
        $users = User::query()->where('status', 'ACTIVE');
        if (!$user->isAdmin()) {
            $users->where('department_id', $user->department_id);
        }
        $users = $users->get();

        return view('crm.reminders.create', compact('customers', 'opportunities', 'followUps', 'linkedRecordOptions', 'users'));
    }

    public function store(Request $request)
    {
        $actor = $request->user();
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'reminder_type' => 'prohibited',
            'employee_id' => 'prohibited',
            'customer_id' => 'nullable|exists:customers,id',
            'opportunity_id' => 'nullable|exists:opportunities,id',
            'follow_up_id' => 'nullable|exists:follow_ups,id',
            'remindable_type' => 'nullable|in:customer,opportunity,sale,document',
            'remindable_id' => 'nullable|integer|required_with:remindable_type',
            'reminder_date' => 'required|date|after_or_equal:today',
            'reminder_time' => 'nullable|date_format:H:i',
            'user_id' => 'nullable|exists:users,id',
        ]);
        $customer = !empty($validated['customer_id']) ? Customer::findOrFail($validated['customer_id']) : null;
        $opportunity = !empty($validated['opportunity_id']) ? Opportunity::findOrFail($validated['opportunity_id']) : null;
        $followUp = !empty($validated['follow_up_id']) ? FollowUp::findOrFail($validated['follow_up_id']) : null;
        if ($customer) {
            $this->authorize('manageActivities', $customer);
        }
        if ($opportunity) {
            $this->authorize('view', $opportunity);
        }
        if ($followUp) {
            $this->authorize('view', $followUp);
        }
        if ($customer && $opportunity && $opportunity->customer_id !== $customer->id) {
            return back()->withErrors(['opportunity_id' => 'The opportunity must belong to the selected customer.'])->withInput();
        }
        if ($followUp && (($customer && $customer->id !== $followUp->customer_id)
            || ($opportunity && $opportunity->id !== $followUp->opportunity_id))) {
            throw ValidationException::withMessages([
                'follow_up_id' => 'The selected follow-up does not match the selected customer or opportunity.',
            ]);
        }

        $related = $this->resolveRelatedRecord($validated['remindable_type'] ?? null, $validated['remindable_id'] ?? null);
        if ($related) {
            $this->authorize($related instanceof Customer ? 'manageActivities' : 'view', $related);
        }

        if ($followUp && $related instanceof Sale
            && ($followUp->customer_id !== $related->customer_id
                || ($followUp->opportunity_id && $followUp->opportunity_id !== $related->opportunity_id))) {
            throw ValidationException::withMessages([
                'remindable_id' => 'The selected sale does not match the linked follow-up.',
            ]);
        }

        if ($related instanceof Sale) {
            if (($customer && $customer->id !== $related->customer_id)
                || ($opportunity && $opportunity->id !== $related->opportunity_id)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'remindable_id' => 'The selected sale does not match the selected customer or opportunity.',
                ]);
            }
        } elseif ($related instanceof Opportunity && (($customer && $customer->id !== $related->customer_id)
            || ($opportunity && $opportunity->id !== $related->id))) {
            throw ValidationException::withMessages([
                'remindable_id' => 'The selected opportunity does not belong to the selected customer.',
            ]);
        } elseif ($related instanceof Customer && (($customer && $customer->id !== $related->id)
            || ($opportunity && $opportunity->customer_id !== $related->id))) {
            throw ValidationException::withMessages([
                'remindable_id' => 'The selected customer does not match the selected opportunity.',
            ]);
        }

        $assigneeId = $this->resolveAssignee($actor, $validated['user_id'] ?? null);
        $customerId = $customer?->id ?? $followUp?->customer_id ?? $opportunity?->customer_id;
        $opportunityId = $opportunity?->id ?? $followUp?->opportunity_id;
        if ($related instanceof Sale) {
            $customerId ??= $related->customer_id;
            $opportunityId ??= $related->opportunity_id;
        } elseif ($related instanceof Opportunity) {
            $customerId ??= $related->customer_id;
            $opportunityId ??= $related->id;
        } elseif ($related instanceof Customer) {
            $customerId ??= $related->id;
        }
        if ($followUp) {
            $assigneeId = $followUp->assigned_to;
            $customerId = $followUp->customer_id;
            $opportunityId = $followUp->opportunity_id;
            if ($related instanceof Sale) {
                $opportunityId ??= $related->opportunity_id;
            }
        }

        $reminder = Reminder::create([
            'user_id' => $assigneeId,
            'customer_id' => $customerId,
            'opportunity_id' => $opportunityId,
            'follow_up_id' => $followUp?->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'reminder_date' => $validated['reminder_date'],
            'reminder_time' => $validated['reminder_time'] ?? null,
            'status' => 'PENDING',
            'is_notified' => false,
            'remindable_type' => $related ? $related::class : ($followUp ? FollowUp::class : null),
            'remindable_id' => $related?->id ?? $followUp?->id,
        ]);
        $this->recordHistory($reminder, 'CREATED', null, 'PENDING', 'Reminder created.');

        if ($assigneeId !== $actor->id) {
            $this->notify($assigneeId, 'REMINDER_ASSIGNED', 'Reminder assigned', "{$reminder->title} has been assigned to you.", $reminder, 'reminder.assigned.' . $reminder->id);
        }

        return redirect()->route('crm.reminders.index')->with('success', 'Reminder created successfully.');
    }

    public function show(Reminder $reminder)
    {
        $this->authorize('view', $reminder);
        $reminder->load(['user', 'customer', 'opportunity', 'followUp', 'histories.actor', 'remindable']);

        return view('crm.reminders.show', compact('reminder'));
    }

    public function edit(Reminder $reminder)
    {
        $this->authorize('update', $reminder);
        $user = auth()->user();
        $customers = Customer::query()->manageableBy($user)->get();
        $opportunities = Opportunity::query();
        if (!$user->isAdmin()) {
            if ($user->isManager() && $user->department_id) {
                $opportunities->whereHas('assignedUser', fn ($assigned) => $assigned->where('department_id', $user->department_id));
            } else {
                $opportunities->where('assigned_to', $user->id);
            }
        }
        $opportunities = $opportunities->get();

        return view('crm.reminders.edit', compact('reminder', 'customers', 'opportunities'));
    }

    public function update(Request $request, Reminder $reminder)
    {
        $this->authorize('update', $reminder);
        $actor = $request->user();
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'reminder_date' => 'required|date|after_or_equal:today',
            'reminder_time' => 'nullable|date_format:H:i',
            'customer_id' => 'nullable|exists:customers,id',
            'opportunity_id' => 'nullable|exists:opportunities,id',
            'reminder_type' => 'prohibited',
            'employee_id' => 'prohibited',
            'user_id' => 'prohibited',
        ]);
        if (!empty($validated['customer_id'])) {
            $this->authorize('manageActivities', Customer::findOrFail($validated['customer_id']));
        }
        if (!empty($validated['opportunity_id'])) {
            $this->authorize('view', Opportunity::findOrFail($validated['opportunity_id']));
        }

        $previousStatus = $reminder->status;
        $previousAssigneeId = $reminder->user_id;
        $reminder->update($validated);
        if ($reminder->wasChanged(['reminder_date', 'reminder_time'])) {
            $reminder->update(['status' => 'PENDING', 'snoozed_until' => null, 'due_notified_at' => null, 'overdue_notified_at' => null]);
            $this->recordHistory($reminder, 'RESCHEDULED', $previousStatus, 'PENDING', 'Reminder rescheduled.');
            $this->notify($reminder->user_id, 'REMINDER_RESCHEDULED', 'Reminder rescheduled', "{$reminder->title} has been rescheduled.", $reminder, 'reminder.rescheduled.' . $reminder->id . '.' . $reminder->updated_at->timestamp);
        } elseif ($previousAssigneeId !== $reminder->user_id) {
            $this->recordHistory($reminder, 'REASSIGNED', $previousStatus, $previousStatus, 'Reminder assignee changed.');
            $this->notify($reminder->user_id, 'REMINDER_ASSIGNED', 'Reminder assigned', "{$reminder->title} has been assigned to you.", $reminder, 'reminder.reassigned.' . $reminder->id . '.' . $reminder->updated_at->timestamp);
        } else {
            $this->recordHistory($reminder, 'UPDATED', $previousStatus, $previousStatus, 'Reminder details updated.');
        }

        return redirect()->route('crm.reminders.index')
            ->with('success', 'Reminder updated successfully.');
    }

    public function complete(Reminder $reminder)
    {
        $this->authorize('update', $reminder);
        abort_unless(in_array($reminder->status, ['PENDING', 'SNOOZED', 'OVERDUE'], true), 422, 'This reminder cannot be completed.');
        $previousStatus = $reminder->status;
        $reminder->update(['status' => 'COMPLETED']);
        $this->recordHistory($reminder, 'COMPLETED', $previousStatus, 'COMPLETED');

        return back()->with('success', 'Reminder marked as completed.');
    }

    public function snooze(Request $request, Reminder $reminder)
    {
        $this->authorize('update', $reminder);
        abort_unless(in_array($reminder->status, ['PENDING', 'SNOOZED', 'OVERDUE'], true), 422, 'This reminder cannot be snoozed.');
        $validated = $request->validate([
            'reminder_date' => 'required|date|after_or_equal:today',
            'reminder_time' => 'nullable|date_format:H:i',
            'snooze_note' => 'nullable|string|max:2000',
        ]);

        $previousStatus = $reminder->status;
        $reminder->update([
            'reminder_date' => $validated['reminder_date'],
            'reminder_time' => $validated['reminder_time'] ?? null,
            'status' => 'SNOOZED',
            'snoozed_until' => Carbon::parse($validated['reminder_date'] . ' ' . ($validated['reminder_time'] ?? '23:59:59')),
            'due_notified_at' => null,
            'overdue_notified_at' => null,
        ]);
        $this->recordHistory($reminder, 'SNOOZED', $previousStatus, 'SNOOZED', $validated['snooze_note'] ?? null);

        return back()->with('success', 'Reminder snoozed.');
    }

    public function reschedule(Request $request, Reminder $reminder)
    {
        $this->authorize('update', $reminder);
        abort_unless(in_array($reminder->status, ['PENDING', 'SNOOZED', 'OVERDUE'], true), 422, 'This reminder cannot be rescheduled.');
        $validated = $request->validate([
            'reminder_date' => 'required|date|after_or_equal:today',
            'reminder_time' => 'nullable|date_format:H:i',
            'reschedule_note' => 'nullable|string|max:2000',
        ]);

        $previousStatus = $reminder->status;
        $reminder->update([
            'reminder_date' => $validated['reminder_date'],
            'reminder_time' => $validated['reminder_time'] ?? null,
            'status' => 'PENDING',
            'snoozed_until' => null,
            'due_notified_at' => null,
            'overdue_notified_at' => null,
        ]);
        $this->recordHistory($reminder, 'RESCHEDULED', $previousStatus, 'PENDING', $validated['reschedule_note'] ?? null);
        $this->notify($reminder->user_id, 'REMINDER_RESCHEDULED', 'Reminder rescheduled', "{$reminder->title} has been rescheduled.", $reminder, 'reminder.rescheduled.' . $reminder->id . '.' . $reminder->updated_at->timestamp);

        return back()->with('success', 'Reminder rescheduled.');
    }

    public function cancel(Reminder $reminder)
    {
        $this->authorize('update', $reminder);
        abort_unless(in_array($reminder->status, ['PENDING', 'SNOOZED', 'OVERDUE'], true), 422, 'This reminder cannot be cancelled.');
        $previousStatus = $reminder->status;
        $reminder->update(['status' => 'CANCELLED']);
        $this->recordHistory($reminder, 'CANCELLED', $previousStatus, 'CANCELLED');

        return back()->with('success', 'Reminder cancelled.');
    }

    public function destroy(Reminder $reminder)
    {
        $this->authorize('delete', $reminder);
        $reminder->delete();

        return redirect()->route('crm.reminders.index')->with('success', 'Reminder deleted successfully.');
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

    private function resolveRelatedRecord(?string $type, ?int $id): Customer|Opportunity|Sale|Document|null
    {
        if (!$type || !$id) {
            return null;
        }

        $model = match ($type) {
            'customer' => Customer::class,
            'opportunity' => Opportunity::class,
            'sale' => Sale::class,
            'document' => Document::class,
        };

        return $model::query()->find($id)
            ?? throw ValidationException::withMessages(['remindable_id' => 'Select a valid CRM record.']);
    }

    private function recordHistory(Reminder $reminder, string $action, ?string $from, ?string $to, ?string $notes = null): void
    {
        $reminder->histories()->create([
            'changed_by' => auth()->id(),
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
        ]);
    }

    private function notify(int $userId, string $type, string $title, string $message, Reminder $reminder, ?string $eventKey = null): void
    {
        app(CrmNotificationService::class)->notifyUser($userId, $type, $title, $message, $reminder, $eventKey);
    }
}
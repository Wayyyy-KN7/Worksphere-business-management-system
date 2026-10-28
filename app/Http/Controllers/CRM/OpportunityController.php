<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\Customer;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\CrmNotificationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpportunityController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of opportunities.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $this->authorize('viewAny', Opportunity::class);

        $query = $this->visibleOpportunitiesQuery($user);
        $statsQuery = clone $query;

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('opportunity_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by stage
        if ($request->filled('stage')) {
            $query->where('stage', $request->stage);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by assigned user
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        $opportunities = $query->latest()->paginate(15);

        // Get filter options
        $assignedUsers = $this->assignableUsers($user);

        // Statistics use the same department/assignment scope as the listing.
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'open' => (clone $statsQuery)->where('status', 'OPEN')->count(),
            'won' => (clone $statsQuery)->where('stage', 'WON')->count(),
            'lost' => (clone $statsQuery)->where('stage', 'LOST')->count(),
        ];

        return view('crm.opportunities.index', compact('opportunities', 'assignedUsers', 'stats'));
    }

    private function visibleOpportunitiesQuery(User $user)
    {
        $query = Opportunity::query()->with(['customer', 'assignedUser']);
        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isSales() || $user->isMarketing()) {
            return $query->whereHas('assignedUser', function ($assigned) use ($user) {
                $assigned->where('department_id', $user->department_id);
            });
        }

        return $query->where('assigned_to', $user->id);
    }

    private function assignableUsers(User $user)
    {
        $query = User::where('status', 'ACTIVE')
            ->whereIn('role', ['SALES_MANAGER', 'SALES_EMPLOYEE', 'MARKETING_MANAGER', 'MARKETING_EMPLOYEE'])
            ->orderBy('name')
            ->get();

        return $query->filter(function (User $assignee) use ($user) {
            if ($user->isAdmin()) {
                return true;
            }
            if (!$assignee->isSales() && !$assignee->isMarketing()) {
                return false;
            }
            return $user->isManager()
                ? $assignee->department_id === $user->department_id
                : $assignee->id === $user->id;
        });
    }

    /**
     * Show the form for creating a new opportunity.
     */
    public function create()
    {
        $this->authorize('create', Opportunity::class);
        $user = auth()->user();
        $customers = $this->manageableCustomersQuery($user)->where('status', '!=', 'INACTIVE')
            ->orderBy('name')
            ->get();
        $assignableUsers = $this->assignableUsers($user);

        return view('crm.opportunities.create', compact('customers', 'assignableUsers'));
    }

    /**
     * Store a newly created opportunity in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Opportunity::class);
        $validated = $request->validate([
            'title'               => 'required|string|max:255',
            'customer_id'         => 'required|exists:customers,id',
            'estimated_value'     => 'required|numeric|min:0',
            'probability'         => 'required|integer|min:0|max:100',
            'expected_close_date' => 'required|date',
            'stage'               => 'required|in:NEW,QUALIFIED,PROPOSAL,NEGOTIATION,WON,LOST',
            'status'              => 'required|in:OPEN,CLOSED,CANCELLED',
            'assigned_to'         => ['required', 'exists:users,id,status,ACTIVE'],
            'description'         => 'nullable|string',
        ]);
        $customer = Customer::findOrFail($validated['customer_id']);
        $this->authorize('manageActivities', $customer);
        $this->authorize('assignTo', [Opportunity::class, User::findOrFail($validated['assigned_to'])]);

        DB::transaction(function () use ($validated, &$opportunity) {
            $validated['opportunity_number'] = 'OPP-' . strtoupper(uniqid());
            
            $opportunity = Opportunity::create($validated);

            if (class_exists(AuditLog::class)) {
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'CREATED',
                    'table_name' => 'opportunities',
                    'module' => 'Opportunities',
                    'record_id' => $opportunity->id,
                    'description' => "Opportunity {$opportunity->opportunity_number} created.",
                    'new_values' => $opportunity->only(['opportunity_number', 'customer_id', 'title', 'estimated_value', 'probability', 'stage', 'status', 'assigned_to']),
                    'ip_address' => request()->ip(),
                ]);
            }
        });

        if ($opportunity->assigned_to !== auth()->id()) {
            app(CrmNotificationService::class)->notifyUser(
                $opportunity->assigned_to,
                'OPPORTUNITY_ASSIGNED',
                'Opportunity Assigned',
                "{$opportunity->title} was assigned to you.",
                $opportunity,
                'opportunity.assigned.' . $opportunity->id,
            );
        }

        return redirect()
            ->route('crm.opportunities.index')
            ->with('success', 'Opportunity created successfully.');
    }

    /**
     * Display the specified opportunity.
     */
    public function show(Opportunity $opportunity)
    {
        $this->authorize('view', $opportunity);
        $opportunity->load(['customer', 'assignedUser']);

        return view('crm.opportunities.show', compact('opportunity'));
    }

    /**
     * Show the form for editing the specified opportunity.
     */
    public function edit(Opportunity $opportunity)
    {
        $this->authorize('update', $opportunity);
        $user = auth()->user();
        $customers = $this->manageableCustomersQuery($user)->where('status', '!=', 'INACTIVE')
            ->orderBy('name')
            ->get();
        $assignableUsers = $this->assignableUsers($user);

        return view('crm.opportunities.edit', compact('opportunity', 'customers', 'assignableUsers'));
    }

    /**
     * Update the specified opportunity in storage.
     */
    public function update(Request $request, Opportunity $opportunity)
    {
        $this->authorize('update', $opportunity);
        $validated = $request->validate([
            'title'               => 'required|string|max:255',
            'customer_id'         => 'required|exists:customers,id',
            'estimated_value'     => 'required|numeric|min:0',
            'probability'         => 'required|integer|min:0|max:100',
            'expected_close_date' => 'required|date',
            'stage'               => 'required|in:NEW,QUALIFIED,PROPOSAL,NEGOTIATION,WON,LOST',
            'status'              => 'required|in:OPEN,CLOSED,CANCELLED',
            'assigned_to'         => ['required', 'exists:users,id,status,ACTIVE'],
            'description'         => 'nullable|string',
        ]);
        $customer = Customer::findOrFail($validated['customer_id']);
        $this->authorize('manageActivities', $customer);
        $this->authorize('assignTo', [Opportunity::class, User::findOrFail($validated['assigned_to'])]);

        $oldAssigneeId = $opportunity->assigned_to;
        $oldStage = $opportunity->stage;
        $oldValues = $opportunity->only(['title', 'customer_id', 'estimated_value', 'probability', 'expected_close_date', 'stage', 'status', 'assigned_to', 'description']);
        $opportunity->update($validated);
        AuditLog::log(
            'UPDATED',
            'opportunities',
            $opportunity->id,
            "Updated opportunity: {$opportunity->opportunity_number}",
            $oldValues,
            $opportunity->only(['title', 'customer_id', 'estimated_value', 'probability', 'expected_close_date', 'stage', 'status', 'assigned_to', 'description']),
        );

        $notificationService = app(CrmNotificationService::class);
        if ($oldAssigneeId !== $opportunity->assigned_to && $opportunity->assigned_to !== auth()->id()) {
            $notificationService->notifyUser(
                $opportunity->assigned_to,
                'OPPORTUNITY_REASSIGNED',
                'Opportunity Reassigned',
                "{$opportunity->title} was reassigned to you.",
                $opportunity,
                'opportunity.reassigned.' . $opportunity->id . '.' . $opportunity->updated_at->timestamp,
            );
        }
        $this->notifyImportantStageChange($notificationService, $opportunity, $oldStage);

        return redirect()
            ->route('crm.opportunities.index')
            ->with('success', 'Opportunity updated successfully.');
    }

    /**
     * Remove the specified opportunity from storage.
     */
    public function destroy(Opportunity $opportunity)
    {
        $this->authorize('delete', $opportunity);
        $opportunity->delete();

        return redirect()
            ->route('crm.opportunities.index')
            ->with('success', 'Opportunity deleted successfully.');
    }
    /**
     * Update the opportunity stage (e.g. advance pipeline or mark WON/LOST).
     */
    public function updateStage(Request $request, Opportunity $opportunity)
    {
        $this->authorize('update', $opportunity);
        $validated = $request->validate([
            'stage' => 'required|in:NEW,QUALIFIED,PROPOSAL,NEGOTIATION,WON,LOST',
            'lost_reason' => 'required_if:stage,LOST|nullable|string',
        ]);

        $oldStage = $opportunity->stage;
        $oldStatus = $opportunity->status;
        $opportunity->stage = $validated['stage'];

        if ($validated['stage'] === 'WON') {
            $opportunity->status = 'CLOSED';
            $opportunity->actual_close_date = now();
        } elseif ($validated['stage'] === 'LOST') {
            $opportunity->status = 'CLOSED';
            $opportunity->lost_reason = $validated['lost_reason'];
            $opportunity->actual_close_date = now();
        }

        $opportunity->save();
        AuditLog::log(
            'UPDATED',
            'opportunities',
            $opportunity->id,
            "Opportunity stage changed from {$oldStage} to {$opportunity->stage}.",
            ['stage' => $oldStage, 'status' => $oldStatus],
            ['stage' => $opportunity->stage, 'status' => $opportunity->status, 'lost_reason' => $opportunity->lost_reason],
        );
        $this->notifyImportantStageChange(app(CrmNotificationService::class), $opportunity, $oldStage);

        // If WON, redirect straight to Sale Creation pre-filled with this opportunity
        if ($opportunity->stage === 'WON') {
            return redirect()
                ->route('crm.sales.create', ['opportunity_id' => $opportunity->id])
                ->with('success', 'Opportunity won! Please generate the sale transaction record.');
        }

        return redirect()
            ->back()
            ->with('success', 'Opportunity stage updated successfully.');
    }

    private function notifyImportantStageChange(CrmNotificationService $notifications, Opportunity $opportunity, string $oldStage): void
    {
        if ($oldStage === $opportunity->stage || !in_array($opportunity->stage, ['PROPOSAL', 'NEGOTIATION', 'WON', 'LOST'], true)) {
            return;
        }

        $departmentId = $opportunity->assignedUser?->department_id;
        $notifications->notifyUsers(
            $notifications->departmentManagerIds($departmentId, auth()->id()),
            'OPPORTUNITY_STAGE_CHANGED',
            'Important Opportunity Stage Changed',
            "{$opportunity->title} moved from {$oldStage} to {$opportunity->stage}.",
            $opportunity,
            'opportunity.stage.' . $opportunity->id . '.' . $opportunity->updated_at->timestamp,
        );
    }

    private function manageableCustomersQuery(User $user)
    {
        return Customer::query()->manageableBy($user);
    }
}
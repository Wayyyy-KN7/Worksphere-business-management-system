<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Customer;
use App\Models\CustomerSource;
use App\Models\User;
use App\Models\Department;
use App\Models\AuditLog;
use App\Services\CrmNotificationService;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $user = auth()->user();
        $this->authorize('viewAny', Customer::class);
        $query = $this->visibleCustomersQuery($user)->with([
            'creator', 'creatorDepartment', 'assignedDepartment',
        ]);
        $this->applyCustomerFilters($query, $request);

        $customers = $query->latest()->paginate(15);
        $sources = CustomerSource::all();
        $assignedUsers = $this->visibleAssignedUsers($user);
        $departments = $this->visibleAssignedDepartments($user);
        $creators = $this->visibleCreators($user);
        $archived = false;

        return view('crm.customers.index', compact('customers', 'sources', 'assignedUsers', 'departments', 'creators', 'archived'));
    }

    public function archived(Request $request)
    {
        $user = auth()->user();
        $this->authorize('viewAny', Customer::class);
        $query = $this->visibleCustomersQuery($user)->with([
            'creator', 'creatorDepartment', 'assignedDepartment',
        ])->onlyTrashed();
        $this->applyCustomerFilters($query, $request);

        $customers = $query->latest('deleted_at')->paginate(15);
        $sources = CustomerSource::all();
        $assignedUsers = $this->visibleAssignedUsers($user);
        $departments = $this->visibleAssignedDepartments($user);
        $creators = $this->visibleCreators($user);
        $archived = true;

        return view('crm.customers.index', compact('customers', 'sources', 'assignedUsers', 'departments', 'creators', 'archived'));
    }

    public function create()
    {
        $this->authorize('create', Customer::class);
        $sources = CustomerSource::all();
        $user = auth()->user();
        $assignableUsers = $this->assignableUsers($user);
        $departments = $this->assignableDepartments($user);

        return view('crm.customers.create', compact('sources', 'assignableUsers', 'departments'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Customer::class);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'customer_type' => 'nullable|string|max:100',
            'source_id' => 'nullable|exists:customer_sources,id',
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('status', 'ACTIVE')],
            'assigned_department_id' => ['nullable', Rule::exists('departments', 'id')->where('status', 'ACTIVE')],
            'status' => 'required|in:ACTIVE,INACTIVE,PROSPECT,LEAD',
            'notes' => 'nullable|string',
        ]);

        $user = $request->user();
        $validated = array_merge($validated, $this->resolveAssignment($user, $validated));
        $validated['created_by'] = $user->id;
        $validated['created_department_id'] = $user->department_id;

        $customer = $this->createCustomerWithUniqueCode($validated);

        AuditLog::log('CREATED', 'customers', $customer->id, 
            "Created customer: {$customer->name} ({$customer->customer_code})",
            null,
            $customer->only([
                'customer_code', 'name', 'company_name', 'email', 'phone',
                'created_by', 'created_department_id', 'assigned_department_id', 'assigned_to', 'status',
            ]));

        if ($customer->assigned_to && (int) $customer->assigned_to !== (int) $user->id) {
            app(CrmNotificationService::class)->notifyUser(
                $customer->assigned_to,
                'CUSTOMER_ASSIGNED',
                'Customer Assigned',
                "{$customer->name} was assigned to you.",
                $customer,
                'customer.assigned.' . $customer->id,
            );
        }

        return redirect()->route('crm.customers.index')
            ->with('success', "Customer created successfully. Customer Code: {$customer->customer_code}");
    }

    public function show(Customer $customer)
    {
        // Check access using Policy via AuthorizesRequests trait
        $this->authorize('view', $customer);

        $customer->load([
            'source', 
            'assignedUser.department', 
            'creator',
            'creatorDepartment',
            'assignedDepartment',
            'opportunities.assignedUser',
            'followUps' => function($query) {
                $query->latest()->limit(10);
            },
            'sales' => function($query) {
                $query->latest()->limit(10);
            }
        ]);

        return view('crm.customers.show', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        // Check access
        $this->authorize('update', $customer);

        $sources = CustomerSource::all();
        $user = auth()->user();
        $assignableUsers = $this->assignableUsers($user);
        $departments = $this->assignableDepartments($user);

        return view('crm.customers.edit', compact('customer', 'sources', 'assignableUsers', 'departments'));
    }

    public function update(Request $request, Customer $customer)
    {
        // Check access
        $this->authorize('update', $customer);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'customer_type' => 'nullable|string|max:100',
            'source_id' => 'nullable|exists:customer_sources,id',
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('status', 'ACTIVE')],
            'assigned_department_id' => ['nullable', Rule::exists('departments', 'id')->where('status', 'ACTIVE')],
            'status' => 'required|in:ACTIVE,INACTIVE,PROSPECT,LEAD',
            'notes' => 'nullable|string',
        ]);

        $user = $request->user();
        $assignment = $this->resolveAssignment($user, $validated, $customer);
        $oldAssignment = $customer->only(['assigned_department_id', 'assigned_to']);
        $oldValues = $customer->only([
            'name', 'company_name', 'email', 'phone', 'address', 'city', 'country',
            'customer_type', 'source_id', 'assigned_department_id', 'assigned_to', 'status', 'notes',
        ]);
        unset($validated['assigned_to'], $validated['assigned_department_id']);
        $validated = array_merge($validated, $assignment);
        $customer->update($validated);

        AuditLog::log('UPDATED', 'customers', $customer->id, 
            "Updated customer: {$customer->name}",
            $oldValues,
            $customer->only([
                'name', 'company_name', 'email', 'phone', 'address', 'city', 'country',
                'customer_type', 'source_id', 'assigned_department_id', 'assigned_to', 'status', 'notes',
            ]));

        if ($oldAssignment !== $customer->only(['assigned_department_id', 'assigned_to'])) {
            AuditLog::log(
                'UPDATED',
                'customers',
                $customer->id,
                ($oldAssignment['assigned_department_id'] || $oldAssignment['assigned_to'] ? 'Customer reassigned: ' : 'Customer assigned: ')
                    . "{$customer->name} ({$customer->customer_code})",
                $oldAssignment,
                $customer->only(['assigned_department_id', 'assigned_to']),
            );
        }

        if ($customer->assigned_to
            && (int) $customer->assigned_to !== (int) $user->id
            && (int) $customer->assigned_to !== (int) $oldAssignment['assigned_to']) {
            app(CrmNotificationService::class)->notifyUser(
                $customer->assigned_to,
                'CUSTOMER_REASSIGNED',
                'Customer Assigned to You',
                "{$customer->name} was assigned to you.",
                $customer,
                'customer.reassigned.' . $customer->id . '.' . $customer->assigned_to . '.' . $customer->updated_at->timestamp,
            );
        }

        return redirect()->route('crm.customers.index')
            ->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('archive', $customer);

        $oldValues = ['deleted_at' => null, 'status' => $customer->status];
        $customer->delete();

        AuditLog::log(
            'ARCHIVED',
            'customers',
            $customer->id,
            "Archived customer: {$customer->name} ({$customer->customer_code})",
            $oldValues,
            ['deleted_at' => $customer->deleted_at?->toDateTimeString(), 'status' => $customer->status],
        );

        return redirect()->route('crm.customers.index')
            ->with('success', 'Customer archived successfully. Related records have been retained.');
    }

    public function restore(int $customerId)
    {
        $customer = Customer::onlyTrashed()->findOrFail($customerId);
        $this->authorize('restore', $customer);

        $oldDeletedAt = $customer->deleted_at?->toDateTimeString();
        $customer->restore();

        AuditLog::log(
            'RESTORED',
            'customers',
            $customer->id,
            "Restored customer: {$customer->name} ({$customer->customer_code})",
            ['deleted_at' => $oldDeletedAt, 'status' => $customer->status],
            ['deleted_at' => null, 'status' => $customer->status],
        );

        return redirect()->route('crm.customers.archived')
            ->with('success', 'Customer restored successfully.');
    }

    private function visibleCustomersQuery(User $user)
    {
        return Customer::query()
            ->visibleTo($user)
            ->with(['source', 'assignedUser.department', 'creator', 'creatorDepartment', 'assignedDepartment']);
    }

    private function applyCustomerFilters($query, Request $request): void
    {
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($matches) use ($search) {
                $matches->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%");
            });
        }

        foreach ([
            'source' => 'source_id',
            'status' => 'status',
            'assigned_to' => 'assigned_to',
            'department' => 'assigned_department_id',
            'created_by' => 'created_by',
        ] as $filter => $column) {
            if ($request->filled($filter)) {
                $query->where($column, $request->input($filter));
            }
        }
    }

    private function assignableUsers(User $user)
    {
        return User::where('status', 'ACTIVE')
            ->where(function ($query) {
                $query->whereIn('role', ['SALES_MANAGER', 'SALES_EMPLOYEE', 'MARKETING_MANAGER', 'MARKETING_EMPLOYEE'])
                    ->orWhereHas('department', fn ($department) => $department->whereIn('code', ['SALES', 'MARKETING', 'MKT']));
            })
            ->with('department')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $assignee) => $this->canAssignToUser($user, $assignee))
            ->values();
    }

    private function assignableDepartments(User $user)
    {
        $query = Department::query()->where('status', 'ACTIVE')
            ->whereIn('code', ['SALES', 'MARKETING', 'MKT']);

        if (!$user->isAdmin() && $user->isSales()) {
            $query->where('code', 'SALES');
        } elseif (!$user->isAdmin() && $user->isMarketing()) {
            $query->where(function ($departments) use ($user) {
                $departments->where('code', 'SALES');
                if ($user->department_id) {
                    $departments->orWhereKey($user->department_id);
                }
            });
        }

        return $query->orderBy('name')->get();
    }

    private function visibleCreators(User $user)
    {
        $visibleCustomers = Customer::withTrashed()->visibleTo($user);

        return User::query()
            ->whereIn('id', (clone $visibleCustomers)->whereNotNull('created_by')->select('created_by'))
            ->orderBy('name')
            ->get();
    }

    private function visibleAssignedUsers(User $user)
    {
        $visibleCustomers = Customer::withTrashed()->visibleTo($user);

        return User::query()
            ->with('department')
            ->whereIn('id', (clone $visibleCustomers)->whereNotNull('assigned_to')->select('assigned_to'))
            ->orderBy('name')
            ->get();
    }

    private function visibleAssignedDepartments(User $user)
    {
        $visibleCustomers = Customer::withTrashed()->visibleTo($user);

        return Department::query()
            ->whereIn('id', (clone $visibleCustomers)->whereNotNull('assigned_department_id')->select('assigned_department_id'))
            ->orderBy('name')
            ->get();
    }

    private function resolveAssignment(User $actor, array $validated, ?Customer $customer = null): array
    {
        $targetId = array_key_exists('assigned_to', $validated)
            ? ($validated['assigned_to'] ? (int) $validated['assigned_to'] : null)
            : $customer?->assigned_to;
        $target = $targetId ? User::query()->where('status', 'ACTIVE')->findOrFail($targetId) : null;

        if ($target) {
            abort_unless($this->canAssignToUser($actor, $target), 403);
        }

        $requestedDepartmentId = array_key_exists('assigned_department_id', $validated)
            ? ($validated['assigned_department_id'] ? (int) $validated['assigned_department_id'] : null)
            : null;

        $departmentId = $target?->department_id
            ?? $requestedDepartmentId
            ?? ($customer ? $customer->assigned_department_id : $actor->department_id);

        if (!$customer && !$target && !$requestedDepartmentId) {
            $target = $actor;
            $targetId = $actor->id;
            $departmentId = $actor->department_id;
        }

        if ($target && (int) $target->department_id !== (int) $departmentId) {
            throw ValidationException::withMessages([
                'assigned_department_id' => 'The assigned department must match the selected salesperson.',
            ]);
        }

        if ($departmentId !== null) {
            $department = Department::query()->findOrFail($departmentId);
            abort_unless($this->canAssignToDepartment($actor, $department), 403);
        }

        return [
            'assigned_department_id' => $departmentId,
            'assigned_to' => $targetId,
        ];
    }

    private function canAssignToDepartment(User $actor, Department $department): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        if ($actor->isSales()) {
            return $department->code === 'SALES' && $department->id === $actor->department_id;
        }

        if ($actor->isMarketing()) {
            return $department->id === $actor->department_id
                || $department->code === 'SALES';
        }

        return false;
    }

    private function canAssignToUser(User $actor, User $assignee): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        if (!$assignee->isSales() && !$assignee->isMarketing()) {
            return false;
        }

        if (!$actor->isSales() && !$actor->isMarketing()) {
            return false;
        }

        if ($actor->isMarketing() && $assignee->isSales()) {
            return true;
        }

        return $assignee->department_id === $actor->department_id
            && ($assignee->id === $actor->id || $actor->isDepartmentManager());
    }

    /**
     * Generate unique customer code
     */
    private function generateCustomerCode(): string
    {
        $year = now()->format('y');
        $prefix = "CUST-{$year}-";

        $lastNumber = Customer::withTrashed()
            ->where('customer_code', 'like', "{$prefix}%")
            ->pluck('customer_code')
            ->map(function (string $customerCode) use ($prefix): int {
                $suffix = substr($customerCode, strlen($prefix));

                return ctype_digit($suffix) ? (int) $suffix : 0;
            })
            ->max() ?? 0;

        return $prefix . str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
    }

    private function createCustomerWithUniqueCode(array $attributes): Customer
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $attributes['customer_code'] = $this->generateCustomerCode();

            try {
                return Customer::create($attributes);
            } catch (QueryException $exception) {
                $message = strtolower($exception->getMessage());
                $isCustomerCodeCollision = str_contains($message, 'customer_code')
                    && (str_contains($message, 'unique') || str_contains($message, 'duplicate'));

                if (!$isCustomerCodeCollision || $attempt === 4) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('Unable to generate a unique customer code.');
    }
}
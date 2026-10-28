<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Department;
use App\Models\LeaveType;
use App\Models\LeaveBalance;
use App\Models\AuditLog;
use App\Services\EmployeeIdService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('department');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('department')) {
            $query->where('department_id', $request->department);
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $users = $query->paginate(15);
        $departments = Department::where('status', 'ACTIVE')->get();

        // Get all unique roles from database
        $roles = User::select('role')->distinct()->pluck('role')->toArray();

        return view('admin.users.index', compact('users', 'departments', 'roles'));
    }

    public function create()
    {
        $departments = Department::where('status', 'ACTIVE')->get();
        $nextEmployeeId = EmployeeIdService::generateUnique();
        return view('admin.users.create', compact('departments', 'nextEmployeeId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'department_id' => 'required|exists:departments,id',
            'role' => ['required', 'string', 'max:100', Rule::in($this->rolesForDepartment((int) $request->input('department_id')))],
            'position' => 'nullable|string|max:255',
            'joining_date' => 'required|date',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);

        $validated['employee_id'] = EmployeeIdService::generateUnique();
        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        $this->createLeaveBalances($user);

        AuditLog::log('CREATED', 'users', $user->id, "Created employee: {$user->name} ({$user->employee_id})", null, $user->only(['employee_id', 'name', 'email', 'phone', 'department_id', 'role', 'position', 'joining_date', 'status']));

        return redirect()->route('admin.users.index')
            ->with('success', "Employee created successfully. Employee ID: {$user->employee_id}");
    }

    public function show(User $user)
    {
        $user->load(['department', 'leaveBalances.leaveType', 'attendances' => function($query) {
            $query->latest()->limit(10);
        }]);

        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $departments = Department::where('status', 'ACTIVE')->get();
        return view('admin.users.edit', compact('user', 'departments'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'department_id' => 'required|exists:departments,id',
            'role' => ['required', 'string', 'max:100', Rule::in($this->rolesForDepartment((int) $request->input('department_id')))],
            'position' => 'nullable|string|max:255',
            'joining_date' => 'required|date',
            'status' => 'required|in:ACTIVE,INACTIVE',
            'change_password' => 'nullable|boolean',
        ]);

        unset($validated['change_password']);

        if ($request->boolean('change_password')) {
            $request->validate([
                'password' => 'required|string|min:8|confirmed',
            ]);
            $validated['password'] = Hash::make($request->password);
        }

        $oldValues = $user->only(['employee_id', 'name', 'email', 'phone', 'department_id', 'role', 'position', 'joining_date', 'status']);
        $user->update($validated);

        AuditLog::log('UPDATED', 'users', $user->id, "Updated employee: {$user->name}", $oldValues, $user->only(['employee_id', 'name', 'email', 'phone', 'department_id', 'role', 'position', 'joining_date', 'status']));

        return redirect()->route('admin.users.index')
            ->with('success', 'Employee updated successfully.');
    }

    public function deactivate(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        if ($user->status !== 'ACTIVE') {
            return back()->with('error', 'This employee account is already inactive.');
        }

        $oldValues = $user->only(['employee_id', 'name', 'email', 'status']);
        $user->update(['status' => 'INACTIVE']);

        AuditLog::log(
            'UPDATED',
            'users',
            $user->id,
            "Deactivated employee: {$user->name} ({$user->employee_id})",
            $oldValues,
            $user->only(['employee_id', 'name', 'email', 'status'])
        );

        return redirect()->route('admin.users.index')
            ->with('success', 'Employee deactivated. Their account and work history were retained.');
    }

    /**
     * API endpoint to get roles for selected department
     */
    public function getRolesByDepartment(Request $request)
    {
        $departmentId = $request->input('department_id');
        $roles = User::getRolesForDepartment($departmentId);
        
        return response()->json($roles);
    }

    private function createLeaveBalances(User $user)
    {
        $currentYear = now()->year;
        $leaveTypes = LeaveType::where('status', 'ACTIVE')->get();

        foreach ($leaveTypes as $leaveType) {
            LeaveBalance::create([
                'user_id' => $user->id,
                'leave_type_id' => $leaveType->id,
                'year' => $currentYear,
                'total_days' => $leaveType->days_allowed,
                'used_days' => 0,
                'pending_days' => 0,
                'carried_forward' => 0,
            ]);
        }
    }

    private function rolesForDepartment(int $departmentId): array
    {
        $department = Department::query()->find($departmentId);
        if (!$department) {
            return [];
        }

        $departmentCode = strtoupper($department->code);
        $roles = [
            "{$departmentCode}_MANAGER",
            "{$departmentCode}_EMPLOYEE",
            'EMPLOYEE',
        ];
        if ($departmentCode === 'ADMIN') {
            $roles[] = 'ADMIN';
        }

        return $roles;
    }
}
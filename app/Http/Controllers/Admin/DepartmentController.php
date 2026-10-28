<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::withCount('users')->paginate(10);
        return view('admin.departments.index', compact('departments'));
    }

    public function structure()
    {
        $departments = Department::withCount('users')
            ->with(['users' => function ($query) {
                $query->orderByRaw("CASE WHEN role = 'ADMIN' THEN 0 WHEN role LIKE '%_MANAGER' THEN 1 WHEN role = 'EMPLOYEE' OR role LIKE '%_EMPLOYEE' THEN 2 ELSE 3 END")
                    ->orderBy('name');
            }])
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();

        $departments->each(function (Department $department): void {
            $department->users->each(function (User $employee): void {
                $photoPath = $employee->profile_photo_path;
                $photoUrl = $photoPath && Storage::disk('public')->exists($photoPath)
                    ? Storage::disk('public')->url($photoPath)
                    : null;

                $employee->setAttribute('profile_photo_url', $photoUrl);
            });
        });

        return view('company-structure.index', compact('departments'));
    }

    public function create()
    {
        return view('admin.departments.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:departments',
            'code' => 'required|string|max:50|unique:departments',
            'description' => 'nullable|string',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);

        $department = Department::create($validated);

        AuditLog::log('CREATED', 'departments', $department->id, "Created department: {$department->name}", null, $department->only(['name', 'code', 'description', 'status']));

        return redirect()->route('admin.departments.index')
            ->with('success', 'Department created successfully.');
    }

    public function show(Department $department)
    {
        $department->load(['users' => function($query) {
            $query->orderBy('created_at', 'desc');
        }]);
        
        return view('admin.departments.show', compact('department'));
    }

    public function edit(Department $department)
    {
        return view('admin.departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments')->ignore($department->id)],
            'code' => ['required', 'string', 'max:50', Rule::unique('departments')->ignore($department->id)],
            'description' => 'nullable|string',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);

        $oldValues = $department->only(['name', 'code', 'description', 'status']);
        $department->update($validated);

        AuditLog::log('UPDATED', 'departments', $department->id, "Updated department: {$department->name}", $oldValues, $department->only(['name', 'code', 'description', 'status']));

        return redirect()->route('admin.departments.index')
            ->with('success', 'Department updated successfully.');
    }

    public function destroy(Department $department)
    {
        // Check if department has users
        if ($department->users()->count() > 0) {
            return back()->with('error', 'Cannot delete department with assigned employees. Reassign them first.');
        }

        $name = $department->name;
        $department->delete();

        AuditLog::log('DELETED', 'departments', $department->id, "Deleted department: {$name}", $department->getOriginal(), null);

        return redirect()->route('admin.departments.index')
            ->with('success', 'Department deleted successfully.');
    }
}
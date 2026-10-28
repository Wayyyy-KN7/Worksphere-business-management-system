<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isHR(), 403);

        $query = User::query()->with('department');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(fn ($employees) => $employees
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('employee_id', 'like', "%{$search}%"));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $employees = $query->orderBy('name')->paginate(20)->withQueryString();
        $departments = Department::query()->orderBy('name')->get();

        return view('hr.employees.index', compact('employees', 'departments'));
    }
}
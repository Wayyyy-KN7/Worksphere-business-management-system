<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\LeaveApproval;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\CrmNotificationService;
use App\Services\LeaveBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LeaveController extends Controller
{
    /**
     * Display a listing of the employee's leave requests.
     */
    public function index(Request $request)
    {
        $this->preventAdminLeaveAccess();
        $user = auth()->user();

        $query = LeaveRequest::where('user_id', $user->id)
            ->with(['leaveType', 'approvals.reviewer']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $leaveRequests = $query->orderBy('created_at', 'desc')->paginate(15);

        // Get leave balances for current year
        $leaveBalances = LeaveBalance::where('user_id', $user->id)
            ->where('year', now()->year)
            ->with('leaveType')
            ->get();

        return view('employee.leave.index', compact('leaveRequests', 'leaveBalances'));
    }

    /**
     * Show the form for creating a new leave request.
     */
    public function create()
    {
        $this->preventAdminLeaveAccess();
        $user = auth()->user();
        $currentYear = now()->year;

        // Get available active leave types
        $leaveTypes = LeaveType::where('status', 'ACTIVE')->get();

        // Automatically ensure every active leave type has a balance record for this user/year
        foreach ($leaveTypes as $type) {
            LeaveBalance::firstOrCreate(
                [
                    'user_id'       => $user->id,
                    'leave_type_id' => $type->id,
                    'year'          => $currentYear,
                ],
                [
                    'total_days'      => $type->days_allowed,
                    'used_days'       => 0,
                    'pending_days'    => 0,
                    'carried_forward' => 0,
                ]
            );
        }

        // Get current balances collection
        $leaveBalances = LeaveBalance::where('user_id', $user->id)
            ->where('year', $currentYear)
            ->with('leaveType')
            ->get();

        return view('employee.leave.create', compact('leaveTypes', 'leaveBalances'));
    }

    /**
     * Store a newly created leave request in storage.
     */
    public function store(Request $request)
    {
        $this->preventAdminLeaveAccess();
        $user = auth()->user();
        $currentYear = now()->year;

        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date'    => 'required|date|after_or_equal:today',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'reason'        => 'required|string|max:500',
        ]);

        $leaveType = LeaveType::findOrFail($validated['leave_type_id']);

        $leaveDaysByYear = app(LeaveBalanceService::class)->calendarDaysByYear($validated['start_date'], $validated['end_date']);
        $totalDays = array_sum($leaveDaysByYear);
        $notificationService = app(CrmNotificationService::class);
        $reviewerIds = $notificationService->departmentManagerIds($user->department_id, $user->id);
        if ($reviewerIds->isEmpty()) {
            $reviewerIds = User::query()
                ->where('status', 'ACTIVE')
                ->where('role', 'ADMIN')
                ->whereKeyNot($user->id)
                ->pluck('id');
        }

        if ($reviewerIds->isEmpty()) {
            return back()->withInput()->withErrors([
                'reason' => 'No eligible leave reviewer is available. Contact another administrator to review leave requests.',
            ]);
        }

        $result = DB::transaction(function () use ($user, $validated, $totalDays, $leaveDaysByYear, $leaveType, $reviewerIds) {
            $balancesByYear = [];

            foreach ($leaveDaysByYear as $year => $daysInYear) {
                $balance = LeaveBalance::query()
                    ->where('user_id', $user->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();

                if (!$balance) {
                    $balance = LeaveBalance::firstOrCreate(
                        ['user_id' => $user->id, 'leave_type_id' => $leaveType->id, 'year' => $year],
                        ['total_days' => $leaveType->days_allowed, 'used_days' => 0, 'pending_days' => 0, 'carried_forward' => 0],
                    );
                }

                if ($daysInYear > $balance->remaining_days) {
                    return ['error' => "The request needs {$daysInYear} leave day(s) in {$year}, but only {$balance->remaining_days} remain."];
                }

                $balancesByYear[$year] = $balance;
            }

            $leaveRequest = LeaveRequest::create([
                'user_id'        => $user->id,
                'leave_type_id'  => $validated['leave_type_id'],
                'start_date'     => $validated['start_date'],
                'end_date'       => $validated['end_date'],
                'total_days'     => $totalDays,
                'reason'         => $validated['reason'],
                'status'         => 'PENDING',
                'request_number' => 'LR-' . strtoupper(uniqid()),
            ]);

            foreach ($leaveDaysByYear as $year => $daysInYear) {
                $balancesByYear[$year]->increment('pending_days', $daysInYear);
            }

            foreach ($reviewerIds as $reviewerId) {
                LeaveApproval::firstOrCreate(
                    ['leave_request_id' => $leaveRequest->id, 'reviewer_id' => $reviewerId],
                    ['status' => 'PENDING'],
                );
            }

            return ['request' => $leaveRequest];
        });

        if (isset($result['error'])) {
            return back()->withInput()->withErrors(['end_date' => $result['error']]);
        }

        $leaveRequest = $result['request'];

        $notificationService->notifyUsers(
            $reviewerIds,
            'LEAVE_REQUEST',
            'Leave Request Submitted',
            "{$user->name} submitted leave request ({$leaveRequest->request_number}) for review.",
            $leaveRequest,
            'leave.submitted.' . $leaveRequest->id,
        );

        return redirect()
            ->route('employee.leave.index')
            ->with('success', 'Leave request submitted successfully.');
    }

    /**
     * Display the specified leave request.
     */
    public function show(LeaveRequest $leaveRequest)
    {
        $this->preventAdminLeaveAccess();
        $user = auth()->user();

        if ($leaveRequest->user_id !== $user->id) {
            abort(403, 'Unauthorized access to this leave request.');
        }

        $leaveRequest->load(['leaveType', 'approvals.reviewer']);

        return view('employee.leave.show', compact('leaveRequest'));
    }

    /**
     * Cancel a pending leave request (triggered via POST form route).
     */
    public function cancel(Request $request, LeaveRequest $leaveRequest)
    {
        $this->preventAdminLeaveAccess();
        $user = auth()->user();

        if ($leaveRequest->user_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }

        if ($leaveRequest->status !== 'PENDING') {
            return back()->with('error', 'Only pending leave requests can be cancelled.');
        }

        $cancelled = DB::transaction(function () use ($leaveRequest, $user) {
            $requestToCancel = LeaveRequest::query()->whereKey($leaveRequest->id)->lockForUpdate()->firstOrFail();
            if ($requestToCancel->status !== 'PENDING') {
                return false;
            }

            $oldStatus = $requestToCancel->status;
            $leaveDaysByYear = app(LeaveBalanceService::class)->calendarDaysByYear(
                $requestToCancel->start_date->toDateString(),
                $requestToCancel->end_date->toDateString(),
            );
            foreach ($leaveDaysByYear as $year => $daysInYear) {
                $leaveBalance = LeaveBalance::query()
                    ->where('user_id', $requestToCancel->user_id)
                    ->where('leave_type_id', $requestToCancel->leave_type_id)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();

                if ($leaveBalance) {
                    $leaveBalance->pending_days = max(0, $leaveBalance->pending_days - $daysInYear);
                    $leaveBalance->save();
                }
            }

            $requestToCancel->update(['status' => 'CANCELLED']);
            LeaveApproval::where('leave_request_id', $requestToCancel->id)
                ->where('status', 'PENDING')
                ->update(['status' => 'CANCELLED', 'comment' => 'Cancelled by employee.', 'reviewed_at' => now()]);

            AuditLog::log(
                'UPDATED',
                'leave_requests',
                $requestToCancel->id,
                "Leave request cancelled by {$user->name}: {$requestToCancel->request_number}",
                ['status' => $oldStatus],
                ['status' => 'CANCELLED'],
            );

            return true;
        });

        if (!$cancelled) {
            return back()->with('error', 'Only pending leave requests can be cancelled.');
        }

        return redirect()
            ->route('employee.leave.index')
            ->with('success', 'Leave request cancelled successfully.');
    }

    /**
     * Delete a leave request (REST destroy method).
     */
    public function destroy(LeaveRequest $leaveRequest)
    {
        return $this->cancel(request(), $leaveRequest);
    }

    private function preventAdminLeaveAccess(): void
    {
        abort_if(auth()->user()->isAdmin(), 404);
    }
}
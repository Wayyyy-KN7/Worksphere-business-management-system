<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveApproval;
use App\Models\LeaveBalance;
use App\Models\AuditLog;
use App\Services\CrmNotificationService;
use App\Services\LeaveBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LeaveController extends Controller
{
    /** Only Admin has company-wide leave visibility. HR and managers stay department-scoped. */
    private function canViewAll($user)
    {
        return $user->isAdmin();
    }

    /**
     * Check if the user is permitted to approve/reject this specific request.
     * - Admins can act on any request.
    * - Department managers can act only on assigned requests from their own department.
     */
    private function canTakeAction($user, LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->user_id === $user->id) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($leaveRequest->user->department_id !== $user->department_id) {
            return false;
        }

        if (!$user->isManager()) {
            return false;
        }

        return $leaveRequest->approvals()
            ->where('reviewer_id', $user->id)
            ->where('status', 'PENDING')
            ->exists();
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $query = LeaveRequest::with(['user.department', 'leaveType', 'approvals']);

        // If NOT Admin and NOT HR, restrict view strictly to their own department
        if (!$this->canViewAll($user)) {
            $query->whereHas('user', function($q) use ($user) {
                $q->where('department_id', $user->department_id);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $leaveRequests = $query->orderBy('created_at', 'desc')->paginate(15);

        $pendingCount = LeaveApproval::where('reviewer_id', $user->id)
            ->where('status', 'PENDING')
            ->count();

        return view('manager.leave.index', compact('leaveRequests', 'pendingCount'));
    }

    public function hrIndex(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isHR(), 403);

        $query = LeaveRequest::query()->with(['user.department', 'leaveType']);
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $leaveRequests = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('hr.leave_management.index', compact('leaveRequests'));
    }

    public function show(LeaveRequest $leaveRequest)
    {
        $user = auth()->user();

        // Non-HR/Non-Admin can only view requests in their own department. 
        // HR can view all, and Admin can view all.
        if (!$this->canViewAll($user) && $leaveRequest->user->department_id !== $user->department_id) {
            abort(403, 'Unauthorized access to this leave request.');
        }

        $leaveRequest->load(['user.department', 'leaveType', 'approvals.reviewer']);

        $requestYear = Carbon::parse($leaveRequest->start_date)->year;

        $leaveBalance = LeaveBalance::where('user_id', $leaveRequest->user_id)
            ->where('leave_type_id', $leaveRequest->leave_type_id)
            ->where('year', $requestYear)
            ->first();

        return view('manager.leave.show', compact('leaveRequest', 'leaveBalance'));
    }

    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        $user = auth()->user();

        // Enforce strict department control for approvals (HR can only approve HR dept, Managers their own dept)
        if (!$this->canTakeAction($user, $leaveRequest)) {
            abort(403, 'You can only approve leave requests from your own department.');
        }

        if ($leaveRequest->status !== 'PENDING') {
            return back()->with('error', 'This leave request has already been processed.');
        }

        $validated = $request->validate([
            'comment' => 'nullable|string|max:500',
        ]);

        $processed = DB::transaction(function () use ($leaveRequest, $user, $validated) {
            $requestToApprove = LeaveRequest::query()->whereKey($leaveRequest->id)->lockForUpdate()->firstOrFail();
            if ($requestToApprove->status !== 'PENDING') {
                return false;
            }

            $requestToApprove->update(['status' => 'APPROVED']);

            $attendanceForDate = \App\Models\Attendance::where('user_id', $requestToApprove->user_id)
                ->whereDate('attendance_date', $requestToApprove->start_date)
                ->first();

            if ($requestToApprove->start_date->equalTo($requestToApprove->end_date)) {
                if (!$attendanceForDate) {
                    $attendanceForDate = \App\Models\Attendance::firstOrCreate(
                        ['user_id' => $requestToApprove->user_id, 'attendance_date' => $requestToApprove->start_date],
                        [
                            'status' => 'LEAVE',
                            'check_in' => null,
                            'check_out' => null,
                            'working_hours' => 0,
                            'late_minutes' => 0,
                        ]
                    );
                    AuditLog::log(
                        'CREATED',
                        'attendances',
                        $attendanceForDate->id,
                        "Attendance marked On Leave after approval of {$requestToApprove->request_number}.",
                        null,
                        ['status' => 'LEAVE', 'leave_request_id' => $requestToApprove->id],
                    );
                } elseif (!$attendanceForDate->check_in && $attendanceForDate->status !== 'LEAVE') {
                    $oldStatus = $attendanceForDate->status;
                    $attendanceForDate->update(['status' => 'LEAVE']);
                    AuditLog::log(
                        'UPDATED',
                        'attendances',
                        $attendanceForDate->id,
                        "Attendance changed from {$oldStatus} to On Leave after approval of {$requestToApprove->request_number}.",
                        ['status' => $oldStatus],
                        ['status' => 'LEAVE', 'leave_request_id' => $requestToApprove->id],
                    );
                }
            }

            $leaveDaysByYear = app(LeaveBalanceService::class)->calendarDaysByYear(
                $requestToApprove->start_date->toDateString(),
                $requestToApprove->end_date->toDateString(),
            );

            foreach ($leaveDaysByYear as $year => $daysInYear) {
                $leaveBalance = LeaveBalance::query()
                    ->where('user_id', $requestToApprove->user_id)
                    ->where('leave_type_id', $requestToApprove->leave_type_id)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();

                if (!$leaveBalance) {
                    $leaveBalance = LeaveBalance::firstOrCreate(
                        ['user_id' => $requestToApprove->user_id, 'leave_type_id' => $requestToApprove->leave_type_id, 'year' => $year],
                        ['total_days' => $requestToApprove->leaveType->days_allowed ?? 12, 'used_days' => 0, 'pending_days' => 0, 'carried_forward' => 0],
                    );
                }

                $leaveBalance->pending_days = max(0, $leaveBalance->pending_days - $daysInYear);
                $leaveBalance->used_days += $daysInYear;
                $leaveBalance->save();
            }

            $approval = LeaveApproval::firstOrCreate(
                ['leave_request_id' => $requestToApprove->id, 'reviewer_id' => $user->id],
                ['status' => 'PENDING'],
            );
            $approval->update([
                'status' => 'APPROVED',
                'comment' => $validated['comment'] ?? null,
                'reviewed_at' => now(),
            ]);
            LeaveApproval::where('leave_request_id', $requestToApprove->id)
                ->where('status', 'PENDING')
                ->update(['status' => 'CANCELLED', 'comment' => 'Closed by another reviewer.', 'reviewed_at' => now()]);

            app(CrmNotificationService::class)->notifyUser(
                $requestToApprove->user_id,
                'LEAVE_APPROVED',
                'Leave Request Approved',
                "Your leave request ({$requestToApprove->request_number}) has been approved by {$user->name}.",
                $requestToApprove,
                'leave.approved.' . $requestToApprove->id,
            );

            AuditLog::log(
                'APPROVED',
                'leave_requests',
                $requestToApprove->id,
                "Leave request approved by {$user->name}: {$requestToApprove->request_number}",
                ['status' => 'PENDING'],
                ['status' => 'APPROVED', 'reviewed_by' => $user->id, 'comment' => $validated['comment'] ?? null],
            );

            return true;
        });

        if (!$processed) {
            return back()->with('error', 'This leave request has already been processed.');
        }

        return redirect()->route('manager.leave.index')
            ->with('success', 'Leave request approved successfully.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        $user = auth()->user();

        // Enforce strict department control for rejections
        if (!$this->canTakeAction($user, $leaveRequest)) {
            abort(403, 'You can only reject leave requests from your own department.');
        }

        if ($leaveRequest->status !== 'PENDING') {
            return back()->with('error', 'This leave request has already been processed.');
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
            'comment'          => 'nullable|string|max:500',
        ]);

        $processed = DB::transaction(function () use ($leaveRequest, $user, $validated) {
            $requestToReject = LeaveRequest::query()->whereKey($leaveRequest->id)->lockForUpdate()->firstOrFail();
            if ($requestToReject->status !== 'PENDING') {
                return false;
            }

            $requestToReject->update([
                'status'           => 'REJECTED',
                'rejection_reason' => $validated['rejection_reason'],
            ]);

            $leaveDaysByYear = app(LeaveBalanceService::class)->calendarDaysByYear(
                $requestToReject->start_date->toDateString(),
                $requestToReject->end_date->toDateString(),
            );
            foreach ($leaveDaysByYear as $year => $daysInYear) {
                $leaveBalance = LeaveBalance::query()
                    ->where('user_id', $requestToReject->user_id)
                    ->where('leave_type_id', $requestToReject->leave_type_id)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();

                if ($leaveBalance) {
                    $leaveBalance->pending_days = max(0, $leaveBalance->pending_days - $daysInYear);
                    $leaveBalance->save();
                }
            }

            $approval = LeaveApproval::firstOrCreate(
                ['leave_request_id' => $requestToReject->id, 'reviewer_id' => $user->id],
                ['status' => 'PENDING'],
            );
            $approval->update([
                'status' => 'REJECTED',
                'comment' => $validated['comment'] ?? null,
                'reviewed_at' => now(),
            ]);
            LeaveApproval::where('leave_request_id', $requestToReject->id)
                ->where('status', 'PENDING')
                ->update(['status' => 'CANCELLED', 'comment' => 'Closed by another reviewer.', 'reviewed_at' => now()]);

            app(CrmNotificationService::class)->notifyUser(
                $requestToReject->user_id,
                'LEAVE_REJECTED',
                'Leave Request Rejected',
                "Your leave request ({$requestToReject->request_number}) has been rejected. Reason: {$validated['rejection_reason']}",
                $requestToReject,
                'leave.rejected.' . $requestToReject->id,
            );

            AuditLog::log(
                'REJECTED',
                'leave_requests',
                $requestToReject->id,
                "Leave request rejected by {$user->name}: {$requestToReject->request_number}",
                ['status' => 'PENDING'],
                ['status' => 'REJECTED', 'reviewed_by' => $user->id, 'rejection_reason' => $validated['rejection_reason'], 'comment' => $validated['comment'] ?? null],
            );

            return true;
        });

        if (!$processed) {
            return back()->with('error', 'This leave request has already been processed.');
        }

        return redirect()->route('manager.leave.index')
            ->with('success', 'Leave request rejected.');
    }

    public function calendar(Request $request)
    {
        $user = auth()->user();

        $periodStart = now()->startOfMonth()->subMonth();
        $periodEnd = now()->endOfMonth()->addMonth();

        $query = LeaveRequest::whereIn('status', ['PENDING', 'APPROVED'])
            ->with(['user', 'leaveType'])
            ->whereDate('end_date', '>=', $periodStart)
            ->whereDate('start_date', '<=', $periodEnd)
            ->orderBy('start_date');

        // If NOT Admin and NOT HR, restrict view strictly to their own department
        if (!$this->canViewAll($user)) {
            $query->whereHas('user', function($q) use ($user) {
                $q->where('department_id', $user->department_id);
            });
        }

        $leaveRequests = $query->get();

        return view('manager.leave.calendar', compact('leaveRequests'));
    }
}
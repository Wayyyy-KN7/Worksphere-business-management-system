<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CompanySettings;
use App\Models\Holiday;
use App\Models\AuditLog;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Services\CrmNotificationService;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $today = now()->format('Y-m-d');

        $todayAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('attendance_date', $today)
            ->first();

        $settings = CompanySettings::current();
        $workingDays = $settings->working_days ?? ['MON', 'TUE', 'WED', 'THU', 'FRI'];
        $isWorkingDay = in_array(strtoupper(now()->format('D')), $workingDays, true);

        $isHoliday = Holiday::whereDate('holiday_date', $today)->exists();

        $monthlyAttendances = Attendance::where('user_id', $user->id)
            ->whereYear('attendance_date', now()->year)
            ->whereMonth('attendance_date', now()->month)
            ->orderBy('attendance_date', 'desc')
            ->get();

        $stats = [
            'present' => $monthlyAttendances->whereIn('status', ['PRESENT', 'LATE', 'EARLY_DEPARTURE', 'HALF_DAY'])->count(),
            'late' => $monthlyAttendances->where('status', 'LATE')->count(),
            'absent' => $monthlyAttendances->where('status', 'ABSENT')->count(),
            'total_hours' => $monthlyAttendances->sum('working_hours'),
        ];

        return view('employee.attendance.index', compact(
            'todayAttendance',
            'isHoliday',
            'isWorkingDay',
            'monthlyAttendances',
            'stats'
        ));
    }

    public function reportLate(Request $request)
    {
        $user = $request->user();
        $today = now()->format('Y-m-d');

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'expected_check_in' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string|max:500',
        ]);
        $notes = $validated['reason'] . (!empty($validated['notes']) ? ' | ' . $validated['notes'] : '');

        if (Attendance::where('user_id', $user->id)->whereDate('attendance_date', $today)->whereNotNull('check_in')->exists()) {
            return back()->with('error', 'You already checked in today.');
        }

        $attendance = Attendance::firstOrCreate(
            ['user_id' => $user->id, 'attendance_date' => $today],
            [
                'status' => 'LATE',
                'late_minutes' => 0,
                'manual_reason' => 'LATE_ARRIVAL',
                'notes' => $notes,
            ]
        );

        if ($attendance->status === 'LEAVE') {
            return back()->with('error', 'This day is already marked as leave.');
        }

        $attendance->update([
            'status' => 'LATE',
            'late_minutes' => 0,
            'manual_reason' => 'LATE_ARRIVAL',
            'notes' => $notes,
        ]);

        $managerIds = app(CrmNotificationService::class)->departmentManagerIds($user->department_id, $user->id);
        if ($managerIds->isEmpty()) {
            $managerIds = User::query()->where('status', 'ACTIVE')->where('role', 'ADMIN')->whereKeyNot($user->id)->pluck('id');
        }
        app(CrmNotificationService::class)->notifyUsers(
            $managerIds,
            'ATTENDANCE_LATE_REPORT',
            'Late Arrival Reported',
            "{$user->name} reported a late arrival for today.",
            $attendance,
            'attendance.late.' . $user->id . '.' . $today,
        );

        AuditLog::log('CREATED', 'attendances', $attendance->id, "Late arrival reported by {$user->name} for {$today}", ['status' => $attendance->status], ['status' => 'LATE', 'reason' => $validated['reason']]);

        return redirect()->route('employee.attendance.index')->with('success', 'Late arrival reported. Please check in normally when you arrive.');
    }

    public function checkIn(Request $request)
    {
        $user = auth()->user();
        $settings = CompanySettings::current();
        $timezone = $settings->timezone ?: config('app.timezone');
        $now = now($timezone);
        $today = $now->toDateString();
        $workingDays = $settings->working_days ?? ['MON', 'TUE', 'WED', 'THU', 'FRI'];

        if (!in_array(strtoupper(now()->format('D')), $workingDays, true)) {
            return back()->with('error', 'Today is not a configured working day.');
        }

        $holiday = Holiday::whereDate('holiday_date', $today)->first();
        if ($holiday) {
            return back()->with('error', "Today is a holiday: {$holiday->name}");
        }

        $existing = Attendance::where('user_id', $user->id)
            ->whereDate('attendance_date', $today)
            ->first();

        if ($existing && $existing->check_in) {
            return back()->with('error', 'You have already checked in today.');
        }

        if ($existing && $existing->status === 'LEAVE') {
            return back()->with('error', 'This day is already marked as leave.');
        }

        if (LeaveRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'APPROVED')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->exists()) {
            return back()->with('error', 'You have approved leave for today and cannot check in.');
        }

        $workStartTime = $now->copy()->startOfDay()->setTimeFromTimeString($settings->work_start ?: '09:00:00');
        $isLate = $now->greaterThan($workStartTime);
        $lateMinutes = $isLate ? (int) $workStartTime->diffInMinutes($now) : 0;

        $attendance = $existing ?? new Attendance([
            'user_id' => $user->id,
            'attendance_date' => $today,
            'status' => 'PRESENT',
        ]);

        $attendance->user_id = $user->id;
        $attendance->attendance_date = $today;
        $attendance->check_in = $now->format('H:i:s');
        $attendance->status = $isLate ? 'LATE' : 'PRESENT';
        $attendance->late_minutes = $lateMinutes;
        $attendance->is_manual = false;
        $attendance->save();

        if (class_exists(AuditLog::class)) {
            AuditLog::log('CREATED', 'attendances', $attendance->id,
                "Employee checked in at {$now->format('H:i')}");
        }

        $message = $isLate
            ? "Checked in successfully. You are {$lateMinutes} minutes late."
            : "Checked in successfully!";

        return back()->with('success', $message);
    }

    public function checkOut(Request $request)
    {
        $validated = $request->validate([
            'departure_type' => 'nullable|in:NORMAL,EARLY_DEPARTURE,HALF_DAY',
            'reason' => 'nullable|required_if:departure_type,EARLY_DEPARTURE,HALF_DAY|string|max:500',
        ]);
        $user = auth()->user();
        $settings = CompanySettings::current();
        $timezone = $settings->timezone ?: config('app.timezone');
        $checkOutTime = now($timezone);
        $today = $checkOutTime->toDateString();

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('attendance_date', $today)
            ->first();

        if (!$attendance || !$attendance->check_in) {
            return back()->with('error', 'You must check in first.');
        }

        if ($attendance->check_out) {
            return back()->with('error', 'You have already checked out today.');
        }

        if ($attendance->status === 'LEAVE') {
            return back()->with('error', 'This day is marked as leave.');
        }

        $departureType = $validated['departure_type'] ?? 'NORMAL';

        $datePart = Carbon::parse($attendance->attendance_date)->format('Y-m-d');
        $checkInTime = Carbon::parse($datePart . ' ' . $attendance->check_in, $timezone);
        if ($checkOutTime->lt($checkInTime)) {
            return back()->with('error', 'Check-out time cannot be earlier than check-in time.');
        }

        if ($departureType !== 'NORMAL') {
            $workStart = Carbon::parse($datePart . ' ' . ($settings->work_start ?: '09:00:00'), $timezone);
            $workEnd = Carbon::parse($datePart . ' ' . ($settings->work_end ?: '17:00:00'), $timezone);
            if ($checkOutTime->gte($workEnd)) {
                return back()->withErrors(['departure_type' => 'Early departure must be recorded before the configured work end.']);
            }
            if ($departureType === 'HALF_DAY' && $checkOutTime->gt($workStart->copy()->addMinutes((int) $workStart->diffInMinutes($workEnd) / 2))) {
                return back()->withErrors(['departure_type' => 'Half-day check-out must be by the midpoint of configured working hours.']);
            }
        }

        $diffInMinutes = $checkInTime->diffInMinutes($checkOutTime);
        $workingHours = round($diffInMinutes / 60, 2);
        $oldStatus = $attendance->status;
        $newStatus = in_array($departureType, ['EARLY_DEPARTURE', 'HALF_DAY'], true)
            ? $departureType
            : $oldStatus;
        $notes = $attendance->notes;

        if ($newStatus !== $oldStatus) {
            $departureNote = $departureType === 'HALF_DAY' ? 'Half-day' : 'Early departure';
            $notes = trim(($notes ? $notes . "\n" : '') . $departureNote . ': ' . $validated['reason']);
        }

        $attendance->update([
            'check_out' => $checkOutTime->format('H:i:s'),
            'working_hours' => $workingHours,
            'status' => $newStatus,
            'notes' => $notes,
        ]);

        $message = "Employee checked out at {$checkOutTime->format('H:i')}. Working hours: {$workingHours}h";
        if ($newStatus !== $oldStatus) {
            $managerIds = app(CrmNotificationService::class)->departmentManagerIds($user->department_id, $user->id);
            if ($managerIds->isEmpty()) {
                $managerIds = User::query()->where('status', 'ACTIVE')->where('role', 'ADMIN')->whereKeyNot($user->id)->pluck('id');
            }
            app(CrmNotificationService::class)->notifyUsers(
                $managerIds,
                'ATTENDANCE_EARLY_DEPARTURE',
                'Early Departure Reported',
                "{$user->name} reported a " . ($departureType === 'HALF_DAY' ? 'half-day' : 'early departure') . " today: {$validated['reason']}",
                $attendance,
                'attendance.departure.' . $user->id . '.' . $today,
            );
            $message .= ' Attendance recorded as ' . strtolower(str_replace('_', ' ', $newStatus)) . '.';
        }

        AuditLog::log('UPDATED', 'attendances', $attendance->id, $message,
            ['status' => $oldStatus, 'check_out' => null],
            ['status' => $newStatus, 'check_out' => $attendance->check_out, 'working_hours' => $workingHours, 'reason' => $validated['reason'] ?? null]);

        return back()->with('success', $message);
    }

    public function history(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->canViewCompanyAttendance(), 403);

        $validated = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'status' => 'nullable|in:PRESENT,LATE,ABSENT,LEAVE,EARLY_DEPARTURE,HALF_DAY,HOLIDAY',
            'search' => 'nullable|string|max:100',
        ]);

        $query = Attendance::query()->where('user_id', $user->id);
        $this->applyHistoryFilters($query, $validated);

        $attendances = (clone $query)->orderByDesc('attendance_date')->paginate(20)->withQueryString();
        $summary = $this->historySummary($query);

        return view('employee.attendance.history', compact('attendances', 'summary'));
    }

    private function applyHistoryFilters($query, array $filters): void
    {
        $query->when($filters['from_date'] ?? null, fn ($records, $date) => $records->whereDate('attendance_date', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($records, $date) => $records->whereDate('attendance_date', '<=', $date))
            ->when($filters['status'] ?? null, fn ($records, $status) => $records->where('status', $status))
            ->when($filters['search'] ?? null, fn ($records, $search) => $records->where(function ($matches) use ($search) {
                $matches->where('notes', 'like', "%{$search}%")
                    ->orWhere('manual_reason', 'like', "%{$search}%");
            }));
    }

    private function historySummary($query): array
    {
        return [
            'present' => (clone $query)->whereIn('status', ['PRESENT', 'LATE', 'EARLY_DEPARTURE', 'HALF_DAY'])->count(),
            'absent' => (clone $query)->where('status', 'ABSENT')->count(),
            'late' => (clone $query)->where('status', 'LATE')->count(),
            'leave' => (clone $query)->where('status', 'LEAVE')->count(),
        ];
    }
}
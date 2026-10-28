<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\CompanySettings;
use App\Models\AuditLog;
use App\Models\LeaveRequest;
use App\Services\AttendanceStatusService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->canViewCompanyAttendance(), 403);

        $filters = $request->validate([
            'date' => 'nullable|date',
            'department' => 'nullable|integer|exists:departments,id',
            'status' => 'nullable|in:PRESENT,LATE,ABSENT,LEAVE,EARLY_DEPARTURE,HALF_DAY,HOLIDAY,NOT_CHECKED_IN,NON_WORKING_DAY',
        ]);
        $settings = CompanySettings::current();
        $timezone = $settings->timezone ?: config('app.timezone');
        $date = $filters['date'] ?? now($timezone)->format('Y-m-d');
        $selectedDate = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $holiday = Holiday::whereDate('holiday_date', $date)->first();
        $approvedLeaveUserIds = LeaveRequest::query()
            ->where('status', 'APPROVED')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->pluck('user_id')
            ->flip();
        $statusResolver = app(AttendanceStatusService::class);

        $dailyAttendances = User::query()
            ->where('status', 'ACTIVE')
            ->with([
                'department',
                'attendances' => fn ($query) => $query
                    ->whereDate('attendance_date', $date)
                    ->with(['checkedInBy', 'checkedOutBy']),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($date, $selectedDate, $approvedLeaveUserIds, $holiday, $settings, $statusResolver): Attendance {
                $attendance = $user->attendances->first();
                if (!$attendance) {
                    $attendance = new Attendance([
                        'user_id' => $user->id,
                        'attendance_date' => $date,
                    ]);
                }
                $attendance->setRelation('user', $user);
                $attendance->status = $statusResolver->resolve(
                    $user,
                    $selectedDate,
                    $attendance->exists ? $attendance : null,
                    $approvedLeaveUserIds->has($user->id),
                    (bool) $holiday,
                    $settings,
                );

                return $attendance;
            });

        $filteredAttendances = $dailyAttendances
            ->when(isset($filters['department']), fn ($records) => $records->filter(
                fn (Attendance $attendance) => (int) $attendance->user->department_id === (int) $filters['department']
            ))
            ->when(isset($filters['status']), fn ($records) => $records->where('status', $filters['status']))
            ->values();
        $stats = [
            'total_employees' => $filteredAttendances->count(),
            'present' => $filteredAttendances->whereIn('status', ['PRESENT', 'EARLY_DEPARTURE', 'HALF_DAY'])->count(),
            'late' => $filteredAttendances->where('status', 'LATE')->count(),
            'absent' => $filteredAttendances->where('status', 'ABSENT')->count(),
        ];
        $page = LengthAwarePaginator::resolveCurrentPage();
        $attendances = new LengthAwarePaginator(
            $filteredAttendances->forPage($page, 20)->values(),
            $filteredAttendances->count(),
            20,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
        $departments = Department::where('status', 'ACTIVE')->get();

        $employees = User::where('status', 'ACTIVE')
            ->with('department')
            ->orderBy('name')
            ->get();

        return view('hr.attendance.index', compact(
            'attendances',
            'departments',
            'employees',
            'date',
            'holiday',
            'stats'
        ));
    }

    public function history(Request $request)
    {
        abort_unless($request->user()->canViewCompanyAttendance(), 403);

        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'department' => 'nullable|integer|exists:departments,id',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'status' => 'nullable|in:PRESENT,LATE,ABSENT,LEAVE,EARLY_DEPARTURE,HALF_DAY,HOLIDAY',
        ]);

        $query = Attendance::query()->with(['user.department', 'checkedInBy', 'checkedOutBy'])
            ->whereHas('user');
        $query->when($filters['search'] ?? null, fn ($records, $search) => $records->whereHas('user', fn ($employees) => $employees
            ->where('name', 'like', "%{$search}%")
            ->orWhere('employee_id', 'like', "%{$search}%")))
            ->when($filters['department'] ?? null, fn ($records, $departmentId) => $records->whereHas('user', fn ($employees) => $employees->where('department_id', $departmentId)))
            ->when($filters['from_date'] ?? null, fn ($records, $date) => $records->whereDate('attendance_date', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($records, $date) => $records->whereDate('attendance_date', '<=', $date))
            ->when($filters['status'] ?? null, fn ($records, $status) => $records->where('status', $status));

        $summary = [
            'present' => (clone $query)->whereIn('status', ['PRESENT', 'LATE', 'EARLY_DEPARTURE', 'HALF_DAY'])->count(),
            'absent' => (clone $query)->where('status', 'ABSENT')->count(),
            'late' => (clone $query)->where('status', 'LATE')->count(),
            'leave' => (clone $query)->where('status', 'LEAVE')->count(),
        ];
        $attendances = (clone $query)->orderByDesc('attendance_date')->orderByDesc('id')->paginate(20)->withQueryString();
        $departments = Department::query()->where('status', 'ACTIVE')->orderBy('name')->get();
        $showEmployee = true;

        return view('employee.attendance.history', compact('attendances', 'summary', 'departments', 'showEmployee'));
    }

  public function manualCheckIn(Request $request)
    {
        $this->authorizeAttendanceManagement();
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id,status,ACTIVE',
            'attendance_date' => 'required|date',
            'check_in_time' => 'required|date_format:H:i',
            'reason' => 'required|string|max:500',
        ]);

        $existing = Attendance::where('user_id', $validated['user_id'])
            ->whereDate('attendance_date', $validated['attendance_date'])
            ->first();

        if ($existing) {
            return back()->with('error', 'Attendance record already exists for this date.');
        }

        $approvedLeave = LeaveRequest::query()
            ->where('user_id', $validated['user_id'])
            ->where('status', 'APPROVED')
            ->whereDate('start_date', '<=', $validated['attendance_date'])
            ->whereDate('end_date', '>=', $validated['attendance_date'])
            ->exists();
        if ($approvedLeave) {
            return back()->with('error', 'This employee has approved leave for the selected date.');
        }

        $settings = CompanySettings::current();
        $timezone = $settings->timezone ?: config('app.timezone');
        if (CarbonImmutable::parse($validated['attendance_date'], $timezone)->isAfter(CarbonImmutable::now($timezone)->startOfDay())) {
            return back()->with('error', 'Manual check-in cannot be recorded for a future date.');
        }
        $checkInTime = Carbon::parse($validated['attendance_date'] . ' ' . $validated['check_in_time'], $timezone);
        $workStartTime = Carbon::parse($validated['attendance_date'] . ' ' . ($settings->work_start ?: '09:00'), $timezone);
        
        $isLate = $checkInTime->gt($workStartTime);
        $lateMinutes = $isLate ? (int) $workStartTime->diffInMinutes($checkInTime) : 0;

        $user = User::findOrFail($validated['user_id']);
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'attendance_date' => $validated['attendance_date'],
            'check_in' => $checkInTime->format('H:i:s'),
            'status' => $isLate ? 'LATE' : 'PRESENT',
            'late_minutes' => $lateMinutes,
            'is_manual' => true,
            'manual_reason' => $validated['reason'],
            'checked_in_by' => auth()->id(),
        ]);

        AuditLog::log('CREATED', 'attendances', $attendance->id,
            "Manual check-in for {$user->name} by HR at {$validated['check_in_time']}. Reason: {$validated['reason']}");

        return back()->with('success', "Manual check-in recorded successfully for {$user->name}.");
    }

    public function manualCheckOut(Request $request)
    {
        $this->authorizeAttendanceManagement();
        $validated = $request->validate([
            'attendance_id' => 'required|exists:attendances,id',
            'check_out_time' => 'required|date_format:H:i',
            'reason' => 'required|string|max:500',
        ]);

        $attendance = Attendance::findOrFail($validated['attendance_id']);
        if (!$attendance->check_in) {
            return back()->with('error', 'This attendance record has no check-in time.');
        }
        if ($attendance->check_out) {
            return back()->with('error', 'This attendance record already has a check-out. Use Edit Record for a correction.');
        }

        $dateOnly = Carbon::parse($attendance->attendance_date)->toDateString();
        $settings = CompanySettings::current();
        $timezone = $settings->timezone ?: config('app.timezone');
        $checkIn = Carbon::parse($dateOnly . ' ' . $attendance->check_in, $timezone);
        $checkOut = Carbon::parse($dateOnly . ' ' . $validated['check_out_time'], $timezone);
        $workEnd = Carbon::parse($dateOnly . ' ' . ($settings->work_end ?: '17:00'), $timezone);

        if ($checkOut->lt($checkIn)) {
            return back()->with('error', 'Check-out time cannot be earlier than check-in time.');
        }

        $workingHours = round($checkIn->diffInMinutes($checkOut, true) / 60, 2);
        $oldValues = [
            'check_out' => $attendance->check_out,
            'status' => $attendance->status,
            'working_hours' => $attendance->working_hours,
        ];
        $manualReason = trim(($attendance->manual_reason ? $attendance->manual_reason . "\n" : '') . 'Manual check-out: ' . $validated['reason']);
        $status = in_array($attendance->status, ['EARLY_DEPARTURE', 'HALF_DAY'], true)
            ? $attendance->status
            : ($checkOut->lt($workEnd) ? 'EARLY_DEPARTURE' : $attendance->status);

        $attendance->update([
            'check_out' => $checkOut->format('H:i:s'),
            'working_hours' => $workingHours,
            'status' => $status,
            'is_manual' => true,
            'checked_out_by' => auth()->id(),
            'manual_reason' => $manualReason,
        ]);

        $user = $attendance->user;

        AuditLog::log('UPDATED', 'attendances', $attendance->id,
            "Manual check-out for {$user->name} by HR at {$validated['check_out_time']}. Reason: {$validated['reason']}",
            $oldValues,
            ['check_out' => $attendance->check_out, 'status' => $attendance->status, 'working_hours' => $workingHours, 'reason' => $validated['reason']],
        );

        return back()->with('success', "Manual check-out recorded successfully for {$user->name}.");
    }

    public function report(Request $request)
    {
        abort_unless($request->user()->canViewCompanyAttendance(), 403);

        $filters = $request->validate([
            'period' => 'nullable|in:daily,monthly,yearly',
            'date' => 'required_if:period,daily|nullable|date_format:Y-m-d',
            'month' => 'required_if:period,monthly|nullable|date_format:Y-m',
            'year' => 'required_if:period,yearly|nullable|digits:4|integer|between:2000,2100',
            'department' => 'nullable|integer|exists:departments,id',
        ]);

        $period = $filters['period'] ?? 'monthly';
        $timezone = CompanySettings::current()->timezone ?: config('app.timezone');
        $periodStart = match ($period) {
            'daily' => CarbonImmutable::parse($filters['date'], $timezone)->startOfDay(),
            'yearly' => CarbonImmutable::create((int) ($filters['year'] ?? now($timezone)->year), 1, 1, 0, 0, 0, $timezone),
            default => CarbonImmutable::parse($filters['month'] ?? now($timezone)->format('Y-m'), $timezone)->startOfMonth(),
        };
        $periodEnd = match ($period) {
            'daily' => $periodStart->endOfDay(),
            'yearly' => $periodStart->endOfYear(),
            default => $periodStart->endOfMonth(),
        };
        $settings = CompanySettings::current();
        $dates = [];
        for ($date = $periodStart->startOfDay(); $date->lte($periodEnd->startOfDay()); $date = $date->addDay()) {
            $dates[] = $date;
        }

        $users = User::query()
            ->where('status', 'ACTIVE')
            ->when($filters['department'] ?? null, fn ($query, $departmentId) => $query->where('department_id', $departmentId))
            ->with([
                'department',
                'attendances' => fn ($query) => $query->whereBetween('attendance_date', [
                    $periodStart->toDateString(),
                    $periodEnd->toDateString(),
                ]),
            ])
            ->orderBy('name')
            ->get();
        $approvedLeaves = LeaveRequest::query()
            ->where('status', 'APPROVED')
            ->whereDate('start_date', '<=', $periodEnd->toDateString())
            ->whereDate('end_date', '>=', $periodStart->toDateString())
            ->get(['user_id', 'start_date', 'end_date'])
            ->groupBy('user_id');
        $holidays = Holiday::query()
            ->whereDate('holiday_date', '>=', $periodStart->toDateString())
            ->whereDate('holiday_date', '<=', $periodEnd->toDateString())
            ->get()
            ->keyBy(fn (Holiday $holiday) => $holiday->holiday_date->toDateString());
        $statusResolver = app(AttendanceStatusService::class);
        $reportRows = $users->map(function (User $user) use ($dates, $approvedLeaves, $holidays, $settings, $statusResolver, $timezone) {
            $attendances = $user->attendances->keyBy(fn (Attendance $attendance) => $attendance->attendance_date->toDateString());
            $leaveRequests = $approvedLeaves->get($user->id, collect());
            $counts = [
                'present' => 0,
                'late' => 0,
                'absent' => 0,
                'leave' => 0,
                'total_hours' => 0.0,
            ];

            foreach ($dates as $date) {
                $dateString = $date->toDateString();
                $attendance = $attendances->get($dateString);
                $onApprovedLeave = $leaveRequests->contains(
                    fn (LeaveRequest $leave) => $date->betweenIncluded(
                        CarbonImmutable::parse($leave->start_date->toDateString(), $timezone),
                        CarbonImmutable::parse($leave->end_date->toDateString(), $timezone),
                    )
                );
                $status = $statusResolver->resolve(
                    $user,
                    $date,
                    $attendance,
                    $onApprovedLeave,
                    $holidays->has($dateString),
                    $settings,
                );

                if (in_array($status, ['PRESENT', 'EARLY_DEPARTURE', 'HALF_DAY'], true)) {
                    $counts['present']++;
                } elseif ($status === 'LATE') {
                    $counts['late']++;
                } elseif ($status === 'ABSENT') {
                    $counts['absent']++;
                } elseif ($status === 'LEAVE') {
                    $counts['leave']++;
                }

                if ($attendance) {
                    $counts['total_hours'] += (float) $attendance->working_hours;
                }
            }

            $counts['total_hours'] = round($counts['total_hours'], 2);
            $attendedDays = $counts['present'] + $counts['late'];

            return [
                'user' => $user,
                'present' => $counts['present'],
                'late' => $counts['late'],
                'absent' => $counts['absent'],
                'leave' => $counts['leave'],
                'total_hours' => $counts['total_hours'],
                'average_hours' => $attendedDays > 0 ? $counts['total_hours'] / $attendedDays : 0,
            ];
        });

        $departments = Department::query()->where('status', 'ACTIVE')->orderBy('name')->get();
        $departmentId = $filters['department'] ?? null;
        $periodLabel = match ($period) {
            'daily' => $periodStart->format('F j, Y'),
            'yearly' => $periodStart->format('Y'),
            default => $periodStart->format('F Y'),
        };
        $date = $periodStart->toDateString();
        $month = $periodStart->format('Y-m');
        $year = $periodStart->format('Y');

        return view('hr.attendance.report', compact(
            'reportRows',
            'period',
            'periodLabel',
            'departments',
            'departmentId',
            'date',
            'month',
            'year',
        ));
    }
    public function update(Request $request, $id)
    {
        $this->authorizeAttendanceManagement();
        $validated = $request->validate([
            'check_in' => 'present|nullable|date_format:H:i',
            'check_out' => 'present|nullable|date_format:H:i',
            'status' => 'required|in:PRESENT,LATE,ABSENT,LEAVE,EARLY_DEPARTURE,HALF_DAY',
            'reason' => 'required|string|max:500',
        ]);

        $attendance = Attendance::findOrFail($id);
        $settings = CompanySettings::current();
        $timezone = $settings->timezone ?: config('app.timezone');
        $date = $attendance->attendance_date->toDateString();
        $workStart = Carbon::parse($date . ' ' . ($settings->work_start ?: '09:00'), $timezone);
        $workEnd = Carbon::parse($date . ' ' . ($settings->work_end ?: '17:00'), $timezone);
        $checkIn = filled($validated['check_in'])
            ? Carbon::parse($date . ' ' . $validated['check_in'], $timezone)
            : null;
        $checkOut = filled($validated['check_out'])
            ? Carbon::parse($date . ' ' . $validated['check_out'], $timezone)
            : null;
        $status = $validated['status'];

        if (in_array($status, ['PRESENT', 'LATE', 'EARLY_DEPARTURE', 'HALF_DAY'], true) && !$checkIn) {
            throw \Illuminate\Validation\ValidationException::withMessages(['check_in' => 'This attendance status requires a check-in time.']);
        }
        if (in_array($status, ['ABSENT', 'LEAVE'], true) && ($checkIn || $checkOut)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Absent and leave records cannot have check-in or check-out times.']);
        }
        if ($checkOut && !$checkIn) {
            throw \Illuminate\Validation\ValidationException::withMessages(['check_out' => 'A check-in time is required when a check-out time is provided.']);
        }
        if ($checkIn && $checkOut && $checkOut->lt($checkIn)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['check_out' => 'Check-out time cannot be earlier than check-in time.']);
        }

        $approvedLeave = LeaveRequest::query()
            ->where('user_id', $attendance->user_id)
            ->where('status', 'APPROVED')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
        $holiday = Holiday::whereDate('holiday_date', $date)->exists();
        $dateStatus = app(AttendanceStatusService::class)->resolve(
            $attendance->user,
            CarbonImmutable::parse($date, $timezone),
            null,
            $approvedLeave,
            $holiday,
            $settings,
        );
        if ($status === 'ABSENT' && $dateStatus !== 'ABSENT') {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Absent status is only valid after the absence cutoff on a configured workday without approved leave.']);
        }
        if ($status === 'LEAVE' && !$approvedLeave) {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Leave status requires an approved leave request for this date.']);
        }
        if ($status !== 'LEAVE' && $approvedLeave && $checkIn) {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'This employee has approved leave for the selected date.']);
        }
        if ($status === 'LATE' && $checkIn && !$checkIn->gt($workStart)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Late status requires a check-in after the configured work start time.']);
        }
        if ($status === 'PRESENT' && $checkIn && $checkIn->gt($workStart)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'A late check-in cannot be saved with Present status.']);
        }
        if (in_array($status, ['EARLY_DEPARTURE', 'HALF_DAY'], true) && (!$checkOut || !$checkOut->lt($workEnd))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Early departure and half-day statuses require a check-out before work end.']);
        }
        if ($status === 'HALF_DAY' && $checkOut && $checkOut->gt($workStart->copy()->addMinutes((int) ($workStart->diffInMinutes($workEnd) / 2)))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Half-day status requires check-out by the midpoint of configured working hours.']);
        }
        if (in_array($status, ['PRESENT', 'LATE'], true) && $checkOut && $checkOut->lt($workEnd)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'An early check-out must use Early Departure or Half Day status.']);
        }

        $lateMinutes = $checkIn && $checkIn->gt($workStart)
            ? (int) $workStart->diffInMinutes($checkIn)
            : 0;
        $workingHours = $checkIn && $checkOut
            ? round($checkIn->diffInMinutes($checkOut, true) / 60, 2)
            : 0.0;
        if ($workingHours !== null && $workingHours > 24) {
            throw \Illuminate\Validation\ValidationException::withMessages(['check_out' => 'Working hours cannot exceed 24 hours.']);
        }

        $oldValues = $attendance->only(['status', 'check_in', 'check_out', 'late_minutes', 'working_hours', 'is_manual', 'manual_reason']);
        $newCheckIn = $checkIn?->format('H:i:s');
        $newCheckOut = $checkOut?->format('H:i:s');
        $attendance->update([
            'check_in' => $newCheckIn,
            'check_out' => $newCheckOut,
            'status' => $status,
            'late_minutes' => $lateMinutes,
            'working_hours' => $workingHours,
            'is_manual' => true,
            'manual_reason' => trim(($attendance->manual_reason ? $attendance->manual_reason . "\n" : '') . $validated['reason']),
            'checked_in_by' => $attendance->check_in !== $newCheckIn ? auth()->id() : $attendance->checked_in_by,
            'checked_out_by' => $attendance->check_out !== $newCheckOut ? auth()->id() : $attendance->checked_out_by,
        ]);

        $user = $attendance->user;
        AuditLog::log(
            'UPDATED',
            'attendances',
            $attendance->id,
            "Attendance record updated for {$user->name}. Reason: {$validated['reason']}",
            $oldValues,
            array_merge($attendance->only(['status', 'check_in', 'check_out', 'late_minutes', 'working_hours', 'is_manual', 'manual_reason']), ['reason' => $validated['reason']]),
        );

        return back()->with('success', "Attendance record updated successfully for {$user->name}.");
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeAttendanceManagement();
        $validated = $request->validate(['reason' => 'required|string|max:500']);
        $attendance = Attendance::findOrFail($id);
        $user = $attendance->user;

        AuditLog::log('DELETED', 'attendances', $attendance->id,
            "Attendance record deleted for {$user->name}. Reason: {$validated['reason']}",
            $attendance->only(['status', 'check_in', 'check_out', 'late_minutes', 'working_hours', 'manual_reason']),
            ['reason' => $validated['reason']],
        );

        $attendance->delete();

        return back()->with('success', "Attendance record deleted successfully.");
    }

    private function authorizeAttendanceManagement(): void
    {
        abort_unless(auth()->user()?->canViewCompanyAttendance(), 403);
    }
}
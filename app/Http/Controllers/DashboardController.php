<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\LeaveApproval;
use App\Models\LeaveBalance;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\Sale;
use App\Models\Document;
use App\Models\FollowUp;
use App\Models\Reminder;
use App\Models\AuditLog;
use Carbon\CarbonImmutable;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $data = [
            'unreadNotifications' => $user->unreadNotificationsCount(),
            'notificationPanel' => $this->notificationDashboardData($user),
        ];

        if ($user->isAdmin()) {
            $data = array_merge($data, $this->adminDashboard());
        } elseif ($user->isManager()) {
            $data = array_merge($data, $this->departmentManagerDashboard($user));
        } elseif ($user->isHR()) {
            $data = array_merge($data, $this->hrDashboard($user));
        } elseif ($user->isSales()) {
            $data = array_merge($data, $this->salesDashboard($user));
        } elseif ($user->isMarketing()) {
            $data = array_merge($data, $this->marketingDashboard($user));
        } else {
            $data = array_merge($data, $this->employeeDashboard($user));
        }

        $data = array_merge($data, $this->documentDashboardData($user));
        $data = array_merge($data, $this->crmActivityDashboardData($user));
        if ($user->isAdmin() || $user->isSales()) {
            $data['salesCharts'] = $this->salesChartData($user);
        }

        return view('dashboard', compact('data'));
    }

    private function departmentManagerDashboard(User $user): array
    {
        $departmentId = $user->department_id;
        $departmentName = $user->department?->name ?? 'Department';
        $departmentCode = strtoupper($user->department?->code ?? '');
        $teamUsers = fn () => User::query()->where('department_id', $departmentId)
            ->where('status', 'ACTIVE')->whereKeyNot($user->id);
        $teamFollowUps = fn () => FollowUp::query()->whereHas('assignedUser', fn ($assigned) => $assigned->where('department_id', $departmentId));
        $teamReminders = fn () => Reminder::query()->whereHas('user', fn ($assigned) => $assigned->where('department_id', $departmentId));
        $activeFollowUps = fn () => ($teamFollowUps())->whereIn('status', ['PLANNED', 'IN_PROGRESS', 'OVERDUE']);
        $upcomingFollowUps = fn () => ($teamFollowUps())->whereIn('status', ['PLANNED', 'IN_PROGRESS'])->whereDate('follow_up_date', '>', today());
        $overdueFollowUps = fn () => ($teamFollowUps())->where(function ($query) {
            $query->where('status', 'OVERDUE')->orWhere(fn ($due) => $due->overdue());
        });
        $upcomingReminders = fn () => ($teamReminders())->whereIn('status', ['PENDING', 'SNOOZED'])->whereDate('reminder_date', '>', today());
        $overdueReminders = fn () => ($teamReminders())->where(function ($query) {
            $query->where('status', 'OVERDUE')->orWhere(function ($due) {
                $due->whereIn('status', ['PENDING', 'SNOOZED'])
                    ->where(function ($date) {
                        $date->whereDate('reminder_date', '<', today())
                            ->orWhere(fn ($today) => $today->whereDate('reminder_date', today())
                                ->whereNotNull('reminder_time')
                                ->where('reminder_time', '<', now()->format('H:i:s')));
                    });
            });
        });

        $cards = match ($departmentCode) {
            'HR' => $this->hrManagerCards($user, $departmentId, $teamUsers),
            'SALES' => $this->salesManagerCards($departmentId, $teamFollowUps, $upcomingFollowUps, $overdueFollowUps, $teamReminders),
            'IT' => $this->itManagerCards($user, $departmentId, $teamUsers, $teamFollowUps, $activeFollowUps, $upcomingFollowUps, $overdueFollowUps, $teamReminders, $upcomingReminders, $overdueReminders),
            default => $this->genericManagerCards($user, $departmentId, $teamUsers, $activeFollowUps, $upcomingFollowUps, $overdueFollowUps, $teamReminders, $upcomingReminders, $overdueReminders),
        };
        $dashboard = [
            'managerDashboard' => [
                'departmentName' => $departmentName,
                'cards' => $cards,
            ],
            'dashboardMetrics' => $this->buildDepartmentMetrics($departmentId, $departmentName),
            'departmentSummaries' => collect([$this->buildDepartmentSummary($departmentName, $departmentId)]),
        ];
        if ($user->isHR()) {
            $dashboard['attendanceChart'] = $this->attendanceChartData($departmentId);
        }

        return $dashboard;
    }

    private function hrManagerCards(User $user, ?int $departmentId, callable $teamUsers): array
    {
        $attendance = Attendance::query()->whereDate('attendance_date', today())
            ->whereHas('user', fn ($employee) => $employee->where('department_id', $departmentId)->whereKeyNot($user->id));
        $leaveBalances = LeaveBalance::query()->where('year', now()->year)
            ->whereHas('user', fn ($employee) => $employee->where('department_id', $departmentId)->whereKeyNot($user->id))
            ->with('leaveType')->get();

        return [
            ['label' => 'Employee Count', 'value' => $teamUsers()->count(), 'icon' => 'bi-people', 'tone' => 'primary'],
            ['label' => 'Attendance Today', 'value' => (clone $attendance)->whereIn('status', ['PRESENT', 'LATE', 'EARLY_DEPARTURE', 'HALF_DAY'])->count(), 'icon' => 'bi-calendar-check', 'tone' => 'success'],
            ['label' => 'Late Employees', 'value' => (clone $attendance)->where('status', 'LATE')->count(), 'icon' => 'bi-clock', 'tone' => 'warning'],
            ['label' => 'Absent Employees', 'value' => (clone $attendance)->where('status', 'ABSENT')->count(), 'icon' => 'bi-person-x', 'tone' => 'danger'],
            ['label' => 'Pending Leave Approvals', 'value' => LeaveApproval::query()->where('reviewer_id', $user->id)->where('status', 'PENDING')->count(), 'icon' => 'bi-hourglass-split', 'tone' => 'warning'],
            ['label' => 'Employee Leave Balance', 'value' => number_format($leaveBalances->sum(fn ($balance) => $balance->remaining_days), 1) . ' days', 'icon' => 'bi-calendar2-range', 'tone' => 'info'],
            ['label' => 'Employee Documents', 'value' => Document::query()->visibleTo($user)->where('department_id', $departmentId)->count(), 'icon' => 'bi-folder2-open', 'tone' => 'primary'],
        ];
    }

    private function salesManagerCards(?int $departmentId, callable $teamFollowUps, callable $upcomingFollowUps, callable $overdueFollowUps, callable $teamReminders): array
    {
        $customers = Customer::query()->where(function ($query) use ($departmentId) {
            $query->where('assigned_department_id', $departmentId)
                ->orWhereHas('assignedUser', fn ($assigned) => $assigned->where('department_id', $departmentId));
        });
        $opportunities = Opportunity::query()->where('status', 'OPEN')->whereHas('assignedUser', fn ($assigned) => $assigned->where('department_id', $departmentId));
        $pipeline = (clone $opportunities)->get(['estimated_value', 'probability'])
            ->sum(fn ($opportunity) => (float) $opportunity->estimated_value * (int) $opportunity->probability / 100);

        return [
            ['label' => 'Customer Count', 'value' => $customers->count(), 'icon' => 'bi-person-rolodex', 'tone' => 'primary'],
            ['label' => 'Sales Opportunities', 'value' => (clone $opportunities)->count(), 'icon' => 'bi-funnel', 'tone' => 'success'],
            ['label' => 'Follow-ups', 'value' => ($teamFollowUps())->whereIn('status', ['PLANNED', 'IN_PROGRESS'])->count(), 'icon' => 'bi-telephone-forward', 'tone' => 'info'],
            ['label' => 'Upcoming Follow-ups', 'value' => ($upcomingFollowUps())->count(), 'icon' => 'bi-calendar-event', 'tone' => 'primary'],
            ['label' => 'Overdue Follow-ups', 'value' => ($overdueFollowUps())->count(), 'icon' => 'bi-exclamation-circle', 'tone' => 'danger'],
            ['label' => 'Sales Pipeline', 'value' => config('company.currency', 'USD') . ' ' . number_format($pipeline, 0), 'icon' => 'bi-currency-dollar', 'tone' => 'success'],
            ['label' => 'Sales Reminders', 'value' => ($teamReminders())->whereIn('status', ['PENDING', 'SNOOZED', 'OVERDUE'])->count(), 'icon' => 'bi-bell', 'tone' => 'warning'],
            ['label' => 'Sales Activities', 'value' => ($teamFollowUps())->count() + ($teamReminders())->count(), 'icon' => 'bi-activity', 'tone' => 'secondary'],
        ];
    }

    private function itManagerCards(User $user, ?int $departmentId, callable $teamUsers, callable $teamFollowUps, callable $activeFollowUps, callable $upcomingFollowUps, callable $overdueFollowUps, callable $teamReminders, callable $upcomingReminders, callable $overdueReminders): array
    {
        $reminders = $teamReminders();

        return [
            ['label' => 'IT Employee Count', 'value' => $teamUsers()->count(), 'icon' => 'bi-people', 'tone' => 'primary'],
            ['label' => 'Assigned Tasks', 'value' => ($activeFollowUps())->count() + (clone $reminders)->whereIn('status', ['PENDING', 'SNOOZED', 'OVERDUE'])->count(), 'icon' => 'bi-list-task', 'tone' => 'success'],
            ['label' => 'Upcoming Tasks', 'value' => ($upcomingFollowUps())->count() + ($upcomingReminders())->count(), 'icon' => 'bi-calendar-event', 'tone' => 'info'],
            ['label' => 'Overdue Tasks', 'value' => ($overdueFollowUps())->count() + ($overdueReminders())->count(), 'icon' => 'bi-exclamation-circle', 'tone' => 'danger'],
            ['label' => 'Documents', 'value' => Document::query()->visibleTo($user)->where('department_id', $departmentId)->count(), 'icon' => 'bi-folder2-open', 'tone' => 'primary'],
            ['label' => 'IT Reminders', 'value' => (clone $reminders)->whereIn('status', ['PENDING', 'SNOOZED', 'OVERDUE'])->count(), 'icon' => 'bi-bell', 'tone' => 'warning'],
            ['label' => 'IT Activities', 'value' => ($teamFollowUps())->count() + $reminders->count(), 'icon' => 'bi-activity', 'tone' => 'secondary'],
        ];
    }

    private function genericManagerCards(User $user, ?int $departmentId, callable $teamUsers, callable $activeFollowUps, callable $upcomingFollowUps, callable $overdueFollowUps, callable $teamReminders, callable $upcomingReminders, callable $overdueReminders): array
    {
        return [
            ['label' => 'Employee Count', 'value' => $teamUsers()->count(), 'icon' => 'bi-people', 'tone' => 'primary'],
            ['label' => 'Assigned Tasks', 'value' => ($activeFollowUps())->count(), 'icon' => 'bi-list-task', 'tone' => 'success'],
            ['label' => 'Upcoming Tasks', 'value' => ($upcomingFollowUps())->count() + ($upcomingReminders())->count(), 'icon' => 'bi-calendar-event', 'tone' => 'info'],
            ['label' => 'Overdue Tasks', 'value' => ($overdueFollowUps())->count() + ($overdueReminders())->count(), 'icon' => 'bi-exclamation-circle', 'tone' => 'danger'],
            ['label' => 'Department Documents', 'value' => Document::query()->visibleTo($user)->where('department_id', $departmentId)->count(), 'icon' => 'bi-folder2-open', 'tone' => 'primary'],
            ['label' => 'Department Reminders', 'value' => ($teamReminders())->whereIn('status', ['PENDING', 'SNOOZED', 'OVERDUE'])->count(), 'icon' => 'bi-bell', 'tone' => 'warning'],
        ];
    }

    private function adminDashboard(): array
    {
        $departments = Department::where('status', 'ACTIVE')->get();
        $departmentSummaries = $departments->map(function ($department) {
            return $this->buildDepartmentSummary($department->name, $department->id);
        });

        return [
            'totalEmployees'    => User::where('status', 'ACTIVE')->count(),
            'totalDepartments'  => $departments->count(),
            'todayAttendance'   => Attendance::where('attendance_date', today())->count(),
            'pendingLeaves'     => LeaveRequest::where('status', 'PENDING')->count(),
            'totalCustomers'    => Customer::count(),
            'openOpportunities' => Opportunity::where('status', 'OPEN')->count(),
            'monthlySales'      => Sale::whereMonth('sale_date', now()->month)->sum('final_amount'),
            'totalDocuments'    => Document::count(),
            'dashboardMetrics'  => $this->buildCompanyMetrics(),
            'departmentSummaries' => $departmentSummaries,
        ];
    }

    private function buildCompanyMetrics(): array
    {
        $attendanceToday = Attendance::where('attendance_date', today());
        $pendingLeaves = LeaveRequest::where('status', 'PENDING');

        return [
            ['label' => 'Total Departments', 'value' => Department::where('status', 'ACTIVE')->count(), 'icon' => 'bi-building', 'tone' => 'primary'],
            ['label' => 'Total Employees', 'value' => User::where('status', 'ACTIVE')->count(), 'icon' => 'bi-people', 'tone' => 'info'],
            ['label' => 'Customers', 'value' => Customer::count(), 'icon' => 'bi-person-rolodex', 'tone' => 'success'],
            ['label' => 'Open Opportunities', 'value' => Opportunity::where('status', 'OPEN')->count(), 'icon' => 'bi-funnel', 'tone' => 'warning'],
            ['label' => 'Monthly Sales', 'value' => config('company.currency', 'USD') . ' ' . number_format(Sale::whereMonth('sale_date', now()->month)->sum('final_amount'), 0), 'icon' => 'bi-currency-dollar', 'tone' => 'success'],
            ['label' => 'Documents', 'value' => Document::count(), 'icon' => 'bi-file-earmark-text', 'tone' => 'secondary'],
            ['label' => 'Pending Leave', 'value' => $pendingLeaves->count(), 'icon' => 'bi-calendar2-x', 'tone' => 'warning'],
            ['label' => 'Attendance Today', 'value' => $attendanceToday->count(), 'icon' => 'bi-calendar-check', 'tone' => 'primary'],
        ];
    }

    private function buildDepartmentMetrics(?int $departmentId, string $departmentName): array
    {
        $employeeCount = User::where('status', 'ACTIVE')->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))->count();
        $attendanceCount = Attendance::where('attendance_date', today())->when($departmentId, fn ($query) => $query->whereHas('user', fn ($q) => $q->where('department_id', $departmentId)))->count();
        $pendingLeaves = LeaveRequest::where('status', 'PENDING')->when($departmentId, fn ($query) => $query->whereHas('user', fn ($q) => $q->where('department_id', $departmentId)))->count();
        $customers = Customer::query()->when($departmentId, fn ($query) => $query->where(function ($assigned) use ($departmentId) {
            $assigned->where('assigned_department_id', $departmentId)
                ->orWhereHas('assignedUser', fn ($user) => $user->where('department_id', $departmentId));
        }))->count();
        $opportunities = Opportunity::query()->where('status', 'OPEN')->when($departmentId, fn ($query) => $query->whereHas('assignedUser', fn ($q) => $q->where('department_id', $departmentId)))->count();
        $salesTotal = Sale::query()->when($departmentId, fn ($query) => $query->whereHas('assignedUser', fn ($q) => $q->where('department_id', $departmentId)))->whereMonth('sale_date', now()->month)->sum('final_amount');
        $documents = Document::query()->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))->count();
        $activities = FollowUp::query()->when($departmentId, fn ($query) => $query->whereHas('assignedUser', fn ($q) => $q->where('department_id', $departmentId)))->count() + Reminder::query()->when($departmentId, fn ($query) => $query->whereHas('user', fn ($q) => $q->where('department_id', $departmentId)))->count();

        return [
            ['label' => 'Employees', 'value' => $employeeCount, 'icon' => 'bi-people', 'tone' => 'primary'],
            ['label' => 'Attendance', 'value' => $attendanceCount, 'icon' => 'bi-calendar-check', 'tone' => 'success'],
            ['label' => 'Leave', 'value' => $pendingLeaves, 'icon' => 'bi-calendar2-x', 'tone' => 'warning'],
            ['label' => 'Customers', 'value' => $customers, 'icon' => 'bi-person-rolodex', 'tone' => 'info'],
            ['label' => 'Opportunities', 'value' => $opportunities, 'icon' => 'bi-funnel', 'tone' => 'secondary'],
            ['label' => 'Sales', 'value' => config('company.currency', 'USD') . ' ' . number_format($salesTotal, 0), 'icon' => 'bi-currency-dollar', 'tone' => 'success'],
            ['label' => 'Documents', 'value' => $documents, 'icon' => 'bi-file-earmark-text', 'tone' => 'primary'],
            ['label' => 'Activities', 'value' => $activities, 'icon' => 'bi-activity', 'tone' => 'warning'],
        ];
    }

    private function buildDepartmentSummary(string $departmentName, ?int $departmentId): array
    {
        return [
            'name' => $departmentName,
            'employees' => User::where('status', 'ACTIVE')->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))->count(),
            'customers' => Customer::query()->when($departmentId, fn ($query) => $query->where(function ($assigned) use ($departmentId) {
                $assigned->where('assigned_department_id', $departmentId)
                    ->orWhereHas('assignedUser', fn ($user) => $user->where('department_id', $departmentId));
            }))->count(),
            'opportunities' => Opportunity::query()->where('status', 'OPEN')->when($departmentId, fn ($query) => $query->whereHas('assignedUser', fn ($q) => $q->where('department_id', $departmentId)))->count(),
            'sales' => Sale::query()->when($departmentId, fn ($query) => $query->whereHas('assignedUser', fn ($q) => $q->where('department_id', $departmentId)))->sum('final_amount'),
            'documents' => Document::query()->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))->count(),
            'attendance' => Attendance::where('attendance_date', today())->when($departmentId, fn ($query) => $query->whereHas('user', fn ($q) => $q->where('department_id', $departmentId)))->count(),
            'leave' => LeaveRequest::where('status', 'PENDING')->when($departmentId, fn ($query) => $query->whereHas('user', fn ($q) => $q->where('department_id', $departmentId)))->count(),
        ];
    }

    private function hrDashboard(User $user): array
    {
        return [
            'totalEmployees'  => User::where('status', 'ACTIVE')->count(),
            'todayPresent'    => Attendance::where('attendance_date', today())->whereIn('status', ['PRESENT', 'EARLY_DEPARTURE', 'HALF_DAY'])->count(),
            'todayLate'       => Attendance::where('attendance_date', today())->where('status', 'LATE')->count(),
            'todayAbsent'     => Attendance::where('attendance_date', today())->where('status', 'ABSENT')->count(),
            'pendingLeaves'   => LeaveRequest::where('status', 'PENDING')->count(),
            'attendanceChart' => $this->attendanceChartData(),
        ];
    }

    private function attendanceChartData(?int $departmentId = null): array
    {
        $attendanceQuery = Attendance::query()
            ->when($departmentId, fn ($query) => $query->whereHas(
                'user',
                fn ($users) => $users->where('department_id', $departmentId),
            ));
        $latestAttendanceDate = (clone $attendanceQuery)->max('attendance_date');
        if (!$latestAttendanceDate) {
            return [
                'month' => null,
                'labels' => [],
                'present' => [],
                'late' => [],
                'absent' => [],
                'leave' => [],
                'holiday' => [],
            ];
        }

        $timezone = \App\Models\CompanySettings::current()->timezone;
        $monthStart = CarbonImmutable::parse($latestAttendanceDate, $timezone)->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();
        $daysInMonth = $monthEnd->day;
        $dailyCounts = (clone $attendanceQuery)
            ->whereDate('attendance_date', '>=', $monthStart->toDateString())
            ->whereDate('attendance_date', '<=', $monthEnd->toDateString())
            ->get(['attendance_date', 'status'])
            ->groupBy(fn (Attendance $attendance) => $attendance->attendance_date->day)
            ->map(fn ($records) => $records->countBy('status'));

        $chart = [
            'month' => $monthStart->translatedFormat('F Y'),
            'labels' => [],
            'present' => [],
            'late' => [],
            'absent' => [],
            'leave' => [],
            'holiday' => [],
        ];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $chart['labels'][] = (string) $day;
            $counts = $dailyCounts->get($day, collect());
            $chart['present'][] = (int) $counts->get('PRESENT', 0)
                + (int) $counts->get('EARLY_DEPARTURE', 0)
                + (int) $counts->get('HALF_DAY', 0);
            $chart['late'][] = (int) $counts->get('LATE', 0);
            $chart['absent'][] = (int) $counts->get('ABSENT', 0);
            $chart['leave'][] = (int) $counts->get('LEAVE', 0);
            $chart['holiday'][] = (int) $counts->get('HOLIDAY', 0);
        }

        return $chart;
    }

    private function salesDashboard($user): array
    {
        $query = $user->isManager()
            ? Customer::where(function ($customers) use ($user) {
                $customers->where('assigned_department_id', $user->department_id)
                    ->orWhereHas('assignedUser', fn ($assigned) => $assigned->where('department_id', $user->department_id));
            })
            : Customer::where('assigned_to', $user->id);

        return [
            'myCustomers'       => (clone $query)->count(),
            'openOpportunities' => Opportunity::where('assigned_to', $user->id)->where('status', 'OPEN')->count(),
            'pendingFollowUps'  => \App\Models\FollowUp::where('assigned_to', $user->id)->whereIn('status', ['PLANNED', 'IN_PROGRESS', 'OVERDUE'])->count(),
            'monthlySales'      => Sale::where('assigned_to', $user->id)->whereMonth('sale_date', now()->month)->sum('final_amount'),
        ];
    }

    private function marketingDashboard($user): array
    {
        return [
            'myCustomers'  => Customer::where(function ($customers) use ($user) {
                $customers->where('assigned_to', $user->id)->orWhere('created_by', $user->id);
            })->count(),
            'pendingTasks' => \App\Models\FollowUp::where('assigned_to', $user->id)->whereIn('status', ['PLANNED', 'IN_PROGRESS', 'OVERDUE'])->count(),
        ];
    }

    private function employeeDashboard($user): array
    {
        return [
            'todayAttendance' => Attendance::where('user_id', $user->id)->where('attendance_date', today())->first(),
            'pendingLeaves'   => LeaveRequest::where('user_id', $user->id)->where('status', 'PENDING')->count(),
            'myReminders'     => \App\Models\Reminder::where('user_id', $user->id)->where('status', 'PENDING')->count(),
        ];
    }

    private function documentDashboardData($user): array
    {
        $visibleDocumentsQuery = Document::query()->visibleTo($user);

        $userDocuments = Document::with(['department', 'uploader'])->where('uploaded_by', $user->id)->latest()->limit(5)->get();

        $departmentDocuments = Document::query()->visibleTo($user)
            ->with(['department', 'uploader'])
            ->where('status', 'PUBLISHED')
            ->when($user->department_id, function ($query) use ($user) {
                $query->where('department_id', $user->department_id)
                    ->orWhere('access_level', 'COMPANY_WIDE');
            }, function ($query) {
                $query->where('access_level', 'COMPANY_WIDE');
            })
            ->latest()
            ->limit(5)
            ->get();

        $companyDocuments = Document::query()->visibleTo($user)
            ->with(['department', 'uploader'])
            ->where('access_level', 'COMPANY_WIDE')
            ->where('status', 'PUBLISHED')
            ->latest()->limit(5)->get();

        $pendingApprovals = Document::with(['department', 'uploader'])
            ->where('status', 'PENDING_REVIEW')
            ->when(!$user->isAdmin(), function ($query) use ($user) {
                $query->where('department_id', $user->department_id);
            })
            ->latest()->limit(5)->get();

        return [
            'recentDocuments' => Document::query()->visibleTo($user)
                ->with(['department', 'uploader'])
                ->where('status', 'PUBLISHED')
                ->latest()->limit(5)->get(),
            'myDocumentsList' => $userDocuments,
            'departmentDocumentsList' => $departmentDocuments,
            'companyDocumentsList' => $companyDocuments,
            'pendingApprovalsList' => $pendingApprovals,
            'documentStats' => [
                'total' => $visibleDocumentsQuery->count(),
                'published' => (clone $visibleDocumentsQuery)->where('status', 'PUBLISHED')->count(),
                'pending_review' => (clone $visibleDocumentsQuery)->where('status', 'PENDING_REVIEW')->count(),
                'company_wide' => (clone $visibleDocumentsQuery)->where('access_level', 'COMPANY_WIDE')->count(),
            ],
        ];
    }

    private function crmActivityDashboardData(User $user): array
    {
        $followUps = FollowUp::query()->visibleTo($user)->whereNull('employee_id');
        $reminders = Reminder::query()->visibleTo($user)->whereNull('employee_id')->whereNull('reminder_type');
        $employeeActivity = collect();

        if ($user->isAdmin() || ($user->isManager() && $user->department_id)) {
            $activityRows = (clone $followUps)
                ->select('assigned_to')
                ->selectRaw('COUNT(*) as total_count')
                ->selectRaw("SUM(CASE WHEN status = 'OVERDUE' THEN 1 ELSE 0 END) as overdue_count")
                ->selectRaw("SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_count")
                ->selectRaw("SUM(CASE WHEN status IN ('PLANNED', 'IN_PROGRESS') AND follow_up_date = ? THEN 1 ELSE 0 END) as today_count", [today()->toDateString()])
                ->groupBy('assigned_to')
                ->get();
            $teamMembers = User::whereIn('id', $activityRows->pluck('assigned_to'))->get()->keyBy('id');
            $employeeActivity = $activityRows->map(fn ($row) => [
                'user' => $teamMembers->get($row->assigned_to),
                'total' => (int) $row->total_count,
                'today' => (int) $row->today_count,
                'overdue' => (int) $row->overdue_count,
                'completed' => (int) $row->completed_count,
            ])->filter(fn ($row) => $row['user'] !== null)->values();
        }

        $overdueFollowUps = (clone $followUps)->where(function ($query) {
            $query->where('status', 'OVERDUE')
                ->orWhere(function ($active) {
                    $active->whereIn('status', ['PLANNED', 'IN_PROGRESS'])->overdue();
                });
        });

        $overdueReminders = (clone $reminders)->where(function ($query) {
            $query->where('status', 'OVERDUE')
                ->orWhere(function ($active) {
                    $active->whereIn('status', ['PENDING', 'SNOOZED'])
                        ->where(function ($due) {
                            $due->whereDate('reminder_date', '<', today())
                                ->orWhere(function ($todayDue) {
                                    $todayDue->whereDate('reminder_date', today())
                                        ->whereNotNull('reminder_time')
                                        ->where('reminder_time', '<', now()->format('H:i:s'));
                                });
                        });
                });
        });

        return [
            'crmActivity' => [
                'todayFollowUps' => (clone $followUps)->whereIn('status', ['PLANNED', 'IN_PROGRESS'])->whereDate('follow_up_date', today())->count(),
                'upcomingFollowUps' => (clone $followUps)->whereIn('status', ['PLANNED', 'IN_PROGRESS'])->whereDate('follow_up_date', '>', today())->count(),
                'overdueFollowUps' => $overdueFollowUps->count(),
                'completedFollowUps' => (clone $followUps)->where('status', 'COMPLETED')->count(),
                'todayReminders' => (clone $reminders)->whereIn('status', ['PENDING', 'SNOOZED'])->whereDate('reminder_date', today())->count(),
                'upcomingReminders' => (clone $reminders)->whereIn('status', ['PENDING', 'SNOOZED'])->whereDate('reminder_date', '>', today())->count(),
                'overdueReminders' => $overdueReminders->count(),
                'overdueFollowUpsList' => (clone $overdueFollowUps)->with(['customer', 'assignedUser'])->orderBy('follow_up_date')->limit(5)->get(),
                'upcomingFollowUpsList' => (clone $followUps)->with(['customer', 'assignedUser'])->whereIn('status', ['PLANNED', 'IN_PROGRESS'])->whereDate('follow_up_date', '>=', today())->orderBy('follow_up_date')->orderBy('follow_up_time')->limit(5)->get(),
                'overdueRemindersList' => (clone $overdueReminders)->with(['customer', 'user', 'followUp'])->orderBy('reminder_date')->limit(5)->get(),
                'employeeActivity' => $employeeActivity,
            ],
        ];
    }

    private function notificationDashboardData(User $user): array
    {
        $pendingLeaves = \App\Models\LeaveRequest::query()->where('status', 'PENDING');
        $pendingDocuments = Document::query()->where('status', 'PENDING_REVIEW');

        if (!$user->isAdmin()) {
            if ($user->isManager() && $user->department_id) {
                $pendingLeaves->whereHas('user', fn ($employee) => $employee->where('department_id', $user->department_id));
                $pendingDocuments->where('department_id', $user->department_id);
            } else {
                $pendingLeaves->whereRaw('1 = 0');
                $pendingDocuments->whereRaw('1 = 0');
            }
        }

        $notifications = $user->isAdmin()
            ? \App\Models\Notification::query()
            : $user->notifications();

        return [
            'unreadCount' => $user->unreadNotificationsCount(),
            'recent' => $notifications->latest()->limit(5)->get(),
            'pendingLeaveApprovals' => $pendingLeaves->count(),
            'pendingDocumentApprovals' => $pendingDocuments->count(),
            'overdueFollowUps' => FollowUp::query()->visibleTo($user)->whereNull('employee_id')->where('status', 'OVERDUE')->count(),
        ];
    }

    private function salesChartData(User $user): array
    {
        $sales = Sale::query();
        $opportunities = Opportunity::query()->where('status', 'OPEN');

        if (!$user->isAdmin()) {
            if ($user->isManager() && $user->department_id) {
                $sales->whereHas('assignedUser', fn ($assigned) => $assigned->where('department_id', $user->department_id));
                $opportunities->whereHas('assignedUser', fn ($assigned) => $assigned->where('department_id', $user->department_id));
            } else {
                $sales->where('assigned_to', $user->id);
                $opportunities->where('assigned_to', $user->id);
            }
        }

        $months = collect(range(5, 0))->map(fn (int $offset) => now()->startOfMonth()->subMonths($offset));
        $labels = [];
        $bookedSales = [];
        $forecastByMonth = [];

        foreach ($months as $month) {
            $labels[] = $month->format('M y');
            $bookedSales[] = (float) (clone $sales)
                ->whereBetween('sale_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->sum('final_amount');
            $forecastByMonth[$month->format('Y-m')] = 0.0;
        }

        $stageValues = [
            'NEW' => 0.0,
            'QUALIFIED' => 0.0,
            'PROPOSAL' => 0.0,
            'NEGOTIATION' => 0.0,
        ];
        $forecastStart = $months->first()->copy()->startOfMonth();
        $forecastEnd = $months->last()->copy()->endOfMonth();

        $openOpportunities = (clone $opportunities)
            ->whereDate('expected_close_date', '>=', $forecastStart->toDateString())
            ->whereDate('expected_close_date', '<=', $forecastEnd->toDateString())
            ->get(['estimated_value', 'probability', 'expected_close_date', 'stage']);

        foreach ($openOpportunities as $opportunity) {
            $weightedValue = (float) $opportunity->estimated_value * (int) $opportunity->probability / 100;
            $monthKey = $opportunity->expected_close_date->format('Y-m');
            $forecastByMonth[$monthKey] = ($forecastByMonth[$monthKey] ?? 0) + $weightedValue;

            if (array_key_exists($opportunity->stage, $stageValues)) {
                $stageValues[$opportunity->stage] += $weightedValue;
            }
        }

        return [
            'labels' => $labels,
            'bookedSales' => $bookedSales,
            'weightedForecast' => array_values($forecastByMonth),
            'stageLabels' => array_keys($stageValues),
            'weightedByStage' => array_values($stageValues),
            'weightedPipelineTotal' => array_sum($stageValues),
            'currency' => config('company.currency', 'USD'),
        ];
    }
}
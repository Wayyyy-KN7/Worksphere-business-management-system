@extends('layouts.app')

@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@push('styles')
<style>
    .dashboard-shell {
        padding: 18px 0 26px;
    }

    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 18px;
        padding: 6px 0 18px;
    }

    .dashboard-eyebrow {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.12rem;
        color: var(--primary);
        margin-bottom: 8px;
    }

    .dashboard-header h2 {
        margin: 0;
        color: var(--navy);
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: 0;
    }

    .dashboard-header p {
        margin: 8px 0 0;
        color: var(--muted);
        font-size: 0.95rem;
    }

    .dashboard-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .dashboard-pill,
    .dashboard-btn {
        border: 1px solid var(--border);
        background: var(--panel-bg);
        color: var(--navy);
        border-radius: 6px;
        height: 36px;
        padding: 0 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 0.82rem;
        font-weight: 600;
    }

    .dashboard-btn {
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .dashboard-btn:hover {
        border-color: rgba(37, 99, 235, 0.4);
        background: #EFF6FF;
    }

    .dashboard-btn .badge {
        min-width: 18px;
        height: 18px;
        padding: 0 6px;
        font-size: 0.7rem;
    }

    .dashboard-calendar { position: relative; }

    .calendar-popover {
        width: 304px;
        padding: 14px;
        border: 1px solid var(--border);
        border-radius: 8px;
        box-shadow: 0 12px 30px rgba(27, 43, 52, 0.16);
    }

    .calendar-popover-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 12px;
    }

    .calendar-month-label {
        color: var(--navy);
        font-size: 0.9rem;
        font-weight: 650;
    }

    .calendar-month-nav {
        width: 30px;
        height: 30px;
        padding: 0;
        display: inline-grid;
        place-items: center;
        border: 1px solid var(--border);
        border-radius: 6px;
        color: var(--navy);
        background: var(--panel-bg);
    }

    .calendar-month-nav:hover { color: var(--primary-dark); background: #EFF6FF; }

    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 4px;
    }

    .calendar-weekday,
    .calendar-day {
        display: grid;
        aspect-ratio: 1;
        place-items: center;
        border-radius: 5px;
        font-size: 0.76rem;
    }

    .calendar-weekday {
        color: var(--muted);
        font-size: 0.66rem;
        font-weight: 700;
    }

    .calendar-day { position: relative; color: var(--navy); }
    .calendar-day.is-today { color: #FFFFFF; background: var(--primary); font-weight: 700; }
    .calendar-day.has-event:not(.is-today)::after {
        position: absolute;
        bottom: 3px;
        width: 4px;
        height: 4px;
        border-radius: 50%;
        background: var(--primary);
        content: "";
    }

    .calendar-popover-footer {
        margin-top: 12px;
        padding-top: 10px;
        border-top: 1px solid var(--border);
        text-align: center;
        font-size: 0.82rem;
        font-weight: 600;
    }

    @media (max-width: 575px) {
        .calendar-popover { width: min(304px, calc(100vw - 32px)); }
    }

    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(160px, 1fr));
        gap: 12px;
        margin-top: 8px;
    }

    .kpi-card {
        background: linear-gradient(125deg, var(--kpi-soft, #EFF6FF) 0%, #FFFFFF 78%);
        border: 1px solid var(--border);
        border-left: 4px solid var(--kpi-accent, var(--primary));
        border-radius: 14px;
        box-shadow: var(--shadow);
        padding: 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .kpi-card { transition: transform 180ms ease, box-shadow 180ms ease; }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 12px 24px rgba(15, 23, 42, 0.1); }
    .kpi-card.tone-primary { --kpi-accent: #2563EB; --kpi-soft: #EFF6FF; }
    .kpi-card.tone-success { --kpi-accent: #22C55E; --kpi-soft: #F0FDF4; }
    .kpi-card.tone-info { --kpi-accent: #0EA5E9; --kpi-soft: #F0F9FF; }
    .kpi-card.tone-warning { --kpi-accent: #F97316; --kpi-soft: #FFF7ED; }
    .kpi-card.tone-danger { --kpi-accent: #EF4444; --kpi-soft: #FEF2F2; }
    .kpi-card.tone-secondary,
    .kpi-card.tone-purple { --kpi-accent: #8B5CF6; --kpi-soft: #F5F3FF; }

    .kpi-card .meta {
        min-width: 0;
    }

    .kpi-label {
        font-size: 0.72rem;
        letter-spacing: 0.04rem;
        text-transform: uppercase;
        color: var(--muted);
        font-weight: 700;
    }

    .kpi-value {
        margin-top: 8px;
        font-size: 1.5rem;
        color: var(--navy);
        line-height: 1.1;
        font-weight: 700;
        letter-spacing: 0;
    }

    .kpi-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    .kpi-icon.bg-primary,
    .kpi-icon.bg-success,
    .kpi-icon.bg-info,
    .kpi-icon.bg-warning,
    .kpi-icon.bg-danger,
    .kpi-icon.bg-secondary {
        background: var(--kpi-soft, #EFF6FF) !important;
        color: var(--kpi-accent, var(--primary)) !important;
    }

    .analytics-grid,
    .content-grid,
    .doc-grid {
        display: grid;
        gap: 14px;
        margin-top: 20px;
    }

    .analytics-grid {
        grid-template-columns: 1.9fr 1fr;
    }

    .content-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .doc-grid {
        grid-template-columns: 1.3fr 1fr;
    }

    .panel {
        background: var(--panel-bg);
        border: 1px solid var(--border);
        border-radius: 14px;
        box-shadow: var(--shadow);
        padding: 16px;
    }

    .panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        margin-bottom: 12px;
    }

    .panel-caption {
        font-size: 0.69rem;
        text-transform: uppercase;
        letter-spacing: 0.12rem;
        color: var(--muted);
        font-weight: 700;
        display: block;
        margin-bottom: 4px;
    }

    .panel-header h3 {
        margin: 0;
        color: var(--navy);
        font-size: 1.15rem;
        font-weight: 700;
    }

    .panel-kicker {
        font-size: 0.76rem;
        font-weight: 700;
        padding: 6px 9px;
        border-radius: 8px;
        background: #EFF6FF;
        color: var(--primary-dark);
        white-space: nowrap;
    }

    .chart-shell {
        position: relative;
        height: 290px;
    }

    .chart-shell.small {
        height: 260px;
    }

    .mini-stat-list,
    .action-list,
    .list-panel {
        display: grid;
        gap: 8px;
    }

    .mini-stat-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 9px 10px;
        border: 1px solid rgba(20, 54, 74, 0.08);
        border-radius: 6px;
        background: var(--surface-subtle, #F7F9FA);
    }

    .mini-stat-item .label {
        font-size: 0.8rem;
        color: var(--muted);
    }

    .mini-stat-item .value {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--navy);
    }

    .mini-stat-item .value.danger { color: #dc3545; }
    .mini-stat-item .value.warning { color: #d97706; }
    .mini-stat-item .value.success { color: var(--primary); }
    .mini-stat-item .value.info { color: var(--navy); }

    .action-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 9px 10px;
        border: 1px solid rgba(20, 54, 74, 0.08);
        border-radius: 6px;
        background: var(--surface-subtle, #F7F9FA);
    }

    .action-item strong {
        font-size: 0.89rem;
        color: var(--navy);
        display: block;
    }

    .action-item .meta {
        font-size: 0.75rem;
        color: var(--muted);
    }

    .action-badge,
    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
        min-width: 24px;
        min-height: 24px;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 700;
        padding: 0 7px;
    }

    .status-badge.danger,
    .action-badge.danger { background: #FEE2E2; color: #B91C1C; }
    .status-badge.warning,
    .action-badge.warning { background: #FFEDD5; color: #C2410C; }
    .status-badge.success,
    .action-badge.success { background: #DCFCE7; color: #15803D; }
    .status-badge.primary,
    .action-badge.primary { background: #DBEAFE; color: #1D4ED8; }

    .doc-row,
    .notification-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 11px 0;
        border-bottom: 1px solid rgba(20, 54, 74, 0.08);
    }

    .doc-row:last-child,
    .notification-row:last-child {
        border-bottom: 0;
    }

    .doc-main,
    .notification-main {
        min-width: 0;
        flex: 1;
    }

    .pill-title {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--navy);
        line-height: 1.35;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pill-subtitle {
        margin-top: 4px;
        font-size: 0.73rem;
        color: rgba(20, 54, 74, 0.7);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .link-btn {
        height: 32px;
        border-radius: 6px;
        border: 1px solid #BFDBFE;
        color: var(--primary);
        background: #EFF6FF;
        padding: 0 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.76rem;
        font-weight: 700;
    }

    .table-panel {
        overflow: hidden;
    }

    .table-panel table {
        margin: 0;
    }

    .table-panel th,
    .table-panel td {
        padding: 12px 10px;
        vertical-align: middle;
        font-size: 0.8rem;
    }

    .table-panel thead th {
        background: rgba(245, 247, 248, 0.8);
        color: rgba(20, 54, 74, 0.7);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04rem;
    }

    @media (max-width: 1200px) {
        .kpi-grid {
            grid-template-columns: repeat(3, minmax(160px, 1fr));
        }
    }

    @media (max-width: 991px) {
        .analytics-grid,
        .content-grid,
        .doc-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767px) {
        .dashboard-header {
            display: block;
        }

        .dashboard-actions {
            margin-top: 14px;
        }

        .kpi-grid {
            grid-template-columns: repeat(2, minmax(140px, 1fr));
        }
    }

    @media (max-width: 575px) {
        .dashboard-shell {
            padding-top: 8px;
        }

        .kpi-grid {
            grid-template-columns: 1fr;
        }

        .chart-shell {
            height: 230px;
        }
    }

    .dashboard-eyebrow,
    .kpi-label,
    .panel-caption,
    .table-panel thead th { letter-spacing: 0; }

    .mini-stat-item,
    .action-item { border-color: var(--border); }

    .action-badge,
    .status-badge { border-radius: 999px; }

    .status-badge.success,
    .action-badge.success { background: #DCFCE7; color: #15803D; }

    .status-badge.primary,
    .action-badge.primary { background: #DBEAFE; color: #1D4ED8; }

    .doc-row,
    .notification-row { border-bottom-color: var(--border); }

    .pill-subtitle,
    .table-panel thead th { color: var(--muted); }

    .link-btn { border-color: #BFDBFE; color: var(--primary); background: #EFF6FF; }

    .table-panel th,
    .table-panel td { padding: 9px 10px; }

    .schedule-list { display: grid; gap: 4px; }

    .schedule-row {
        display: flex;
        align-items: center;
        gap: 9px;
        min-width: 0;
        padding: 7px 0;
        color: var(--navy);
        border-bottom: 1px solid var(--border);
    }

    .schedule-row:last-child { border-bottom: 0; }
    .schedule-row:hover { color: var(--primary-dark); }

    .schedule-icon {
        display: grid;
        place-items: center;
        width: 30px;
        height: 30px;
        flex: 0 0 30px;
        border-radius: 6px;
        background: #EFF6FF;
        color: var(--primary-dark);
    }

    .schedule-icon.reminder { background: #F6F2E9; color: #80652C; }
    .schedule-icon.leave { background: #F0F3F5; color: #52636D; }

    .schedule-copy { min-width: 0; flex: 1; }

    .schedule-title {
        display: block;
        overflow: hidden;
        color: var(--navy);
        font-size: 0.78rem;
        font-weight: 650;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .schedule-meta {
        display: block;
        overflow: hidden;
        color: var(--muted);
        font-size: 0.68rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .schedule-kind {
        flex: 0 0 auto;
        padding: 3px 5px;
        border-radius: 4px;
        background: #DBEAFE;
        color: #1D4ED8;
        font-size: 0.62rem;
        font-weight: 650;
        white-space: nowrap;
    }

    .schedule-kind.reminder { background: #F6F2E9; color: #80652C; }
    .schedule-kind.leave { background: #F0F3F5; color: #52636D; }

    .schedule-empty {
        padding: 18px 4px;
        color: var(--muted);
        font-size: 0.8rem;
        text-align: center;
    }

    @media (max-width: 991px) {
        .content-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 575px) {
        .schedule-kind { max-width: 76px; overflow: hidden; text-overflow: ellipsis; }
    }
</style>
@endpush

@section('content')
@php
    $user = auth()->user();
    $notificationPanel = $data['notificationPanel'] ?? [];
    $activity = $data['crmActivity'] ?? [];
    $salesCharts = $data['salesCharts'] ?? null;
    $departmentSummaries = collect($data['departmentSummaries'] ?? []);
    $recentDocs = collect($data['recentDocuments'] ?? []);
    $userDepartment = optional($user->department)->name ?? 'No Department';
    $periodLabel = now()->translatedFormat('D, M j, Y');

    $formatMoney = fn ($value = 0) => config('company.currency', 'USD') . ' ' . number_format((float) ($value ?? 0), 0);

    $scheduleStart = today();
    $scheduleEnd = today()->addDays(30);
    $isHrStaff = $user->isHR() && !$user->isAdmin();
    $scheduleEvents = $isHrStaff ? collect() : collect($activity['upcomingFollowUpsList'] ?? [])
        ->filter(fn ($followUp) => $followUp->follow_up_date && $followUp->follow_up_date->lte($scheduleEnd))
        ->map(fn ($followUp) => [
            'sort_at' => $followUp->follow_up_date->format('Y-m-d') . ' ' . ($followUp->follow_up_time ?: '23:59:59'),
            'date' => $followUp->follow_up_date,
            'title' => $followUp->title,
            'time' => $followUp->follow_up_time ? \Illuminate\Support\Carbon::parse($followUp->follow_up_time)->format('g:i A') : 'Time not set',
            'type' => 'Follow-up',
            'tone' => 'follow-up',
            'icon' => 'bi-telephone-forward',
            'status' => str_replace('_', ' ', ucfirst(strtolower($followUp->status))),
            'url' => route('crm.follow-ups.show', $followUp),
        ]);

    if (!$isHrStaff) {
        $scheduleEvents = $scheduleEvents->concat(
        \App\Models\Reminder::query()->visibleTo($user)
            ->whereNull('employee_id')
            ->whereNull('reminder_type')
            ->whereIn('status', ['PENDING', 'SNOOZED'])
            ->whereBetween('reminder_date', [$scheduleStart->toDateString(), $scheduleEnd->toDateString()])
            ->with(['customer', 'opportunity'])
            ->orderBy('reminder_date')->orderBy('reminder_time')
            ->limit(10)
            ->get()
            ->map(fn ($reminder) => [
                'sort_at' => $reminder->reminder_date->format('Y-m-d') . ' ' . ($reminder->reminder_time ?: '23:59:59'),
                'date' => $reminder->reminder_date,
                'title' => $reminder->title,
                'time' => $reminder->reminder_time ? \Illuminate\Support\Carbon::parse($reminder->reminder_time)->format('g:i A') : 'Time not set',
                'type' => 'Reminder',
                'tone' => 'reminder',
                'icon' => 'bi-bell',
                'status' => str_replace('_', ' ', ucfirst(strtolower($reminder->status))),
                'url' => route('crm.reminders.show', $reminder),
            ])
        );
    }

    $leaveQuery = \App\Models\LeaveRequest::query()
        ->with(['user', 'leaveType'])
        ->whereIn('status', ['PENDING', 'APPROVED'])
        ->whereDate('end_date', '>=', $scheduleStart->toDateString())
        ->whereDate('start_date', '<=', $scheduleEnd->toDateString());

    if (!$user->isAdmin()) {
        if ($user->isManager() || $user->isHR()) {
            $leaveQuery->whereHas('user', fn ($employee) => $employee->where('department_id', $user->department_id));
        } else {
            $leaveQuery->where('user_id', $user->id);
        }
    }

    $scheduleEvents = $scheduleEvents->concat(
        $leaveQuery->orderBy('start_date')->limit(10)->get()->map(function ($leave) use ($scheduleStart, $user) {
            $eventDate = $leave->start_date->lt($scheduleStart) ? $scheduleStart->copy() : $leave->start_date;

            return [
                'sort_at' => $eventDate->format('Y-m-d') . ' 00:00:00',
                'date' => $eventDate,
                'title' => $leave->leaveType?->name ?? 'Leave request',
                'time' => 'All day',
                'type' => 'Leave',
                'tone' => 'leave',
                'icon' => 'bi-calendar2-check',
                'status' => $leave->status === 'PENDING' ? 'Pending approval' : 'Approved',
                'url' => $user->isAdmin() || $user->isManager()
                    ? route('manager.leave.show', $leave)
                    : ($user->role === 'HR_EMPLOYEE' ? route('hr.leaves.index') : route('employee.leave.index')),
            ];
        })
    )->sortBy('sort_at')->take(5)->values();

    $calendarEventDates = $scheduleEvents->pluck('date')
        ->map(fn ($date) => $date->format('Y-m-d'))
        ->unique()
        ->values();
    $scheduleUrl = $user->isAdmin() || $user->isManager()
        ? route('manager.leave.calendar')
        : ($user->role === 'HR_EMPLOYEE' ? route('hr.leaves.index') : route('employee.leave.index'));
    $scheduleLinkLabel = $user->isAdmin() || $user->isManager() ? 'View calendar' : 'View all';

    $kpis = [];

    if ($user->isAdmin()) {
        $kpis = [
            ['label' => 'Total Employees', 'value' => $data['totalEmployees'] ?? 0, 'icon' => 'bi-people', 'tone' => 'primary'],
            ['label' => 'Present Today', 'value' => $data['todayPresent'] ?? 0, 'icon' => 'bi-calendar-check', 'tone' => 'success'],
            ['label' => 'Total Customers', 'value' => $data['totalCustomers'] ?? 0, 'icon' => 'bi-person-rolodex', 'tone' => 'info'],
            ['label' => 'Open Opportunities', 'value' => $data['openOpportunities'] ?? 0, 'icon' => 'bi-funnel', 'tone' => 'warning'],
            ['label' => 'Monthly Sales', 'value' => $formatMoney($data['monthlySales'] ?? 0), 'icon' => 'bi-currency-dollar', 'tone' => 'success'],
        ];
    } elseif ($user->isManager()) {
        $managerCards = $data['managerDashboard']['cards'] ?? [];
        $kpis = collect($managerCards)->take(5)->map(function ($item) {
            return ['label' => $item['label'], 'value' => $item['value'], 'icon' => $item['icon'], 'tone' => $item['tone']];
        })->all();
    } elseif ($user->isHR()) {
        $kpis = [
            ['label' => 'Total Employees', 'value' => $data['totalEmployees'] ?? 0, 'icon' => 'bi-people', 'tone' => 'primary'],
            ['label' => 'Present', 'value' => $data['todayPresent'] ?? 0, 'icon' => 'bi-check-circle', 'tone' => 'success'],
            ['label' => 'Late', 'value' => $data['todayLate'] ?? 0, 'icon' => 'bi-clock', 'tone' => 'warning'],
            ['label' => 'Absent', 'value' => $data['todayAbsent'] ?? 0, 'icon' => 'bi-x-circle', 'tone' => 'danger'],
            ['label' => 'Pending Leaves', 'value' => $data['pendingLeaves'] ?? 0, 'icon' => 'bi-calendar2-x', 'tone' => 'info'],
        ];
    } elseif ($user->isSales()) {
        $kpis = [
            ['label' => 'My Customers', 'value' => $data['myCustomers'] ?? 0, 'icon' => 'bi-person-rolodex', 'tone' => 'primary'],
            ['label' => 'Open Opportunities', 'value' => $data['openOpportunities'] ?? 0, 'icon' => 'bi-funnel', 'tone' => 'success'],
            ['label' => 'Follow-Ups', 'value' => $data['pendingFollowUps'] ?? 0, 'icon' => 'bi-telephone-forward', 'tone' => 'warning'],
            ['label' => 'Monthly Sales', 'value' => $formatMoney($data['monthlySales'] ?? 0), 'icon' => 'bi-currency-dollar', 'tone' => 'info'],
        ];
    } elseif ($user->isMarketing()) {
        $kpis = [
            ['label' => 'My Customers', 'value' => $data['myCustomers'] ?? 0, 'icon' => 'bi-person-rolodex', 'tone' => 'primary'],
            ['label' => 'Pending Tasks', 'value' => $data['pendingTasks'] ?? 0, 'icon' => 'bi-list-task', 'tone' => 'warning'],
            ['label' => 'Follow-Ups', 'value' => $data['pendingTasks'] ?? 0, 'icon' => 'bi-telephone-forward', 'tone' => 'info'],
            ['label' => 'Activities', 'value' => $activity['todayFollowUps'] ?? 0, 'icon' => 'bi-activity', 'tone' => 'success'],
            ['label' => 'Notifications', 'value' => $notificationPanel['unreadCount'] ?? 0, 'icon' => 'bi-bell', 'tone' => 'primary'],
        ];
    } else {
        $kpis = [
            ['label' => 'Today', 'value' => $data['todayAttendance'] ? strtoupper($data['todayAttendance']->status ?? 'NO STATUS') : 'NOT CHECKED IN', 'icon' => 'bi-calendar-check', 'tone' => 'primary'],
            ['label' => 'Pending Leave', 'value' => $data['pendingLeaves'] ?? 0, 'icon' => 'bi-hourglass-split', 'tone' => 'warning'],
            ['label' => 'Reminders', 'value' => $data['myReminders'] ?? 0, 'icon' => 'bi-bell', 'tone' => 'info'],
            ['label' => 'Documents', 'value' => $data['myDocuments'] ?? 0, 'icon' => 'bi-file-earmark-text', 'tone' => 'success'],
            ['label' => 'Follow-Ups', 'value' => $activity['todayFollowUps'] ?? 0, 'icon' => 'bi-telephone-forward', 'tone' => 'primary'],
        ];
    }
@endphp

<div class="dashboard-shell">
    <header class="dashboard-header">
        <div>
            <div class="dashboard-eyebrow">Overview</div>
            <h2>Good morning, {{ $user->name }}</h2>
            <p>{{ $user->role }} • {{ $userDepartment }}</p>
        </div>

        <div class="dashboard-actions">
            <div class="dropdown dashboard-calendar">
                <button type="button" class="dashboard-pill" id="dashboardCalendarToggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Open calendar">
                    <i class="bi bi-calendar3" aria-hidden="true"></i> {{ $periodLabel }}
                </button>
                <div class="dropdown-menu dropdown-menu-end calendar-popover" aria-labelledby="dashboardCalendarToggle"
                     data-calendar-month="{{ now()->format('Y-m') }}"
                     data-calendar-today="{{ now()->toDateString() }}"
                     data-calendar-events="{{ $calendarEventDates->toJson() }}">
                    <div class="calendar-popover-header">
                        <button type="button" class="calendar-month-nav" data-calendar-month-shift="-1" aria-label="Previous month"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                        <span class="calendar-month-label" aria-live="polite"></span>
                        <button type="button" class="calendar-month-nav" data-calendar-month-shift="1" aria-label="Next month"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                    </div>
                    <div class="calendar-grid" aria-label="Monthly calendar">
                        <span class="calendar-weekday">Mo</span><span class="calendar-weekday">Tu</span><span class="calendar-weekday">We</span><span class="calendar-weekday">Th</span><span class="calendar-weekday">Fr</span><span class="calendar-weekday">Sa</span><span class="calendar-weekday">Su</span>
                    </div>
                    <div class="calendar-grid calendar-days" role="grid"></div>
                    <div class="calendar-popover-footer">
                        <a href="{{ $scheduleUrl }}">{{ $scheduleLinkLabel }}</a>
                    </div>
                </div>
            </div>
            <button type="button" class="dashboard-btn" id="dashboardRefresh" aria-label="Refresh dashboard">
                <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Refresh
            </button>
        </div>
    </header>

    <section class="kpi-grid">
        @foreach($kpis as $metric)
            <div class="kpi-card tone-{{ $metric['tone'] }}">
                <div class="meta">
                    <div class="kpi-label">{{ $metric['label'] }}</div>
                    <div class="kpi-value">{{ $metric['value'] }}</div>
                </div>
                <div class="kpi-icon bg-{{ $metric['tone'] }} bg-opacity-10 text-{{ $metric['tone'] }}">
                    <i class="bi {{ $metric['icon'] }}"></i>
                </div>
            </div>
        @endforeach
    </section>

    @if($salesCharts)
        <section class="analytics-grid">
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <span class="panel-caption">Performance overview</span>
                        <h3>Booked Sales vs Forecast</h3>
                    </div>
                    <div class="panel-kicker">{{ $salesCharts['currency'] }} {{ number_format($salesCharts['weightedPipelineTotal'] ?? 0, 0) }}</div>
                </div>
                <div class="chart-shell">
                    <canvas id="sales-outlook-chart" aria-label="Booked sales and weighted forecast chart"></canvas>
                </div>
            </article>

            <article class="panel">
                <div class="panel-header">
                    <div>
                        <span class="panel-caption">Pipeline</span>
                        <h3>By Stage</h3>
                    </div>
                </div>
                <div class="chart-shell small">
                    <canvas id="pipeline-stage-chart" aria-label="Opportunity pipeline by stage chart"></canvas>
                </div>
            </article>
        </section>
    @elseif($user->isHR())
        <section class="panel" style="margin-top: 20px;">
            <div class="panel-header">
                <div>
                    <span class="panel-caption">Attendance overview</span>
                    <h3>{{ $data['attendanceChart']['month'] ?? 'Monthly attendance' }}</h3>
                </div>
            </div>
            <div class="chart-shell small">
                <canvas id="attendance-overview-chart" aria-label="Attendance overview chart"></canvas>
            </div>
        </section>
    @else
        <section class="panel" style="margin-top: 20px;">
            <div class="panel-header">
                <div>
                    <span class="panel-caption">My activity</span>
                    <h3>Today</h3>
                </div>
            </div>
            <div class="mini-stat-list">
                <div class="mini-stat-item">
                    <span class="label">Overdue Follow-Ups</span>
                    <span class="value danger">{{ $activity['overdueFollowUps'] ?? 0 }}</span>
                </div>
                <div class="mini-stat-item">
                    <span class="label">Today's Follow-Ups</span>
                    <span class="value info">{{ $activity['todayFollowUps'] ?? 0 }}</span>
                </div>
                <div class="mini-stat-item">
                    <span class="label">Upcoming Follow-Ups</span>
                    <span class="value success">{{ $activity['upcomingFollowUps'] ?? 0 }}</span>
                </div>
                <div class="mini-stat-item">
                    <span class="label">Overdue Reminders</span>
                    <span class="value warning">{{ $activity['overdueReminders'] ?? 0 }}</span>
                </div>
            </div>
        </section>
    @endif

    <section class="content-grid">
        @unless($isHrStaff)
        <article class="panel">
            <div class="panel-header">
                <div>
                    <span class="panel-caption">Activity</span>
                    <h3>Important activity</h3>
                </div>
            </div>
            <div class="mini-stat-list">
                <div class="mini-stat-item">
                    <span class="label">Overdue Follow-Ups</span>
                    <span class="value danger">{{ $activity['overdueFollowUps'] ?? 0 }}</span>
                </div>
                <div class="mini-stat-item">
                    <span class="label">Today's Follow-Ups</span>
                    <span class="value info">{{ $activity['todayFollowUps'] ?? 0 }}</span>
                </div>
                <div class="mini-stat-item">
                    <span class="label">Upcoming Follow-Ups</span>
                    <span class="value success">{{ $activity['upcomingFollowUps'] ?? 0 }}</span>
                </div>
                <div class="mini-stat-item">
                    <span class="label">Overdue Reminders</span>
                    <span class="value warning">{{ $activity['overdueReminders'] ?? 0 }}</span>
                </div>
            </div>
        </article>
        @endunless

        <article class="panel">
            <div class="panel-header">
                <div>
                    <span class="panel-caption">Action required</span>
                    <h3>Priorities</h3>
                </div>
            </div>
            <div class="action-list">
                @if($isHrStaff)
                    <div class="action-item">
                        <div>
                            <strong>Pending leave approvals</strong>
                            <div class="meta">Review employee requests</div>
                        </div>
                        <span class="action-badge warning">{{ $notificationPanel['pendingLeaveApprovals'] ?? 0 }}</span>
                    </div>
                    <div class="action-item">
                        <div>
                            <strong>Documents waiting approval</strong>
                            <div class="meta">Open approvals queue</div>
                        </div>
                        <span class="action-badge primary">{{ $notificationPanel['pendingDocumentApprovals'] ?? 0 }}</span>
                    </div>
                    <div class="action-item">
                        <div>
                            <strong>Unread notifications</strong>
                            <div class="meta">Recent updates</div>
                        </div>
                        <span class="action-badge primary">{{ $notificationPanel['unreadCount'] ?? 0 }}</span>
                    </div>
                @elseif($user->isAdmin() || $user->isManager())
                    <div class="action-item">
                        <div>
                            <strong>Pending leave approvals</strong>
                            <div class="meta">Review current requests</div>
                        </div>
                        <span class="action-badge warning">{{ $notificationPanel['pendingLeaveApprovals'] ?? 0 }}</span>
                    </div>
                    <div class="action-item">
                        <div>
                            <strong>Documents waiting approval</strong>
                            <div class="meta">Open approvals queue</div>
                        </div>
                        <span class="action-badge primary">{{ $notificationPanel['pendingDocumentApprovals'] ?? 0 }}</span>
                    </div>
                    <div class="action-item">
                        <div>
                            <strong>Overdue follow-ups</strong>
                            <div class="meta">Needs attention</div>
                        </div>
                        <span class="action-badge danger">{{ $notificationPanel['overdueFollowUps'] ?? 0 }}</span>
                    </div>
                @elseif($user->isSales())
                    <div class="action-item">
                        <div>
                            <strong>Overdue follow-ups</strong>
                            <div class="meta">Customer follow-up</div>
                        </div>
                        <span class="action-badge danger">{{ $activity['overdueFollowUps'] ?? 0 }}</span>
                    </div>
                    <div class="action-item">
                        <div>
                            <strong>Today's follow-ups</strong>
                            <div class="meta">Open tasks today</div>
                        </div>
                        <span class="action-badge primary">{{ $activity['todayFollowUps'] ?? 0 }}</span>
                    </div>
                    <div class="action-item">
                        <div>
                            <strong>Important opportunities</strong>
                            <div class="meta">Pipeline review</div>
                        </div>
                        <span class="action-badge success">{{ $data['openOpportunities'] ?? 0 }}</span>
                    </div>
                @else
                    <div class="action-item">
                        <div>
                            <strong>Pending leave</strong>
                            <div class="meta">Leave request status</div>
                        </div>
                        <span class="action-badge warning">{{ $data['pendingLeaves'] ?? 0 }}</span>
                    </div>
                    <div class="action-item">
                        <div>
                            <strong>Unread notifications</strong>
                            <div class="meta">Action items</div>
                        </div>
                        <span class="action-badge primary">{{ $notificationPanel['unreadCount'] ?? 0 }}</span>
                    </div>
                    <div class="action-item">
                        <div>
                            <strong>Overdue tasks</strong>
                            <div class="meta">Need review</div>
                        </div>
                        <span class="action-badge danger">{{ $activity['overdueFollowUps'] ?? 0 }}</span>
                    </div>
                @endif
            </div>
        </article>

        <article class="panel">
            <div class="panel-header">
                <div>
                    <span class="panel-caption">Next 30 days</span>
                    <h3>Upcoming schedule</h3>
                </div>
                <a href="{{ $scheduleUrl }}" class="link-btn">{{ $scheduleLinkLabel }}</a>
            </div>
            <div class="schedule-list">
                @forelse($scheduleEvents as $event)
                    <a href="{{ $event['url'] }}" class="schedule-row" title="{{ $event['status'] }}">
                        <span class="schedule-icon {{ $event['tone'] }}"><i class="bi {{ $event['icon'] }}" aria-hidden="true"></i></span>
                        <span class="schedule-copy">
                            <span class="schedule-title">{{ $event['title'] }}</span>
                            <span class="schedule-meta">{{ $event['date']->format('M j') }} · {{ $event['time'] }} · {{ $event['status'] }}</span>
                        </span>
                        <span class="schedule-kind {{ $event['tone'] }}">{{ $event['type'] }}</span>
                    </a>
                @empty
                    <div class="schedule-empty"><i class="bi bi-calendar2-week me-1" aria-hidden="true"></i>No upcoming events in the next 30 days.</div>
                @endforelse
            </div>
        </article>
    </section>

    @if($user->isAdmin() || $user->isManager())
        <section class="panel" style="margin-top: 20px;">
            <div class="panel-header">
                <div>
                    <span class="panel-caption">Department insight</span>
                    <h3>{{ $user->isAdmin() ? 'Department performance' : 'Current department' }}</h3>
                </div>
                <a href="{{ route('dashboard') }}" class="link-btn">View all</a>
            </div>

            <div class="table-panel">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Employees</th>
                            <th>Sales</th>
                            <th>Open</th>
                            <th>Leave</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($departmentSummaries->take(5) as $summary)
                            <tr>
                                <td><strong>{{ $summary['name'] ?? 'Department' }}</strong></td>
                                <td>{{ $summary['employees'] ?? 0 }}</td>
                                <td>{{ $formatMoney($summary['sales'] ?? 0) }}</td>
                                <td>{{ $summary['opportunities'] ?? 0 }}</td>
                                <td>{{ $summary['leave'] ?? 0 }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">No department data available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="doc-grid">
        <article class="panel">
            <div class="panel-header">
                <div>
                    <span class="panel-caption">Documents</span>
                    <h3>Recent documents</h3>
                </div>
                <a href="{{ route('documents.index') }}" class="link-btn">View all</a>
            </div>

            <div class="list-panel">
                @forelse($recentDocs->take(5) as $doc)
                    <div class="doc-row">
                        <div class="doc-main">
                            <a href="{{ route('documents.show', $doc) }}" class="pill-title text-decoration-none text-dark">{{ $doc->title }}</a>
                            <div class="pill-subtitle">{{ $doc->department->name ?? 'General' }} • {{ $doc->document_type }}</div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="{{ route('documents.show', $doc) }}" class="btn btn-sm btn-outline-primary">View</a>
                            <span class="status-badge {{ $doc->status === 'PUBLISHED' ? 'success' : 'primary' }}">{{ $doc->status ?? 'ACTIVE' }}</span>
                        </div>
                    </div>
                @empty
                    <div class="pill-subtitle">No recent documents are available.</div>
                @endforelse
            </div>
        </article>

        <article class="panel">
            <div class="panel-header">
                <div>
                    <span class="panel-caption">Updates</span>
                    <h3>Notifications</h3>
                </div>
                <a href="{{ route('notifications.index') }}" class="link-btn">View all</a>
            </div>

            <div class="list-panel">
                @forelse((collect($notificationPanel['recent'] ?? []))->take(5) as $notification)
                    <div class="notification-row">
                        <div class="notification-main">
                            <div class="pill-title">{{ $notification->title ?? 'Notification' }}</div>
                            <div class="pill-subtitle">{{ $notification->message ?? 'New update' }}</div>
                        </div>
                        <span class="status-badge {{ $notification->is_read ? 'primary' : 'danger' }}">{{ $notification->is_read ? 'Read' : 'New' }}</span>
                    </div>
                @empty
                    <div class="pill-subtitle">No recent notifications.</div>
                @endforelse
            </div>
        </article>
    </section>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const refreshButton = document.getElementById('dashboardRefresh');
        if (refreshButton) {
            refreshButton.addEventListener('click', function () {
                window.location.reload();
            });
        }

        const calendar = document.querySelector('.calendar-popover');
        if (!calendar) return;

        const monthLabel = calendar.querySelector('.calendar-month-label');
        const daysContainer = calendar.querySelector('.calendar-days');
        const eventDates = new Set(JSON.parse(calendar.dataset.calendarEvents || '[]'));
        const today = calendar.dataset.calendarToday;
        let displayedMonth = calendar.dataset.calendarMonth;

        function renderCalendar() {
            const [year, month] = displayedMonth.split('-').map(Number);
            const firstDay = new Date(Date.UTC(year, month - 1, 1));
            const daysInMonth = new Date(Date.UTC(year, month, 0)).getUTCDate();
            const mondayFirstOffset = (firstDay.getUTCDay() + 6) % 7;

            monthLabel.textContent = firstDay.toLocaleDateString(undefined, {
                month: 'long',
                year: 'numeric',
                timeZone: 'UTC'
            });
            daysContainer.replaceChildren();

            for (let blank = 0; blank < mondayFirstOffset; blank += 1) {
                const emptyDay = document.createElement('span');
                emptyDay.setAttribute('aria-hidden', 'true');
                daysContainer.appendChild(emptyDay);
            }

            for (let day = 1; day <= daysInMonth; day += 1) {
                const dateKey = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const dayCell = document.createElement('span');
                dayCell.className = 'calendar-day';
                dayCell.textContent = String(day);
                dayCell.setAttribute('role', 'gridcell');
                if (dateKey === today) {
                    dayCell.classList.add('is-today');
                    dayCell.setAttribute('aria-current', 'date');
                }
                if (eventDates.has(dateKey)) {
                    dayCell.classList.add('has-event');
                    dayCell.setAttribute('title', 'Scheduled event');
                }
                daysContainer.appendChild(dayCell);
            }
        }

        calendar.querySelectorAll('[data-calendar-month-shift]').forEach(function (button) {
            button.addEventListener('click', function () {
                const [year, month] = displayedMonth.split('-').map(Number);
                const shiftedMonth = new Date(Date.UTC(year, month - 1 + Number(button.dataset.calendarMonthShift), 1));
                displayedMonth = `${shiftedMonth.getUTCFullYear()}-${String(shiftedMonth.getUTCMonth() + 1).padStart(2, '0')}`;
                renderCalendar();
            });
        });

        renderCalendar();
    });
</script>
@endpush

@push('scripts')
@if($salesCharts)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (!window.Chart) return;

    const chartData = @json($salesCharts);
    const colors = {
        booked: '#2563EB',
        forecast: '#0EA5E9',
        grid: 'rgba(100, 116, 139, 0.16)',
        text: '#64748B'
    };

    const formatCurrency = (value, compact = false) => new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: chartData.currency,
        maximumFractionDigits: 0,
        ...(compact ? { notation: 'compact' } : {})
    }).format(value);

    Chart.defaults.font.family = "'Segoe UI', Tahoma, sans-serif";
    Chart.defaults.color = colors.text;

    const salesCanvas = document.getElementById('sales-outlook-chart');
    if (salesCanvas) {
        new Chart(salesCanvas, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Booked sales',
                        data: chartData.bookedSales,
                        backgroundColor: colors.booked,
                        borderRadius: 5,
                        maxBarThickness: 28
                    },
                    {
                        type: 'line',
                        label: 'Weighted forecast',
                        data: chartData.weightedForecast,
                        borderColor: colors.forecast,
                        backgroundColor: 'rgba(104, 121, 130, 0.12)',
                        pointBackgroundColor: colors.forecast,
                        pointRadius: 3,
                        borderWidth: 2,
                        tension: 0.35,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 16 } },
                    tooltip: { callbacks: { label: context => `${context.dataset.label}: ${formatCurrency(context.raw)}` } }
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false } },
                    y: {
                        beginAtZero: true,
                        grid: { color: colors.grid },
                        border: { display: false },
                        ticks: { callback: value => formatCurrency(value, true) }
                    }
                }
            }
        });
    }

    const stageCanvas = document.getElementById('pipeline-stage-chart');
    if (stageCanvas) {
        new Chart(stageCanvas, {
            type: 'bar',
            data: {
                labels: chartData.stageLabels,
                datasets: [{
                    label: 'Weighted value',
                    data: chartData.weightedByStage,
                    backgroundColor: ['#2563EB', '#22C55E', '#0EA5E9', '#8B5CF6'],
                    borderRadius: 6,
                    barThickness: 22
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: colors.grid },
                        border: { display: false },
                        ticks: { callback: value => formatCurrency(value, true) }
                    },
                    y: { grid: { display: false }, border: { display: false } }
                }
            }
        });
    }
});
</script>
@elseif(auth()->user()->isHR())
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.Chart) return;

    const attendanceCanvas = document.getElementById('attendance-overview-chart');
    if (!attendanceCanvas) return;

    new Chart(attendanceCanvas, {
        type: 'bar',
        data: {
        labels: @json($data['attendanceChart']['labels'] ?? []),
            datasets: [{
            label: 'Present',
            data: @json($data['attendanceChart']['present'] ?? []),
            backgroundColor: '#2563EB',
            borderRadius: 4
        }, {
            label: 'Late',
            data: @json($data['attendanceChart']['late'] ?? []),
            backgroundColor: '#F97316',
            borderRadius: 4
        }, {
            label: 'Absent',
            data: @json($data['attendanceChart']['absent'] ?? []),
            backgroundColor: '#EF4444',
            borderRadius: 4
        }, {
            label: 'Leave',
            data: @json($data['attendanceChart']['leave'] ?? []),
            backgroundColor: '#0EA5E9',
            borderRadius: 4
        }, {
            label: 'Holiday',
            data: @json($data['attendanceChart']['holiday'] ?? []),
            backgroundColor: '#8A9298',
            borderRadius: 4
        }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                x: { stacked: true, grid: { display: false }, border: { display: false }, title: { display: true, text: 'Day of month' } },
                y: { stacked: true, beginAtZero: true, grid: { color: 'rgba(36, 50, 59, 0.1)' }, border: { display: false }, title: { display: true, text: 'Attendance records' } }
            }
        }
    });
});
</script>
@endif
@endpush

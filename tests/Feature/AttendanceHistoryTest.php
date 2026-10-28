<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\CompanySettings;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttendanceHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_cannot_access_attendance_history_but_keep_personal_attendance(): void
    {
        $department = $this->department('SALES');
        $user = $this->user($department, 'SALES_EMPLOYEE', 'history.employee@example.com');
        $otherUser = $this->user($department, 'SALES_EMPLOYEE', 'history.other@example.com');
        $this->attendance($user, '2026-05-01', 'LATE', '10:00', 60, 'Traffic delay');
        $this->attendance($otherUser, '2026-05-02', 'PRESENT', '09:00', 0, 'Other user note');

        $this->actingAs($user)->get(route('employee.attendance.history'))->assertForbidden();
        $this->get(route('hr.attendance.history'))->assertForbidden();
        $this->get(route('manager.attendance.history'))->assertForbidden();
        $this->get(route('employee.attendance.index'))->assertOk();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('hr.attendance.history'), false)
            ->assertDontSee(route('manager.attendance.history'), false)
            ->assertDontSee('Attendance History');
    }

    public function test_non_hr_managers_cannot_access_attendance_history_urls(): void
    {
        $department = $this->department('OPS');
        $otherDepartment = $this->department('SALES');
        $manager = $this->user($department, 'OPS_MANAGER', 'history.manager@example.com');
        $teamMember = $this->user($department, 'OPS_EMPLOYEE', 'history.team@example.com');
        $outsideMember = $this->user($otherDepartment, 'SALES_EMPLOYEE', 'history.outside@example.com');
        $this->attendance($teamMember, '2026-05-03', 'LATE', '10:00', 60, 'Train delay');
        $this->attendance($outsideMember, '2026-05-03', 'PRESENT', '09:00', 0, 'Outside team note');
        $teamMember->update(['status' => 'INACTIVE']);

        $this->actingAs($manager)->get(route('manager.attendance.history'))->assertForbidden();
        $this->get(route('hr.attendance.history'))->assertForbidden();
        $this->get(route('hr.attendance.report'))->assertForbidden();
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('manager.attendance.history'), false)
            ->assertDontSee('Team Attendance History');
    }

    public function test_hr_managers_and_admins_can_filter_company_attendance_history_and_reports(): void
    {
        $hrDepartment = $this->department('HR');
        $salesDepartment = $this->department('SALES');
        $hrManager = $this->user($hrDepartment, 'HR_MANAGER', 'history.hr@example.com');
        $admin = $this->user($hrDepartment, 'ADMIN', 'history.admin@example.com');
        $hrEmployee = $this->user($hrDepartment, 'HR_EMPLOYEE', 'history.hr.employee@example.com');
        $salesManager = $this->user($salesDepartment, 'SALES_MANAGER', 'history.sales.manager@example.com');
        $salesEmployee = $this->user($salesDepartment, 'SALES_EMPLOYEE', 'history.sales@example.com');
        $adminDepartment = $this->department('ADMIN');
        $companyAdmin = $this->user($adminDepartment, 'ADMIN', 'history.company.admin@example.com');
        $this->attendance($salesEmployee, '2026-05-04', 'LEAVE', null, 0, 'Approved annual leave');
        $this->attendance($companyAdmin, '2026-05-04', 'PRESENT', '09:00', 0, 'Admin attendance');

        foreach ([$hrManager, $admin] as $viewer) {
            $this->actingAs($viewer)->get(route('hr.attendance.index'))->assertOk();
            $this->actingAs($viewer)->get(route('hr.attendance.history', [
                'department' => $salesDepartment->id,
                'status' => 'LEAVE',
            ]))->assertOk()
                ->assertSee($salesEmployee->name)
                ->assertSee('Approved annual leave')
                ->assertViewHas('summary', fn (array $summary) => $summary['leave'] === 1);

            $this->get(route('hr.attendance.report', ['month' => '2026-05', 'department' => $salesDepartment->id]))
                ->assertOk()
                ->assertSee($salesEmployee->name);

            $this->get(route('manager.attendance.history'))
                ->assertRedirect(route('hr.attendance.history'));

            $this->get(route('employee.attendance.history'))->assertOk();

            $this->get(route('hr.attendance.history', [
                'department' => $adminDepartment->id,
                'from_date' => '2026-05-04',
                'to_date' => '2026-05-04',
            ]))->assertOk()
                ->assertSee($companyAdmin->name)
                ->assertSee('Admin attendance');
        }

        $this->actingAs($hrEmployee)->get(route('hr.attendance.history'))->assertForbidden();
        $this->get(route('hr.attendance.report'))->assertForbidden();
        $this->get(route('employee.attendance.history'))->assertForbidden();
        $this->actingAs($salesManager)->get(route('hr.attendance.history'))->assertForbidden();
        $this->get(route('hr.attendance.report'))->assertForbidden();
    }

    public function test_hr_manager_role_outside_hr_department_cannot_view_company_attendance(): void
    {
        $salesDepartment = $this->department('SALES');
        $misassignedManager = $this->user($salesDepartment, 'HR_MANAGER', 'history.misassigned.hr@example.com');

        $this->actingAs($misassignedManager)->get(route('hr.attendance.history'))->assertForbidden();
        $this->get(route('hr.attendance.index'))->assertForbidden();
        $this->get(route('hr.attendance.report'))->assertForbidden();
        $this->get(route('manager.attendance.history'))->assertForbidden();
        $target = $this->user($salesDepartment, 'SALES_EMPLOYEE', 'history.misassigned.target@example.com');
        $this->post(route('hr.attendance.manual-check-in'), [
            'user_id' => $target->id,
            'attendance_date' => today()->toDateString(),
            'check_in_time' => '09:00',
            'reason' => 'Attempted unauthorized mutation.',
        ])->assertForbidden();
    }

    public function test_daily_monthly_and_yearly_reports_use_workdays_leave_and_shared_attendance(): void
    {
        $hrDepartment = $this->department('HR');
        $admin = $this->user($this->department('ADMIN'), 'ADMIN', 'attendance.report.admin@example.com');
        $employee = $this->user($hrDepartment, 'HR_EMPLOYEE', 'attendance.report.employee@example.com');
        CompanySettings::create([
            'company_name' => 'Test Company',
            'timezone' => 'Asia/Yangon',
            'currency' => 'USD',
            'date_format' => 'Y-m-d',
            'working_days' => ['MON', 'TUE', 'WED', 'THU', 'FRI'],
            'work_start' => '09:00',
            'work_end' => '17:00',
        ]);
        $this->travelTo(Carbon::parse('2026-09-30 18:00:00', 'Asia/Yangon'));

        Attendance::create([
            'user_id' => $employee->id,
            'attendance_date' => '2026-09-01',
            'check_in' => '09:15:00',
            'check_out' => '17:00:00',
            'working_hours' => 7.75,
            'late_minutes' => 15,
            'status' => 'LATE',
        ]);
        Attendance::create([
            'user_id' => $employee->id,
            'attendance_date' => '2026-09-02',
            'check_in' => '08:55:00',
            'check_out' => '17:00:00',
            'working_hours' => 8.08,
            'status' => 'PRESENT',
        ]);
        Holiday::create(['name' => 'Company Holiday', 'holiday_date' => '2026-09-30']);
        $leaveType = LeaveType::create([
            'name' => 'Annual Leave',
            'code' => 'REPORT-ANNUAL',
            'days_allowed' => 10,
            'status' => 'ACTIVE',
        ]);
        LeaveRequest::create([
            'request_number' => 'LR-REPORT-APPROVED',
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-09-28',
            'end_date' => '2026-09-28',
            'total_days' => 1,
            'reason' => 'Approved leave.',
            'status' => 'APPROVED',
        ]);

        $this->actingAs($admin)->get(route('hr.attendance.report', [
            'period' => 'monthly',
            'month' => '2026-09',
            'department' => $hrDepartment->id,
        ]))->assertOk()
            ->assertSee('Monthly report for September 2026')
            ->assertViewHas('reportRows', function ($rows) use ($employee): bool {
                $row = $rows->firstWhere('user.id', $employee->id);
                $this->assertSame(1, $row['present']);
                $this->assertSame(1, $row['late']);
                $this->assertSame(1, $row['leave']);
                $this->assertSame(18, $row['absent']);
                $this->assertSame(15.83, $row['total_hours']);

                return true;
            });

        $this->get(route('hr.attendance.report', [
            'period' => 'daily',
            'date' => '2026-09-30',
            'department' => $hrDepartment->id,
        ]))->assertOk()
            ->assertSee('Daily report for September 30, 2026')
            ->assertViewHas('reportRows', fn ($rows) => $rows->first()['absent'] === 0);

        $this->get(route('hr.attendance.report', [
            'period' => 'yearly',
            'year' => '2026',
            'department' => $hrDepartment->id,
        ]))->assertOk()
            ->assertSee('Yearly report for 2026')
            ->assertViewHas('reportRows', fn ($rows) => $rows->first()['present'] === 1);
    }

    public function test_admin_cannot_access_employee_leave_submission_pages_or_actions(): void
    {
        $admin = $this->user($this->department('ADMIN'), 'ADMIN', 'history.leave.admin@example.com');

        $this->actingAs($admin)->get(route('employee.leave.index'))->assertNotFound();
        $this->get(route('employee.leave.create'))->assertNotFound();
        $this->post(route('employee.leave.store'), [])->assertNotFound();
    }

    private function department(string $code): Department
    {
        return Department::create(['name' => $code, 'code' => $code, 'status' => 'ACTIVE']);
    }

    private function user(Department $department, string $role, string $email): User
    {
        return User::create([
            'employee_id' => strtoupper(Str::random(10)),
            'name' => ucfirst(strstr($email, '@', true)),
            'email' => $email,
            'password' => bcrypt('password'),
            'department_id' => $department->id,
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function attendance(User $user, string $date, string $status, ?string $checkIn, int $lateMinutes, string $reason): Attendance
    {
        return Attendance::create([
            'user_id' => $user->id,
            'attendance_date' => $date,
            'check_in' => $checkIn,
            'status' => $status,
            'late_minutes' => $lateMinutes,
            'manual_reason' => $reason,
        ]);
    }
}

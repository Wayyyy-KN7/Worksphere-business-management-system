<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\CompanySettings;
use App\Models\Department;
use App\Models\LeaveApproval;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Holiday;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceLeaveWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_daily_attendance_lists_active_users_without_records_and_counts_leave_and_admin_users(): void
    {
        $hrDepartment = $this->department('HR');
        $salesDepartment = $this->department('SALES');
        $hrManager = $this->user($hrDepartment, 'HR_MANAGER', 'daily.attendance.hr@example.com');
        $presentEmployee = $this->user($salesDepartment, 'SALES_EMPLOYEE', 'daily.attendance.present@example.com');
        $lateEmployee = $this->user($salesDepartment, 'SALES_EMPLOYEE', 'daily.attendance.late@example.com');
        $leaveEmployee = $this->user($salesDepartment, 'SALES_EMPLOYEE', 'daily.attendance.leave@example.com');
        $absentEmployee = $this->user($salesDepartment, 'SALES_EMPLOYEE', 'daily.attendance.absent@example.com');
        $admin = $this->user($this->department('ADMIN'), 'ADMIN', 'daily.attendance.admin@example.com');
        $date = '2026-09-28';

        CompanySettings::create([
            'company_name' => 'Test Company',
            'timezone' => 'Asia/Yangon',
            'currency' => 'USD',
            'date_format' => 'Y-m-d',
            'working_days' => ['MON', 'TUE', 'WED', 'THU', 'FRI'],
            'work_start' => '09:00',
            'work_end' => '17:00',
        ]);
        $this->travelTo(Carbon::parse($date . ' 18:00:00'));

        Attendance::create([
            'user_id' => $presentEmployee->id,
            'attendance_date' => $date,
            'check_in' => '08:55:00',
            'status' => 'PRESENT',
        ]);
        Attendance::create([
            'user_id' => $lateEmployee->id,
            'attendance_date' => $date,
            'check_in' => '09:20:00',
            'status' => 'LATE',
            'late_minutes' => 20,
        ]);
        $leaveType = $this->leaveType('Annual Leave', 'DAILY-ANNUAL', 10);
        LeaveRequest::create([
            'request_number' => 'LR-DAILY-APPROVED',
            'user_id' => $leaveEmployee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $date,
            'end_date' => $date,
            'total_days' => 1,
            'reason' => 'Approved annual leave.',
            'status' => 'APPROVED',
        ]);

        $response = $this->actingAs($hrManager)->get(route('hr.attendance.index', ['date' => $date]));
        $response->assertOk()
            ->assertSee($presentEmployee->name)
            ->assertSee($lateEmployee->name)
            ->assertSee($leaveEmployee->name)
            ->assertSee($absentEmployee->name)
            ->assertSee($admin->name)
            ->assertSee('On Leave')
            ->assertSee('Absent')
            ->assertViewHas('stats', function (array $stats): bool {
                $this->assertSame(6, $stats['total_employees']);
                $this->assertSame(1, $stats['present']);
                $this->assertSame(1, $stats['late']);
                $this->assertSame(3, $stats['absent']);

                return true;
            })
            ->assertViewHas('attendances', function ($attendances) use ($presentEmployee, $lateEmployee, $leaveEmployee, $absentEmployee, $admin, $hrManager): bool {
                $this->assertCount(6, $attendances);
                $byUser = $attendances->getCollection()->keyBy('user_id');
                $this->assertSame('PRESENT', $byUser[$presentEmployee->id]->status);
                $this->assertSame('LATE', $byUser[$lateEmployee->id]->status);
                $this->assertSame('LEAVE', $byUser[$leaveEmployee->id]->status);
                $this->assertSame('ABSENT', $byUser[$absentEmployee->id]->status);
                $this->assertSame('ABSENT', $byUser[$admin->id]->status);
                $this->assertSame('ABSENT', $byUser[$hrManager->id]->status);
                $this->assertFalse($byUser[$leaveEmployee->id]->exists);
                $this->assertFalse($byUser[$admin->id]->exists);

                return true;
            });

        $this->assertSame(2, Attendance::whereDate('attendance_date', $date)->count());

        $this->get(route('hr.attendance.index', [
            'date' => $date,
            'department' => $salesDepartment->id,
            'status' => 'LATE',
        ]))->assertOk()
            ->assertViewHas('stats', function (array $stats): bool {
                $this->assertSame(1, $stats['total_employees']);
                $this->assertSame(0, $stats['present']);
                $this->assertSame(1, $stats['late']);
                $this->assertSame(0, $stats['absent']);

                return true;
            });

        $this->travelTo(Carbon::parse($date . ' 12:00:00'));
        $this->actingAs($hrManager)->get(route('hr.attendance.index', ['date' => $date]))
            ->assertOk()
            ->assertSee('Not Checked In')
            ->assertViewHas('stats', fn (array $stats) => $stats['absent'] === 0);
    }

    public function test_daily_attendance_does_not_mark_holidays_non_working_days_or_future_dates_absent(): void
    {
        $department = $this->department('HR');
        $hrManager = $this->user($department, 'HR_MANAGER', 'daily.attendance.dates@example.com');
        $this->user($department, 'HR_EMPLOYEE', 'daily.attendance.employee@example.com');
        CompanySettings::create([
            'company_name' => 'Test Company',
            'timezone' => 'Asia/Yangon',
            'currency' => 'USD',
            'date_format' => 'Y-m-d',
            'working_days' => ['MON', 'TUE', 'WED', 'THU', 'FRI'],
            'work_start' => '09:00',
            'work_end' => '17:00',
        ]);
        Holiday::create(['name' => 'Company Holiday', 'holiday_date' => '2026-09-28']);
        $this->travelTo(Carbon::parse('2026-09-28 18:00:00'));

        foreach ([
            '2026-09-27' => 'NON_WORKING_DAY',
            '2026-09-28' => 'HOLIDAY',
            '2026-09-29' => 'NOT_CHECKED_IN',
        ] as $date => $expectedStatus) {
            $this->actingAs($hrManager)->get(route('hr.attendance.index', ['date' => $date]))
                ->assertOk()
                ->assertViewHas('attendances', fn ($attendances) => $attendances->getCollection()
                    ->every(fn (Attendance $attendance) => $attendance->status === $expectedStatus))
                ->assertViewHas('stats', fn (array $stats) => $stats['absent'] === 0);
        }
    }

    public function test_employee_check_in_uses_company_work_start_and_counts_late_minutes_positive(): void
    {
        $department = $this->department('SALES');
        $employee = $this->user($department, 'SALES_EMPLOYEE', 'attendance.employee@example.com');
        $hrManager = $this->user($this->department('HR'), 'HR_MANAGER', 'attendance.employee.viewer@example.com');
        CompanySettings::create([
            'company_name' => 'Test Company',
            'timezone' => 'Asia/Yangon',
            'currency' => 'USD',
            'date_format' => 'Y-m-d',
            'working_days' => ['MON', 'TUE', 'WED', 'THU', 'FRI'],
            'work_start' => '09:30',
            'work_end' => '17:30',
        ]);

        $this->travelTo(Carbon::parse('2026-09-28 10:00:00'));
        $this->actingAs($employee)->post(route('employee.attendance.check-in'))->assertRedirect();

        $attendance = Attendance::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame('LATE', $attendance->status);
        $this->assertSame(30, $attendance->late_minutes);

        $this->actingAs($hrManager)->get(route('hr.attendance.index', ['date' => '2026-09-28']))
            ->assertOk()
            ->assertSee($employee->name)
            ->assertSee('Late');
    }

    public function test_hr_manual_check_in_uses_company_start_time_and_positive_late_minutes(): void
    {
        $department = $this->department('HR');
        $hrManager = $this->user($department, 'HR_MANAGER', 'attendance.hr@example.com');
        $employee = $this->user($department, 'HR_EMPLOYEE', 'attendance.target@example.com');
        CompanySettings::create([
            'company_name' => 'Test Company',
            'timezone' => 'Asia/Yangon',
            'currency' => 'USD',
            'date_format' => 'Y-m-d',
            'working_days' => ['MON', 'TUE', 'WED', 'THU', 'FRI'],
            'work_start' => '09:30',
            'work_end' => '17:30',
        ]);

        $this->actingAs($hrManager)->post(route('hr.attendance.manual-check-in'), [
            'user_id' => $employee->id,
            'attendance_date' => '2026-09-28',
            'check_in_time' => '10:00',
            'reason' => 'Time clock unavailable.',
        ])->assertRedirect();

        $this->assertSame(30, Attendance::where('user_id', $employee->id)->value('late_minutes'));
    }

    public function test_team_leave_calendar_shows_pending_requests_before_leave_is_approved(): void
    {
        $department = $this->department('SALES');
        $manager = $this->user($department, 'SALES_MANAGER', 'leave.calendar.manager@example.com');
        $employee = $this->user($department, 'SALES_EMPLOYEE', 'leave.calendar.employee@example.com');
        $leaveType = $this->leaveType('Annual Leave', 'CALENDAR-ANNUAL', 15);

        $pendingLeave = LeaveRequest::create([
            'request_number' => 'LR-CALENDAR-PENDING',
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => today()->addDays(3),
            'end_date' => today()->addDays(4),
            'total_days' => 2,
            'reason' => 'Family appointment',
            'status' => 'PENDING',
        ]);

        $this->actingAs($manager)->get(route('manager.leave.calendar'))
            ->assertOk()
            ->assertSee($employee->name)
            ->assertSee('Family appointment')
            ->assertSee('Pending approval')
            ->assertViewHas('leaveRequests', fn ($leaveRequests) => $leaveRequests->contains('id', $pendingLeave->id));
    }

    public function test_hr_manual_checkout_does_not_overwrite_an_existing_checkout(): void
    {
        $department = $this->department('HR');
        $hrManager = $this->user($department, 'HR_MANAGER', 'checkout.hr@example.com');
        $employee = $this->user($department, 'HR_EMPLOYEE', 'checkout.employee@example.com');
        $attendance = Attendance::create([
            'user_id' => $employee->id,
            'attendance_date' => today()->toDateString(),
            'check_in' => '09:00:00',
            'check_out' => '17:00:00',
            'working_hours' => 8,
            'status' => 'PRESENT',
        ]);

        $this->actingAs($hrManager)->post(route('hr.attendance.manual-check-out'), [
            'attendance_id' => $attendance->id,
            'check_out_time' => '18:00',
            'reason' => 'Attempt duplicate checkout.',
        ])->assertSessionHas('error');

        $this->assertSame('17:00:00', $attendance->fresh()->check_out);
        $this->assertSame(8.0, (float) $attendance->fresh()->working_hours);
    }

    public function test_employee_cannot_check_in_on_a_configured_non_working_day(): void
    {
        $department = $this->department('SALES');
        $employee = $this->user($department, 'SALES_EMPLOYEE', 'attendance.weekend@example.com');
        CompanySettings::create([
            'company_name' => 'Test Company',
            'timezone' => 'Asia/Yangon',
            'currency' => 'USD',
            'date_format' => 'Y-m-d',
            'working_days' => ['TUE', 'WED', 'THU', 'FRI'],
            'work_start' => '09:00',
            'work_end' => '17:00',
        ]);
        $this->travelTo(Carbon::parse('2026-09-28 08:30:00'));

        $this->actingAs($employee)->post(route('employee.attendance.check-in'))->assertSessionHas('error');
        $this->assertSame(0, Attendance::where('user_id', $employee->id)->count());
        $this->get(route('employee.attendance.index'))->assertOk()->assertSee('not a configured working day');
    }

    public function test_hr_employee_keeps_personal_attendance_but_cannot_view_or_change_company_attendance(): void
    {
        $department = $this->department('HR');
        $hrEmployee = $this->user($department, 'HR_EMPLOYEE', 'attendance.hr.employee@example.com');
        $target = $this->user($department, 'HR_EMPLOYEE', 'attendance.hr.target@example.com');

        $this->actingAs($hrEmployee)->get(route('employee.attendance.index'))->assertOk();
        $this->get(route('hr.attendance.index'))->assertForbidden();
        $this->get(route('hr.attendance.history'))->assertForbidden();
        $this->get(route('hr.attendance.report'))->assertForbidden();
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('hr.attendance.index'), false);
        $this->post(route('hr.attendance.manual-check-in'), [
            'user_id' => $target->id,
            'attendance_date' => today()->toDateString(),
            'check_in_time' => '09:00',
            'reason' => 'Test unauthorized edit.',
        ])->assertForbidden();
    }

    public function test_manual_and_employee_check_in_are_blocked_on_approved_leave_dates(): void
    {
        $department = $this->department('HR');
        $manager = $this->user($department, 'HR_MANAGER', 'attendance.leave.manager@example.com');
        $employee = $this->user($department, 'HR_EMPLOYEE', 'attendance.leave.employee@example.com');
        $date = '2026-09-28';
        $this->travelTo(Carbon::parse($date . ' 10:00:00', 'Asia/Yangon'));
        CompanySettings::create([
            'company_name' => 'Test Company',
            'timezone' => 'Asia/Yangon',
            'currency' => 'USD',
            'date_format' => 'Y-m-d',
            'working_days' => ['MON', 'TUE', 'WED', 'THU', 'FRI'],
            'work_start' => '09:00',
            'work_end' => '17:00',
        ]);
        $leaveType = $this->leaveType('Annual Leave', 'APPROVED-ATTENDANCE-LEAVE', 10);
        LeaveRequest::create([
            'request_number' => 'LR-APPROVED-ATTENDANCE-LEAVE',
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $date,
            'end_date' => $date,
            'total_days' => 1,
            'reason' => 'Approved leave.',
            'status' => 'APPROVED',
        ]);

        $this->actingAs($manager)->post(route('hr.attendance.manual-check-in'), [
            'user_id' => $employee->id,
            'attendance_date' => $date,
            'check_in_time' => '09:00',
            'reason' => 'Clock issue.',
        ])->assertSessionHas('error');
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $employee->id,
            'attendance_date' => $date,
            'check_in' => '09:00:00',
        ]);

        $this->actingAs($employee)->post(route('employee.attendance.check-in'))->assertSessionHas('error');
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $employee->id,
            'attendance_date' => $date,
            'check_in' => '10:00:00',
        ]);
    }

    public function test_manual_attendance_corrections_validate_times_and_keep_actor_reason_and_audit_details(): void
    {
        $department = $this->department('HR');
        $manager = $this->user($department, 'HR_MANAGER', 'attendance.correction.manager@example.com');
        $employee = $this->user($department, 'HR_EMPLOYEE', 'attendance.correction.employee@example.com');
        CompanySettings::create([
            'company_name' => 'Test Company',
            'timezone' => 'Asia/Yangon',
            'currency' => 'USD',
            'date_format' => 'Y-m-d',
            'working_days' => ['MON', 'TUE', 'WED', 'THU', 'FRI'],
            'work_start' => '09:00',
            'work_end' => '17:00',
        ]);
        $attendance = Attendance::create([
            'user_id' => $employee->id,
            'attendance_date' => '2026-09-28',
            'check_in' => '09:00:00',
            'status' => 'PRESENT',
        ]);

        $this->actingAs($manager)->put(route('hr.attendance.update', $attendance->id), [
            'status' => 'LATE',
            'check_in' => '09:15',
            'check_out' => '17:00',
            'reason' => 'Corrected from the signed attendance sheet.',
        ])->assertRedirect();

        $attendance->refresh();
        $this->assertSame('LATE', $attendance->status);
        $this->assertSame('09:15:00', $attendance->check_in);
        $this->assertSame('17:00:00', $attendance->check_out);
        $this->assertSame(15, $attendance->late_minutes);
        $this->assertSame(7.75, (float) $attendance->working_hours);
        $this->assertSame($manager->id, $attendance->checked_in_by);
        $this->assertSame($manager->id, $attendance->checked_out_by);
        $this->assertStringContainsString('Corrected from the signed attendance sheet.', $attendance->manual_reason);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'table_name' => 'attendances',
            'record_id' => $attendance->id,
            'action' => 'UPDATED',
        ]);
        $this->assertSame(
            'PRESENT',
            AuditLog::where('record_id', $attendance->id)->latest('id')->firstOrFail()->old_values['status'],
        );
        $this->actingAs($manager)->get(route('hr.attendance.history', [
            'search' => $employee->name,
            'from_date' => '2026-09-28',
            'to_date' => '2026-09-28',
        ]))->assertOk()->assertSee('Late')->assertSee('Corrected from the signed attendance sheet.');

        $this->put(route('hr.attendance.update', $attendance->id), [
            'status' => 'PRESENT',
            'check_in' => '10:00',
            'check_out' => '17:00',
            'reason' => 'This status conflicts with the late check-in.',
        ])->assertSessionHasErrors('status');
        $this->assertSame('LATE', $attendance->fresh()->status);
    }

    public function test_leave_submission_creates_reviewer_assignments_and_cancel_keeps_record(): void
    {
        $department = $this->department('SALES');
        $employee = $this->user($department, 'SALES_EMPLOYEE', 'leave.employee@example.com');
        $manager = $this->user($department, 'SALES_MANAGER', 'leave.manager@example.com');
        $leaveType = $this->leaveType('Annual Leave', 'ANNUAL', 10);

        $leaveRequest = $this->submitLeave($employee, $leaveType, today()->addDay()->toDateString(), today()->addDay()->toDateString());

        $this->assertDatabaseHas('leave_approvals', [
            'leave_request_id' => $leaveRequest->id,
            'reviewer_id' => $manager->id,
            'status' => 'PENDING',
        ]);
        $this->actingAs($manager)->get(route('manager.leave.index'))->assertViewHas('pendingCount', 1);

        $this->actingAs($employee)->post(route('employee.leave.cancel', $leaveRequest))->assertRedirect();
        $this->assertSame('CANCELLED', $leaveRequest->fresh()->status);
        $this->assertDatabaseHas('leave_approvals', [
            'leave_request_id' => $leaveRequest->id,
            'reviewer_id' => $manager->id,
            'status' => 'CANCELLED',
        ]);
    }

    public function test_new_department_manager_role_can_open_own_leave_management(): void
    {
        $department = $this->department('OPS');
        $manager = $this->user($department, 'OPS_MANAGER', 'ops.manager@example.com');
        $employee = $this->user($department, 'OPS_EMPLOYEE', 'ops.employee@example.com');

        $this->actingAs($manager)->get(route('manager.leave.index'))->assertOk();
        $this->actingAs($employee)->get(route('manager.leave.index'))->assertForbidden();
    }

    public function test_new_department_manager_can_apply_for_leave_and_receive_admin_reviewer(): void
    {
        $department = $this->department('OPS');
        $manager = $this->user($department, 'OPS_MANAGER', 'ops.leave.manager@example.com');
        $admin = $this->user($department, 'ADMIN', 'ops.leave.admin@example.com');
        $leaveType = $this->leaveType('Annual Leave', 'OPS-ANNUAL', 10);

        $this->actingAs($manager)->get(route('employee.leave.create'))
            ->assertOk()
            ->assertSee('Request Leave');

        $response = $this->post(route('employee.leave.store'), [
            'leave_type_id' => $leaveType->id,
            'start_date' => today()->addDay()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'reason' => 'Family appointment.',
        ]);

        $leaveRequest = LeaveRequest::where('user_id', $manager->id)->firstOrFail();
        $response->assertRedirect(route('employee.leave.index'));
        $this->assertDatabaseHas('leave_approvals', [
            'leave_request_id' => $leaveRequest->id,
            'reviewer_id' => $admin->id,
            'status' => 'PENDING',
        ]);
        $this->assertSame(1.0, (float) LeaveBalance::where('user_id', $manager->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('year', now()->year)
            ->value('pending_days'));
    }

    public function test_manager_cannot_approve_or_reject_their_own_leave_request(): void
    {
        $department = $this->department('OPS');
        $manager = $this->user($department, 'OPS_MANAGER', 'ops.self.leave@example.com');
        $peerManager = $this->user($department, 'OPS_MANAGER', 'ops.peer.manager@example.com');
        $leaveType = $this->leaveType('Annual Leave', 'OPS-SELF-ANNUAL', 10);
        $leaveRequest = $this->submitLeave(
            $manager,
            $leaveType,
            today()->addDay()->toDateString(),
            today()->addDay()->toDateString(),
        );

        $this->assertDatabaseHas('leave_approvals', [
            'leave_request_id' => $leaveRequest->id,
            'reviewer_id' => $peerManager->id,
            'status' => 'PENDING',
        ]);

        $this->actingAs($manager)->get(route('manager.leave.index'))
            ->assertOk()
            ->assertDontSee('approveModal' . $leaveRequest->id)
            ->assertDontSee('rejectModal' . $leaveRequest->id);
        $this->post(route('manager.leave.approve', $leaveRequest), ['comment' => 'Self approval'])
            ->assertForbidden();
        $this->post(route('manager.leave.reject', $leaveRequest), [
            'rejection_reason' => 'Self rejection',
        ])->assertForbidden();

        $this->assertSame('PENDING', $leaveRequest->fresh()->status);
    }

    public function test_same_department_manager_without_pending_assignment_cannot_approve_leave(): void
    {
        $department = $this->department('OPS');
        $employee = $this->user($department, 'OPS_EMPLOYEE', 'ops.assignment.employee@example.com');
        $assignedManager = $this->user($department, 'OPS_MANAGER', 'ops.assigned.manager@example.com');
        $otherManager = $this->user($department, 'OPS_MANAGER', 'ops.unassigned.manager@example.com');
        $leaveType = $this->leaveType('Annual Leave', 'OPS-ASSIGN-ANNUAL', 10);
        $leaveRequest = $this->submitLeave(
            $employee,
            $leaveType,
            today()->addDay()->toDateString(),
            today()->addDay()->toDateString(),
        );
        LeaveApproval::where('leave_request_id', $leaveRequest->id)
            ->where('reviewer_id', $otherManager->id)
            ->delete();

        $this->actingAs($otherManager)->post(route('manager.leave.approve', $leaveRequest), [
            'comment' => 'Not assigned',
        ])->assertForbidden();

        $this->actingAs($assignedManager)->post(route('manager.leave.approve', $leaveRequest), [
            'comment' => 'Assigned reviewer',
        ])->assertRedirect(route('manager.leave.index'));

        $this->assertSame('APPROVED', $leaveRequest->fresh()->status);
    }

    public function test_hr_leave_management_is_company_wide_read_only_while_approvals_stay_department_scoped(): void
    {
        $hrDepartment = $this->department('HR');
        $salesDepartment = $this->department('SALES');
        $hrManager = $this->user($hrDepartment, 'HR_MANAGER', 'leave.scope.hr.manager@example.com');
        $hrEmployee = $this->user($hrDepartment, 'HR_EMPLOYEE', 'leave.scope.hr.employee@example.com');
        $salesManager = $this->user($salesDepartment, 'SALES_MANAGER', 'leave.scope.sales.manager@example.com');
        $salesEmployee = $this->user($salesDepartment, 'SALES_EMPLOYEE', 'leave.scope.sales.employee@example.com');
        $admin = $this->user($this->department('ADMIN'), 'ADMIN', 'leave.scope.admin@example.com');
        $leaveType = $this->leaveType('Annual Leave', 'SCOPE-ANNUAL', 10);

        $hrRequest = $this->submitLeave($hrEmployee, $leaveType, today()->addDay()->toDateString(), today()->addDay()->toDateString());
        $salesRequest = $this->submitLeave($salesEmployee, $leaveType, today()->addDays(2)->toDateString(), today()->addDays(2)->toDateString());

        $this->actingAs($hrManager)->get(route('manager.leave.index'))
            ->assertOk()
            ->assertSee($hrRequest->request_number)
            ->assertDontSee($salesRequest->request_number);
        $this->get(route('hr.leaves.index'))
            ->assertOk()
            ->assertSee($hrRequest->request_number)
            ->assertSee($salesRequest->request_number)
            ->assertDontSee('Approve Leave')
            ->assertDontSee('Reject Leave');
        $this->actingAs($hrEmployee)->get(route('hr.leaves.index'))
            ->assertOk()
            ->assertSee($hrRequest->request_number)
            ->assertSee($salesRequest->request_number)
            ->assertDontSee('Approve Leave')
            ->assertDontSee('Reject Leave');

        $this->actingAs($hrManager);
        $this->get(route('manager.leave.show', $salesRequest))->assertForbidden();
        $this->post(route('manager.leave.approve', $salesRequest), ['comment' => 'Cross-department review'])->assertForbidden();
        $this->assertSame('PENDING', $salesRequest->fresh()->status);

        $this->actingAs($admin)->get(route('manager.leave.index'))
            ->assertOk()
            ->assertSee($hrRequest->request_number)
            ->assertSee($salesRequest->request_number);

        $this->assertSame('PENDING', $hrRequest->fresh()->status);
        $this->assertNotSame($hrManager->id, $salesManager->id);
    }

    public function test_employee_can_submit_same_day_sick_leave_and_it_becomes_on_leave_when_approved(): void
    {
        $department = $this->department('HR');
        $employee = $this->user($department, 'HR_EMPLOYEE', 'sick.leave.employee@example.com');
        $manager = $this->user($department, 'HR_MANAGER', 'sick.leave.manager@example.com');
        $leaveType = $this->leaveType('Sick Leave', 'SICK', 10);
        CompanySettings::create([
            'company_name' => 'Test Company',
            'timezone' => 'Asia/Yangon',
            'currency' => 'USD',
            'date_format' => 'Y-m-d',
            'working_days' => ['MON', 'TUE', 'WED', 'THU', 'FRI'],
            'work_start' => '09:00',
            'work_end' => '17:00',
        ]);
        $this->travelTo(Carbon::parse('2026-09-28 08:00:00'));

        $this->actingAs($employee)->post(route('employee.leave.store'), [
            'leave_type_id' => $leaveType->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
            'reason' => 'I am feeling unwell and cannot come to the office today.',
        ])->assertRedirect(route('employee.leave.index'));

        $leaveRequest = LeaveRequest::where('user_id', $employee->id)->latest()->firstOrFail();
        $this->assertSame('PENDING', $leaveRequest->status);
        $this->assertDatabaseHas('leave_approvals', [
            'leave_request_id' => $leaveRequest->id,
            'reviewer_id' => $manager->id,
            'status' => 'PENDING',
        ]);

        $this->actingAs($manager)->post(route('manager.leave.approve', $leaveRequest), ['comment' => 'Approved sick leave'])->assertRedirect(route('manager.leave.index'));

        $attendance = Attendance::where('user_id', $employee->id)->whereDate('attendance_date', today())->first();
        $this->assertNotNull($attendance);
        $this->assertSame('LEAVE', $attendance->status);
        $this->assertNull($attendance->check_in);
        $this->assertNull($attendance->check_out);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $employee->id,
            'type' => 'LEAVE_APPROVED',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $employee->id,
            'type' => 'LEAVE_APPROVED',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'table_name' => 'attendances',
            'record_id' => $attendance->id,
            'action' => 'CREATED',
        ]);
    }

    public function test_employee_can_report_late_arrival_and_check_in_uses_company_work_start_for_late_minutes(): void
    {
        $department = $this->department('SALES');
        $employee = $this->user($department, 'SALES_EMPLOYEE', 'late.arrival.employee@example.com');
        $manager = $this->user($department, 'SALES_MANAGER', 'late.arrival.manager@example.com');
        CompanySettings::create([
            'company_name' => 'Test Company',
            'timezone' => 'Asia/Yangon',
            'currency' => 'USD',
            'date_format' => 'Y-m-d',
            'working_days' => ['MON', 'TUE', 'WED', 'THU', 'FRI'],
            'work_start' => '08:30',
            'work_end' => '17:30',
        ]);

        $this->travelTo(Carbon::parse('2026-09-28 08:45:00'));
        $this->actingAs($employee)->post(route('employee.attendance.report-late'), [
            'reason' => 'Vehicle breakdown on the way to work.',
            'expected_check_in' => '09:00',
        ])->assertRedirect(route('employee.attendance.index'));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $manager->id,
            'type' => 'ATTENDANCE_LATE_REPORT',
        ]);

        $this->travelTo(Carbon::parse('2026-09-28 09:40:00'));
        $this->actingAs($employee)->post(route('employee.attendance.check-in'))->assertRedirect();

        $attendance = Attendance::where('user_id', $employee->id)->whereDate('attendance_date', today())->firstOrFail();
        $this->assertSame('LATE', $attendance->status);
        $this->assertSame(70, $attendance->late_minutes);
        $this->assertDatabaseHas('audit_logs', [
            'table_name' => 'attendances',
            'record_id' => $attendance->id,
            'action' => 'CREATED',
        ]);
    }

    public function test_employee_can_switch_from_late_report_to_emergency_leave_and_approve_it_without_duplicate_attendance(): void
    {
        $department = $this->department('SALES');
        $employee = $this->user($department, 'SALES_EMPLOYEE', 'late.to.leave.employee@example.com');
        $manager = $this->user($department, 'SALES_MANAGER', 'late.to.leave.manager@example.com');
        $leaveType = $this->leaveType('Emergency Leave', 'EMERGENCY', 10);
        CompanySettings::create([
            'company_name' => 'Test Company',
            'timezone' => 'Asia/Yangon',
            'currency' => 'USD',
            'date_format' => 'Y-m-d',
            'working_days' => ['MON', 'TUE', 'WED', 'THU', 'FRI'],
            'work_start' => '09:00',
            'work_end' => '17:00',
        ]);

        $this->travelTo(Carbon::parse('2026-09-28 08:35:00'));
        $this->actingAs($employee)->post(route('employee.attendance.report-late'), [
            'reason' => 'Vehicle issue, still evaluating timing.',
            'expected_check_in' => '09:00',
        ])->assertRedirect(route('employee.attendance.index'));

        $this->actingAs($employee)->post(route('employee.leave.store'), [
            'leave_type_id' => $leaveType->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
            'reason' => 'Vehicle breakdown became serious and I cannot come in today.',
        ])->assertRedirect(route('employee.leave.index'));

        $this->assertSame(1, LeaveRequest::where('user_id', $employee->id)->count());
        $leaveRequest = LeaveRequest::where('user_id', $employee->id)->latest()->firstOrFail();

        $this->actingAs($manager)->post(route('manager.leave.approve', $leaveRequest), ['comment' => 'Approved emergency leave'])->assertRedirect(route('manager.leave.index'));

        $this->assertSame(1, Attendance::where('user_id', $employee->id)->whereDate('attendance_date', today())->count());
        $attendance = Attendance::where('user_id', $employee->id)->whereDate('attendance_date', today())->firstOrFail();
        $this->assertSame('LEAVE', $attendance->status);
        $this->assertNull($attendance->check_in);
        $this->assertNull($attendance->check_out);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $employee->id,
            'type' => 'LEAVE_APPROVED',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'table_name' => 'attendances',
            'record_id' => $attendance->id,
            'action' => 'UPDATED',
        ]);
    }

    public function test_employee_check_in_and_later_early_departure_keeps_original_check_in_and_checkout_values(): void
    {
        $department = $this->department('SALES');
        $employee = $this->user($department, 'SALES_EMPLOYEE', 'early.departure.employee@example.com');
        $manager = $this->user($department, 'SALES_MANAGER', 'early.departure.manager@example.com');
        CompanySettings::create([
            'company_name' => 'Test Company',
            'timezone' => 'Asia/Yangon',
            'currency' => 'USD',
            'date_format' => 'Y-m-d',
            'working_days' => ['MON', 'TUE', 'WED', 'THU', 'FRI'],
            'work_start' => '09:00',
            'work_end' => '17:00',
        ]);

        $this->travelTo(Carbon::parse('2026-09-28 09:02:00'));
        $this->actingAs($employee)->post(route('employee.attendance.check-in'))->assertRedirect();

        $attendance = Attendance::where('user_id', $employee->id)->whereDate('attendance_date', today())->firstOrFail();
        $this->assertSame('LATE', $attendance->status);

        $this->travelTo(Carbon::parse('2026-09-28 10:30:00'));
        $this->actingAs($employee)->get(route('employee.attendance.index'))->assertOk();

        $this->travelTo(Carbon::parse('2026-09-28 11:15:00'));
        $this->actingAs($employee)->post(route('employee.attendance.check-out'), [
            'departure_type' => 'HALF_DAY',
            'reason' => 'Feeling sick and unable to finish the workday.',
        ])->assertRedirect();

        $attendance = $attendance->fresh();
        $this->assertSame('09:02:00', $attendance->check_in);
        $this->assertSame('11:15:00', $attendance->check_out);
        $this->assertGreaterThan(0, (float) $attendance->working_hours);
        $this->assertSame('HALF_DAY', $attendance->status);
        $this->assertStringContainsString('Feeling sick', $attendance->notes);
        $this->actingAs($employee)->get(route('employee.attendance.index'))
            ->assertOk()
            ->assertSee('Half-Day');
        $this->assertDatabaseHas('notifications', [
            'user_id' => $manager->id,
            'type' => 'ATTENDANCE_EARLY_DEPARTURE',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'table_name' => 'attendances',
            'record_id' => $attendance->id,
            'action' => 'UPDATED',
        ]);
    }

    public function test_admin_cannot_apply_for_leave_through_employee_workflow(): void
    {
        $department = $this->department('ADMIN');
        $admin = $this->user($department, 'ADMIN', 'leave.admin.request@example.com');
        $leaveType = $this->leaveType('Annual Leave', 'ADMIN-ANNUAL', 10);

        $this->actingAs($admin)->post(route('employee.leave.store'), [
            'leave_type_id' => $leaveType->id,
            'start_date' => today()->addDay()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'reason' => 'Personal leave.',
        ])->assertNotFound();

        $this->assertDatabaseMissing('leave_requests', ['user_id' => $admin->id]);
    }

    public function test_employee_leave_request_uses_admin_fallback_when_department_has_no_manager(): void
    {
        $department = $this->department('OPS');
        $employee = $this->user($department, 'OPS_EMPLOYEE', 'leave.ops.employee@example.com');
        $admin = $this->user($department, 'ADMIN', 'leave.ops.admin@example.com');
        $leaveType = $this->leaveType('Annual Leave', 'OPS-ANNUAL', 10);

        $this->actingAs($employee)->post(route('employee.leave.store'), [
            'leave_type_id' => $leaveType->id,
            'start_date' => today()->addDay()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'reason' => 'Personal leave.',
        ])->assertRedirect(route('employee.leave.index'));

        $leaveRequest = LeaveRequest::where('user_id', $employee->id)->firstOrFail();
        $this->assertDatabaseHas('leave_approvals', [
            'leave_request_id' => $leaveRequest->id,
            'reviewer_id' => $admin->id,
            'status' => 'PENDING',
        ]);
    }

    public function test_leave_days_are_reserved_against_each_request_year(): void
    {
        $department = $this->department('SALES');
        $employee = $this->user($department, 'SALES_EMPLOYEE', 'leave.year@example.com');
        $manager = $this->user($department, 'SALES_MANAGER', 'leave.year.manager@example.com');
        $leaveType = $this->leaveType('Annual Leave', 'ANNUAL', 5);
        LeaveBalance::where('user_id', $employee->id)->where('leave_type_id', $leaveType->id)->where('year', now()->year)->update(['total_days' => 0]);
        LeaveBalance::updateOrCreate(
            ['user_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'year' => now()->year + 1],
            ['total_days' => 5, 'used_days' => 0, 'pending_days' => 0, 'carried_forward' => 0],
        );

        $request = $this->submitLeave($employee, $leaveType, (now()->year + 1) . '-01-02', (now()->year + 1) . '-01-03');

        $nextYearBalance = LeaveBalance::where('user_id', $employee->id)->where('leave_type_id', $leaveType->id)->where('year', now()->year + 1)->firstOrFail();
        $this->assertSame(2.0, (float) $request->total_days);
        $this->assertSame(2.0, (float) $nextYearBalance->pending_days);

        $this->actingAs($manager)->post(route('manager.leave.approve', $request), ['comment' => 'Approved'])->assertRedirect();
        $this->assertSame(2.0, (float) $nextYearBalance->fresh()->used_days);
        $this->assertSame(0.0, (float) $nextYearBalance->fresh()->pending_days);
    }

    private function department(string $code): Department
    {
        return Department::create(['name' => $code, 'code' => $code, 'status' => 'ACTIVE']);
    }

    private function user(Department $department, string $role, string $email): User
    {
        return User::create([
            'employee_id' => strtoupper(substr(md5($email), 0, 10)),
            'name' => ucfirst(strstr($email, '@', true)),
            'email' => $email,
            'password' => bcrypt('password'),
            'department_id' => $department->id,
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function leaveType(string $name, string $code, int $days): LeaveType
    {
        return LeaveType::create([
            'name' => $name,
            'code' => $code,
            'days_allowed' => $days,
            'status' => 'ACTIVE',
        ]);
    }

    private function submitLeave(User $employee, LeaveType $leaveType, string $start, string $end): LeaveRequest
    {
        $this->actingAs($employee)->post(route('employee.leave.store'), [
            'leave_type_id' => $leaveType->id,
            'start_date' => $start,
            'end_date' => $end,
            'reason' => 'Test leave request',
        ])->assertRedirect(route('employee.leave.index'));

        return LeaveRequest::where('user_id', $employee->id)->latest()->firstOrFail();
    }

}
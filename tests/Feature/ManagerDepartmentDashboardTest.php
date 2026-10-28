<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Document;
use App\Models\FollowUp;
use App\Models\LeaveApproval;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManagerDepartmentDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_manager_dashboard_metrics_are_limited_to_their_department(): void
    {
        $hr = $this->department('HR');
        $sales = $this->department('SALES');
        $manager = $this->user($hr, 'HR_MANAGER', 'hr.manager.dashboard@example.com');
        $employee = $this->user($hr, 'HR_EMPLOYEE', 'hr.employee.dashboard@example.com');
        $otherEmployee = $this->user($sales, 'SALES_EMPLOYEE', 'sales.employee.dashboard@example.com');

        Attendance::create([
            'user_id' => $employee->id,
            'attendance_date' => today(),
            'check_in' => '09:00',
            'status' => 'PRESENT',
        ]);
        Attendance::create([
            'user_id' => $otherEmployee->id,
            'attendance_date' => today(),
            'check_in' => '10:00',
            'status' => 'LATE',
        ]);

        $leaveType = LeaveType::create(['name' => 'Annual Leave', 'code' => 'HR-ANNUAL', 'days_allowed' => 15, 'status' => 'ACTIVE']);
        $leaveRequest = LeaveRequest::create([
            'request_number' => 'LR-HR-DASH-001',
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => today()->addDay(),
            'end_date' => today()->addDay(),
            'total_days' => 1,
            'reason' => 'Personal appointment',
            'status' => 'PENDING',
        ]);
        LeaveApproval::create([
            'leave_request_id' => $leaveRequest->id,
            'reviewer_id' => $manager->id,
            'status' => 'PENDING',
        ]);
        LeaveBalance::updateOrCreate(
            ['user_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'year' => now()->year],
            ['total_days' => 15, 'used_days' => 3, 'pending_days' => 2, 'carried_forward' => 0],
        );
        Document::create([
            'title' => 'HR Handbook',
            'document_type' => 'POLICY',
            'department_id' => $hr->id,
            'uploaded_by' => $manager->id,
            'access_level' => 'DEPARTMENT',
            'status' => 'PUBLISHED',
        ]);
        DB::table('reminders')->insert([
            'user_id' => $employee->id,
            'employee_id' => $employee->id,
            'reminder_type' => 'PROBATION_REVIEW',
            'title' => 'Review HR policy',
            'reminder_date' => today()->addDay(),
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('data', function (array $data): bool {
                $cards = collect($data['managerDashboard']['cards'])->keyBy('label');
                $this->assertSame(1, $cards['Employee Count']['value']);
                $this->assertSame(1, $cards['Attendance Today']['value']);
                $this->assertSame(0, $cards['Late Employees']['value']);
                $this->assertSame(1, $cards['Pending Leave Approvals']['value']);
                $this->assertSame('10.0 days', $cards['Employee Leave Balance']['value']);
                $this->assertSame(1, $cards['Employee Documents']['value']);
                $this->assertFalse($cards->has('HR Reminders'));

                return true;
            });
    }

    public function test_it_manager_dashboard_counts_department_tasks_and_documents(): void
    {
        $it = $this->department('IT');
        $sales = $this->department('SALES');
        $manager = $this->user($it, 'IT_MANAGER', 'it.manager.dashboard@example.com');
        $employee = $this->user($it, 'IT_EMPLOYEE', 'it.employee.dashboard@example.com');
        $otherEmployee = $this->user($sales, 'SALES_EMPLOYEE', 'sales.employee.it-dashboard@example.com');

        FollowUp::create([
            'customer_id' => null,
            'title' => 'Resolve network issue',
            'follow_up_date' => today()->subDay(),
            'type' => 'OTHER',
            'priority' => 'HIGH',
            'status' => 'OVERDUE',
            'assigned_to' => $employee->id,
            'created_by' => $manager->id,
            'recurrence_rule' => 'NONE',
        ]);
        FollowUp::create([
            'title' => 'Sales team task outside IT',
            'follow_up_date' => today()->addDays(2),
            'type' => 'OTHER',
            'priority' => 'NORMAL',
            'status' => 'PLANNED',
            'assigned_to' => $otherEmployee->id,
            'created_by' => $otherEmployee->id,
            'recurrence_rule' => 'NONE',
        ]);
        Reminder::create([
            'user_id' => $employee->id,
            'title' => 'Check server backup',
            'reminder_date' => today()->addDay(),
            'status' => 'PENDING',
        ]);
        Document::create([
            'title' => 'IT Operations Guide',
            'document_type' => 'POLICY',
            'department_id' => $it->id,
            'uploaded_by' => $manager->id,
            'access_level' => 'DEPARTMENT',
            'status' => 'PUBLISHED',
        ]);

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('data', function (array $data): bool {
                $cards = collect($data['managerDashboard']['cards'])->keyBy('label');
                $this->assertSame(1, $cards['IT Employee Count']['value']);
                $this->assertSame(2, $cards['Assigned Tasks']['value']);
                $this->assertSame(1, $cards['Upcoming Tasks']['value']);
                $this->assertSame(1, $cards['Overdue Tasks']['value']);
                $this->assertSame(1, $cards['Documents']['value']);
                $this->assertSame(1, $cards['IT Reminders']['value']);
                $this->assertSame(2, $cards['IT Activities']['value']);

                return true;
            });
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
}

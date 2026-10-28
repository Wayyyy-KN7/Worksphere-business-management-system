<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\CompanySettings;
use App\Models\Customer;
use App\Models\CustomerSource;
use App\Models\Department;
use App\Models\Document;
use App\Models\FollowUp;
use App\Models\Holiday;
use App\Models\LeaveApproval;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Notification;
use App\Models\Opportunity;
use App\Models\Reminder;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class CompanyPresentationSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'Demo-Only-2026!';

    private const ROSTERS = [
        'HR' => [
            ['employee_id' => 'DEMO-HR-002', 'name' => 'Ethan Brooks', 'email' => 'ethan.brooks@hr-demo.test'],
            ['employee_id' => 'DEMO-HR-003', 'name' => 'Grace Morgan', 'email' => 'grace.morgan@hr-demo.test'],
            ['employee_id' => 'DEMO-HR-004', 'name' => 'Lucas Bennett', 'email' => 'lucas.bennett@hr-demo.test'],
            ['employee_id' => 'DEMO-HR-005', 'name' => 'Zoe Carter', 'email' => 'zoe.carter@hr-demo.test'],
        ],
        'SALES' => [
            ['employee_id' => 'DEMO-SALES-003', 'name' => 'Mason Patel', 'email' => 'mason.patel@sales-demo.test'],
            ['employee_id' => 'DEMO-SALES-004', 'name' => 'Chloe Adams', 'email' => 'chloe.adams@sales-demo.test'],
            ['employee_id' => 'DEMO-SALES-005', 'name' => 'Ethan Brooks', 'email' => 'ethan.brooks@sales-demo.test'],
        ],
        'IT' => [
            ['employee_id' => 'DEMO-IT-003', 'name' => 'Sofia Nguyen', 'email' => 'sofia.nguyen@it-demo.test'],
            ['employee_id' => 'DEMO-IT-004', 'name' => 'Caleb Foster', 'email' => 'caleb.foster@it-demo.test'],
            ['employee_id' => 'DEMO-IT-005', 'name' => 'Maya Turner', 'email' => 'maya.turner@it-demo.test'],
            ['employee_id' => 'DEMO-IT-006', 'name' => 'Oliver Hayes', 'email' => 'oliver.hayes@it-demo.test'],
        ],
        'MKT' => [
            ['employee_id' => 'DEMO-MKT-002', 'name' => 'Lily Cooper', 'email' => 'lily.cooper@marketing-demo.test'],
            ['employee_id' => 'DEMO-MKT-003', 'name' => 'Jack Wilson', 'email' => 'jack.wilson@marketing-demo.test'],
            ['employee_id' => 'DEMO-MKT-004', 'name' => 'Amelia Scott', 'email' => 'amelia.scott@marketing-demo.test'],
            ['employee_id' => 'DEMO-MKT-005', 'name' => 'Henry Ward', 'email' => 'henry.ward@marketing-demo.test'],
        ],
    ];

    private const DEPARTMENT_ROLES = [
        'HR' => ['manager' => 'HR_MANAGER', 'employee' => 'HR_EMPLOYEE'],
        'SALES' => ['manager' => 'SALES_MANAGER', 'employee' => 'SALES_EMPLOYEE'],
        'IT' => ['manager' => 'IT_MANAGER', 'employee' => 'IT_EMPLOYEE'],
        'MKT' => ['manager' => 'MARKETING_MANAGER', 'employee' => 'MARKETING_EMPLOYEE'],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Demo data seeding is disabled in production.');
        }

        $departments = Department::query()->whereIn('code', array_keys(self::DEPARTMENT_ROLES))->get()->keyBy('code');
        $managers = [];
        foreach (self::DEPARTMENT_ROLES as $code => $roles) {
            $department = $departments->get($code);
            if (!$department) {
                throw new RuntimeException("Required existing department {$code} is missing.");
            }

            $manager = User::query()->where('department_id', $department->id)
                ->where('role', $roles['manager'])->where('status', 'ACTIVE')->first();
            if (!$manager) {
                throw new RuntimeException("An active {$roles['manager']} is required in department {$code}.");
            }
            $managers[$code] = $manager;
        }

        $rosterUsers = $this->ensureDepartmentEmployees($departments);
        $this->seedLeaveBalances($rosterUsers);
        $this->seedAttendance($rosterUsers);
        $this->seedSalesActivities($departments, $managers, $rosterUsers);
        $this->seedMarketingLeads($departments->get('MKT'), $managers['MKT'], $rosterUsers['MKT']);
        $this->seedItActivities($departments->get('IT'), $managers['IT'], $rosterUsers['IT']);
        $this->seedDepartmentDocuments($departments, $managers);
        $this->seedHrLeaveExamples($managers['HR'], $rosterUsers['HR']);
        $this->seedNotifications($managers, $rosterUsers);
        $this->writeLocalCredentials($departments, $managers);
    }

    private function ensureDepartmentEmployees($departments): array
    {
        $employeesByDepartment = [];

        foreach (self::ROSTERS as $code => $roster) {
            $department = $departments->get($code);
            $role = self::DEPARTMENT_ROLES[$code]['employee'];
            $activeCount = User::query()->where('department_id', $department->id)
                ->where('role', $role)->where('status', 'ACTIVE')->count();

            foreach ($roster as $index => $identity) {
                $existing = User::query()->where('email', $identity['email'])->first();
                if ($existing) {
                    if ($existing->employee_id !== $identity['employee_id']
                        || $existing->role !== $role
                        || (int) $existing->department_id !== (int) $department->id
                        || $existing->status !== 'ACTIVE') {
                        throw new RuntimeException("Demo account {$identity['email']} already exists with conflicting identity data.");
                    }
                    continue;
                }

                $employeeIdOwner = User::query()->where('employee_id', $identity['employee_id'])->first();
                if ($employeeIdOwner) {
                    throw new RuntimeException("Demo employee ID {$identity['employee_id']} is already assigned to another account.");
                }

                if ($activeCount >= 6) {
                    continue;
                }

                User::query()->create([
                    'employee_id' => $identity['employee_id'],
                    'name' => $identity['name'],
                    'email' => $identity['email'],
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'department_id' => $department->id,
                    'role' => $role,
                    'position' => $this->positionFor($role),
                    'joining_date' => today()->subMonths(6 + $index)->toDateString(),
                    'status' => 'ACTIVE',
                ]);
                $activeCount++;
            }

            $employeesByDepartment[$code] = User::query()
                ->where('department_id', $department->id)
                ->where('role', $role)
                ->where('status', 'ACTIVE')
                ->orderBy('id')
                ->get();

            if ($employeesByDepartment[$code]->count() < 6) {
                throw new RuntimeException("Unable to ensure six active employees in department {$code}.");
            }
        }

        return $employeesByDepartment;
    }

    private function seedLeaveBalances(array $employeesByDepartment): void
    {
        $leaveTypes = LeaveType::query()->where('status', 'ACTIVE')->get();
        foreach (collect($employeesByDepartment)->flatten(1) as $employee) {
            foreach ($leaveTypes as $leaveType) {
                LeaveBalance::query()->firstOrCreate(
                    ['user_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'year' => now()->year],
                    ['total_days' => $leaveType->days_allowed, 'used_days' => 0, 'pending_days' => 0, 'carried_forward' => 0],
                );
            }
        }
    }

    private function seedAttendance(array $employeesByDepartment): void
    {
        $settings = CompanySettings::current();
        $timezone = $settings->timezone;
        $date = Carbon::today($timezone);
        while (!$this->isWorkingDate($date, $settings->working_days, $timezone)) {
            $date->subDay();
        }

        foreach (collect($employeesByDepartment)->flatten(1)->values() as $index => $employee) {
            $lateMinutes = $index % 5 === 0 ? 15 : 0;
            $checkIn = Carbon::parse($date->toDateString() . ' ' . $settings->work_start, $timezone)->addMinutes($lateMinutes);
            $checkOut = Carbon::parse($date->toDateString() . ' ' . $settings->work_end, $timezone);
            $attendanceExists = Attendance::query()
                ->where('user_id', $employee->id)
                ->whereDate('attendance_date', $date->toDateString())
                ->exists();
            if (!$attendanceExists) {
                Attendance::query()->create([
                    'check_in' => $checkIn->format('H:i:s'),
                    'check_out' => $checkOut->format('H:i:s'),
                    'working_hours' => round($checkIn->diffInMinutes($checkOut) / 60, 2),
                    'user_id' => $employee->id,
                    'attendance_date' => $date->toDateString(),
                    'status' => $lateMinutes > 0 ? 'LATE' : 'PRESENT',
                    'late_minutes' => $lateMinutes,
                    'is_manual' => false,
                    'notes' => 'Presentation demo attendance record.',
                ]);
            }
        }
    }

    private function seedSalesActivities($departments, array $managers, array $employeesByDepartment): void
    {
        $department = $departments->get('SALES');
        $salesEmployees = $employeesByDepartment['SALES']->values();
        $sources = [
            'Website' => CustomerSource::query()->firstOrCreate(['name' => 'Website']),
            'Referral' => CustomerSource::query()->firstOrCreate(['name' => 'Referral']),
            'Social Media' => CustomerSource::query()->firstOrCreate(['name' => 'Social Media']),
            'Existing Customer' => CustomerSource::query()->firstOrCreate(['name' => 'Existing Customer']),
        ];

        $accounts = [
            ['code' => 'DEMO-PRES-SAL-001', 'name' => 'Northstar Technology Group', 'company' => 'Northstar Technology Group Ltd', 'source' => 'Website'],
            ['code' => 'DEMO-PRES-SAL-002', 'name' => 'Harbor Retail Group', 'company' => 'Harbor Retail Group Ltd', 'source' => 'Referral'],
            ['code' => 'DEMO-PRES-SAL-003', 'name' => 'Summit Health Services', 'company' => 'Summit Health Services Ltd', 'source' => 'Social Media'],
            ['code' => 'DEMO-PRES-SAL-004', 'name' => 'Greenfield Logistics', 'company' => 'Greenfield Logistics Co', 'source' => 'Existing Customer'],
        ];

        foreach ($accounts as $index => $account) {
            $employee = $salesEmployees[$index % $salesEmployees->count()];
            $customer = Customer::query()->firstOrCreate(
                ['customer_code' => $account['code']],
                [
                    'name' => $account['name'],
                    'company_name' => $account['company'],
                    'email' => strtolower(str_replace(' ', '.', $account['code'])) . '@customer-demo.test',
                    'phone' => '+95-9-4100-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'city' => $index % 2 === 0 ? 'Yangon' : 'Mandalay',
                    'country' => 'Myanmar',
                    'customer_type' => 'NEW',
                    'source_id' => $sources[$account['source']]->id,
                    'created_by' => $managers['SALES']->id,
                    'created_department_id' => $department->id,
                    'assigned_department_id' => $department->id,
                    'assigned_to' => $employee->id,
                    'status' => 'ACTIVE',
                    'notes' => 'Presentation demo account.',
                ],
            );
            $this->assertCustomerOwnership($customer, $employee, $department);

            $isWon = $index < 2;
            $expectedCloseDate = $isWon ? today()->subDays(8 + $index)->toDateString() : today()->addDays(10 + $index)->toDateString();
            $opportunity = Opportunity::query()->firstOrCreate(
                ['opportunity_number' => 'DEMO-PRES-OPP-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'customer_id' => $customer->id,
                    'title' => ['CRM Platform Upgrade', 'Retail Analytics Rollout', 'Managed Support Renewal', 'Cloud Migration Assessment'][$index],
                    'description' => 'Presentation demo opportunity with a realistic sales stage.',
                    'estimated_value' => [32000, 18500, 12500, 46000][$index],
                    'probability' => $isWon ? 100 : [55, 70][$index - 2],
                    'expected_close_date' => $expectedCloseDate,
                    'actual_close_date' => $isWon ? $expectedCloseDate : null,
                    'stage' => $isWon ? 'WON' : ['PROPOSAL', 'NEGOTIATION'][$index - 2],
                    'status' => $isWon ? 'CLOSED' : 'OPEN',
                    'assigned_to' => $employee->id,
                ],
            );
            if ((int) $opportunity->customer_id !== (int) $customer->id
                || (int) $opportunity->assigned_to !== (int) $employee->id) {
                throw new RuntimeException("Presentation opportunity {$opportunity->opportunity_number} has conflicting relationships.");
            }

            $followUp = FollowUp::query()->firstOrCreate(
                ['title' => 'DEMO Sales - ' . ['Proposal review call', 'Retail rollout discussion', 'Support renewal check-in', 'Migration requirements review'][$index]],
                [
                    'customer_id' => $customer->id,
                    'opportunity_id' => $opportunity->id,
                    'description' => 'Confirm customer needs, next steps, and decision timeline.',
                    'follow_up_date' => $isWon
                        ? today()->subDays(2 + $index)->toDateString()
                        : $this->nextWorkingDate($index + 1)->toDateString(),
                    'follow_up_time' => ['10:00:00', '11:30:00', '14:00:00', '15:30:00'][$index],
                    'type' => $index % 2 === 0 ? 'CALL' : 'MEETING',
                    'status' => $isWon ? 'COMPLETED' : 'PLANNED',
                    'assigned_to' => $employee->id,
                    'created_by' => $managers['SALES']->id,
                    'completed_at' => $isWon ? now()->subDays(2 + $index) : null,
                    'notes' => $isWon ? 'Customer confirmed the purchase.' : null,
                    'priority' => $index === 3 ? 'HIGH' : 'NORMAL',
                    'recurrence_rule' => 'NONE',
                ],
            );

            if (!$isWon) {
                Reminder::query()->firstOrCreate(
                    ['follow_up_id' => $followUp->id],
                    [
                        'user_id' => $employee->id,
                        'customer_id' => $customer->id,
                        'opportunity_id' => $opportunity->id,
                        'title' => $followUp->title,
                        'description' => $followUp->description,
                        'reminder_date' => $followUp->follow_up_date,
                        'reminder_time' => $followUp->follow_up_time,
                        'status' => 'PENDING',
                        'is_notified' => false,
                        'remindable_type' => FollowUp::class,
                        'remindable_id' => $followUp->id,
                    ],
                );
            }

            if ($isWon) {
                $sale = Sale::query()->firstOrCreate(
                    ['sale_number' => 'DEMO-PRES-SALE-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                    [
                        'customer_id' => $customer->id,
                        'opportunity_id' => $opportunity->id,
                        'sale_date' => $expectedCloseDate,
                        'total_amount' => $opportunity->estimated_value,
                        'discount_amount' => 0,
                        'tax_amount' => 0,
                        'final_amount' => $opportunity->estimated_value,
                        'payment_status' => $index === 0 ? 'PAID' : 'PARTIAL',
                        'payment_method' => $index === 0 ? 'BANK_TRANSFER' : 'CASH',
                        'assigned_to' => $employee->id,
                        'notes' => 'Presentation demo sale.',
                    ],
                );
                SaleItem::query()->firstOrCreate(
                    ['sale_id' => $sale->id, 'product_name' => ['CRM Platform License', 'Retail Analytics Setup'][$index]],
                    [
                        'description' => 'Implementation and initial subscription.',
                        'quantity' => 1,
                        'unit_price' => $sale->total_amount,
                        'discount' => 0,
                        'tax' => 0,
                        'total' => $sale->total_amount,
                    ],
                );
            }
        }
    }

    private function seedMarketingLeads(Department $department, User $manager, $marketingEmployees): void
    {
        $sources = [
            'LinkedIn Campaign' => CustomerSource::query()->firstOrCreate(['name' => 'LinkedIn Campaign']),
            'Product Webinar' => CustomerSource::query()->firstOrCreate(['name' => 'Product Webinar']),
            'Technology Expo' => CustomerSource::query()->firstOrCreate(['name' => 'Technology Expo']),
        ];
        $leads = [
            ['name' => 'Bluebird Learning', 'company' => 'Bluebird Learning Co', 'source' => 'LinkedIn Campaign'],
            ['name' => 'Pioneer Manufacturing', 'company' => 'Pioneer Manufacturing Ltd', 'source' => 'Product Webinar'],
            ['name' => 'Cedar Financial', 'company' => 'Cedar Financial Services', 'source' => 'Technology Expo'],
        ];

        foreach ($leads as $index => $lead) {
            $employee = $marketingEmployees->values()[$index % $marketingEmployees->count()];
            $customer = Customer::query()->firstOrCreate(
                ['customer_code' => 'DEMO-PRES-MKT-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'name' => $lead['name'],
                    'company_name' => $lead['company'],
                    'email' => 'lead' . ($index + 1) . '@marketing-demo.test',
                    'phone' => '+95-9-4200-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'city' => 'Yangon',
                    'country' => 'Myanmar',
                    'customer_type' => 'LEAD',
                    'source_id' => $sources[$lead['source']]->id,
                    'created_by' => $employee->id,
                    'created_department_id' => $department->id,
                    'assigned_department_id' => $department->id,
                    'assigned_to' => $employee->id,
                    'status' => 'LEAD',
                    'notes' => 'Presentation demo lead from ' . $lead['source'] . '.',
                ],
            );
            $this->assertCustomerOwnership($customer, $employee, $department);
            Opportunity::query()->firstOrCreate(
                ['opportunity_number' => 'DEMO-PRES-MKT-OPP-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'customer_id' => $customer->id,
                    'title' => $lead['company'] . ' introduction',
                    'description' => 'Marketing-qualified lead for Sales review.',
                    'estimated_value' => 9000 + ($index * 3500),
                    'probability' => 20,
                    'expected_close_date' => $this->nextWorkingDate(15 + $index)->toDateString(),
                    'stage' => 'NEW',
                    'status' => 'OPEN',
                    'assigned_to' => $employee->id,
                ],
            );
            FollowUp::query()->firstOrCreate(
                ['title' => 'DEMO Marketing - ' . $lead['name'] . ' campaign response'],
                [
                    'customer_id' => $customer->id,
                    'description' => 'Review campaign response and coordinate a qualified handoff.',
                    'follow_up_date' => $this->nextWorkingDate(2 + $index)->toDateString(),
                    'follow_up_time' => '13:00:00',
                    'type' => 'EMAIL',
                    'status' => 'PLANNED',
                    'assigned_to' => $employee->id,
                    'created_by' => $manager->id,
                    'priority' => 'NORMAL',
                    'recurrence_rule' => 'NONE',
                ],
            );
        }
    }

    private function seedItActivities(Department $department, User $manager, $itEmployees): void
    {
        $tasks = [
            ['title' => 'DEMO IT - Review endpoint security updates', 'priority' => 'HIGH'],
            ['title' => 'DEMO IT - Prepare workstation onboarding checklist', 'priority' => 'NORMAL'],
            ['title' => 'DEMO IT - Test scheduled backup recovery', 'priority' => 'NORMAL'],
        ];
        foreach ($tasks as $index => $task) {
            $employee = $itEmployees->values()[$index % $itEmployees->count()];
            FollowUp::query()->firstOrCreate(
                ['title' => $task['title']],
                [
                    'description' => 'Department task scheduled for the presentation demo.',
                    'follow_up_date' => $this->nextWorkingDate($index + 1)->toDateString(),
                    'follow_up_time' => '09:30:00',
                    'type' => 'TASK',
                    'status' => $index === 2 ? 'IN_PROGRESS' : 'PLANNED',
                    'assigned_to' => $employee->id,
                    'created_by' => $manager->id,
                    'priority' => $task['priority'],
                    'recurrence_rule' => 'NONE',
                ],
            );
        }
    }

    private function seedDepartmentDocuments($departments, array $managers): void
    {
        $titles = [
            'HR' => ['Demo HR - Employee Handbook', 'EMPLOYEE HANDBOOK'],
            'SALES' => ['Demo Sales - Account Planning Playbook', 'SALES PLAYBOOK'],
            'IT' => ['Demo IT - Security and Backup Runbook', 'TECHNICAL RUNBOOK'],
            'MKT' => ['Demo Marketing - Campaign Calendar', 'CAMPAIGN PLAN'],
        ];

        foreach ($titles as $code => [$title, $documentType]) {
            $department = $departments->get($code);
            Document::query()->firstOrCreate(
                ['title' => $title],
                [
                    'description' => "Presentation demo reference for the {$department->name} department.",
                    'document_type' => $documentType,
                    'department_id' => $department->id,
                    'uploaded_by' => $managers[$code]->id,
                    'access_level' => 'DEPARTMENT',
                    'status' => 'PUBLISHED',
                ],
            );
        }
    }

    private function seedHrLeaveExamples(User $hrManager, $hrEmployees): void
    {
        $leaveType = LeaveType::query()->where('code', 'AL')->where('status', 'ACTIVE')->first();
        if (!$leaveType || $hrEmployees->count() < 2) {
            throw new RuntimeException('An active Annual Leave type and two HR employees are required for leave demos.');
        }

        $examples = [
            ['suffix' => 'PENDING', 'email' => 'masu@company.com', 'status' => 'PENDING'],
            ['suffix' => 'APPROVED', 'email' => 'nora.rivera@hr-demo.test', 'status' => 'APPROVED'],
        ];
        foreach ($examples as $index => $example) {
            $employee = $hrEmployees->firstWhere('email', $example['email']);
            if (!$employee) {
                throw new RuntimeException("Required demo HR employee {$example['email']} is missing.");
            }
            $date = $this->nextWorkingDate(5 + ($index * 3))->toDateString();
            $requestNumber = 'LR-DEMO-PRES-HR-' . $example['suffix'] . '-001';
            $request = LeaveRequest::query()->firstOrCreate(
                ['request_number' => $requestNumber],
                [
                    'user_id' => $employee->id,
                    'leave_type_id' => $leaveType->id,
                    'start_date' => $date,
                    'end_date' => $date,
                    'total_days' => 1,
                    'reason' => 'Presentation demo annual leave request.',
                    'status' => $example['status'],
                ],
            );
            if ((int) $request->user_id !== (int) $employee->id
                || (int) $request->leave_type_id !== (int) $leaveType->id) {
                throw new RuntimeException("Presentation leave request {$requestNumber} has conflicting ownership.");
            }

            LeaveApproval::query()->firstOrCreate(
                ['leave_request_id' => $request->id, 'reviewer_id' => $hrManager->id],
                [
                    'status' => $example['status'],
                    'comment' => $example['status'] === 'APPROVED' ? 'Approved for the demo.' : null,
                    'reviewed_at' => $example['status'] === 'APPROVED' ? now() : null,
                ],
            );

            if ($request->wasRecentlyCreated) {
                $balance = LeaveBalance::query()->firstOrCreate(
                    ['user_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'year' => now()->year],
                    ['total_days' => $leaveType->days_allowed, 'used_days' => 0, 'pending_days' => 0, 'carried_forward' => 0],
                );
                $balance->increment($example['status'] === 'APPROVED' ? 'used_days' : 'pending_days', 1);
            }
        }
    }

    private function seedNotifications(array $managers, array $employeesByDepartment): void
    {
        foreach ($managers as $code => $manager) {
            $employee = $employeesByDepartment[$code]->first();
            Notification::query()->firstOrCreate(
                ['event_key' => 'demo.presentation.team-update.' . strtolower($code)],
                [
                    'user_id' => $employee->id,
                    'type' => 'GENERAL',
                    'title' => 'Presentation demo team update',
                    'message' => "Your {$manager->department->name} workspace is ready with sample department records.",
                    'related_type' => null,
                    'related_id' => null,
                    'is_read' => false,
                ],
            );
        }
    }

    private function writeLocalCredentials($departments, array $managers): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $lines = [
            'WorkSphere presentation demo credentials',
            'Generated by DatabaseSeeder. Development/demo use only; do not deploy these passwords.',
            '',
        ];
        foreach (self::DEPARTMENT_ROLES as $code => $roles) {
            $department = $departments->get($code);
            $lines[] = $department->name . ':';
            $users = User::query()->where('department_id', $department->id)
                ->whereIn('role', [$roles['manager'], $roles['employee']])
                ->where('status', 'ACTIVE')
                ->orderByRaw('CASE WHEN role = ? THEN 0 ELSE 1 END', [$roles['manager']])
                ->orderBy('name')
                ->get();

            foreach ($users as $user) {
                $password = $this->knownDemoPassword($user);
                if ($password === null) {
                    throw new RuntimeException("Cannot document a known demo password for {$user->email}; existing account credentials were not changed.");
                }
                $roleLabel = $user->role === $roles['manager'] ? 'Manager' : 'Employee';
                $lines[] = "{$roleLabel}: {$user->name} | {$user->email} | {$password}";
            }
            $lines[] = '';
        }

        $path = storage_path('app/demo-credentials.txt');
        if (file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write local presentation credentials to storage/app/demo-credentials.txt.');
        }
    }

    private function knownDemoPassword(User $user): ?string
    {
        foreach ([self::DEMO_PASSWORD, 'password'] as $candidate) {
            if (Hash::check($candidate, $user->password)) {
                return $candidate;
            }
        }

        return null;
    }

    private function assertCustomerOwnership(Customer $customer, User $employee, Department $department): void
    {
        if ((int) $customer->assigned_to !== (int) $employee->id
            || (int) $customer->assigned_department_id !== (int) $department->id) {
            throw new RuntimeException("Presentation customer {$customer->customer_code} has conflicting department ownership.");
        }
    }

    private function positionFor(string $role): string
    {
        return match ($role) {
            'HR_EMPLOYEE' => 'HR Coordinator',
            'SALES_EMPLOYEE' => 'Sales Executive',
            'IT_EMPLOYEE' => 'IT Support Specialist',
            'MARKETING_EMPLOYEE' => 'Marketing Associate',
            default => 'Employee',
        };
    }

    private function isWorkingDate(Carbon $date, array $workingDays, string $timezone): bool
    {
        return in_array(strtoupper($date->format('D')), $workingDays, true)
            && !Holiday::query()->whereDate('holiday_date', $date->toDateString())->exists();
    }

    private function nextWorkingDate(int $daysAhead): Carbon
    {
        $settings = CompanySettings::current();
        $timezone = $settings->timezone;
        $date = Carbon::today($timezone);
        $count = 0;
        while ($count < $daysAhead) {
            $date->addDay();
            if ($this->isWorkingDate($date, $settings->working_days, $timezone)) {
                $count++;
            }
        }

        return $date;
    }
}

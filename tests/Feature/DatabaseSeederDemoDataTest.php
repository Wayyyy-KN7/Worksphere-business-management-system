<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\CompanySettings;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\DocumentVersion;
use App\Models\FollowUp;
use App\Models\LeaveApproval;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\Opportunity;
use App\Models\Reminder;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\CustomerSource;
use App\Models\User;
use Database\Seeders\CompanyPresentationSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoWorkflowSeeder;
use Database\Seeders\OpportunitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseSeederDemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_creates_repeatable_workflow_data_without_duplicates(): void
    {
        Storage::fake('public');
        $seeder = new DatabaseSeeder();
        $seeder->run();

        $this->assertSame(5, Sale::query()->count());
        $this->assertSame(3, Sale::query()->where('sale_number', 'like', 'SAL-DEMO-2026-%')->pluck('sale_date')
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m'))
            ->unique()->count());
        $this->assertSame(5, Opportunity::query()->where('opportunity_number', 'like', 'OPP-DEMO-2026-%')->count());
        $this->assertSame(2, Sale::query()->where('sale_number', 'like', 'DEMO-PRES-SALE-%')->count());
        $this->assertSame(7, Opportunity::query()->where('opportunity_number', 'like', 'DEMO-PRES-%')->count());
        $this->assertSame(4, Customer::query()->where('customer_code', 'like', 'DEMO-PRES-SAL-%')->count());
        $this->assertSame(3, Customer::query()->where('customer_code', 'like', 'DEMO-PRES-MKT-%')->count());
        $this->assertSame(10, FollowUp::query()->where('title', 'like', 'DEMO %')->count());
        $this->assertSame(2, Reminder::query()->whereHas('followUp', fn ($query) => $query->where('title', 'like', 'DEMO Sales - %'))->count());
        $this->assertSame(2, SaleItem::query()->whereHas('sale', fn ($query) => $query->where('sale_number', 'like', 'DEMO-PRES-SALE-%'))->count());
        $this->assertSame(3, CustomerSource::query()->whereIn('name', ['LinkedIn Campaign', 'Product Webinar', 'Technology Expo'])->count());
        $this->assertSame(2, Opportunity::query()->whereIn('opportunity_number', [
            'OPP-DEMO-2026-0004',
            'OPP-DEMO-2026-0005',
        ])->where('status', 'OPEN')->count());
        $this->assertSame(1, Document::query()
            ->where('title', 'Company Operations Bulletin')
            ->where('access_level', 'COMPANY_WIDE')
            ->where('status', 'PUBLISHED')
            ->count());
        $this->assertSame(1, DocumentShare::query()->whereNotNull('user_id')->count());
        $this->assertSame(1, DocumentShare::query()->whereNotNull('department_id')->count());

        $invoiceDocuments = Document::query()
            ->where('title', 'like', 'DEMO Invoice SAL-%')
            ->with(['versions', 'documentable'])
            ->get();
        $this->assertCount(3, $invoiceDocuments);
        $this->assertSame(3, DocumentVersion::query()->count());
        foreach ($invoiceDocuments as $document) {
            $this->assertSame('INVOICE', $document->document_type);
            $this->assertSame('PUBLISHED', $document->status);
            $this->assertInstanceOf(Sale::class, $document->documentable);
            $this->assertCount(1, $document->versions);
            Storage::disk('public')->assertExists($document->versions->first()->file_path);
        }

        $newEmployees = [
            'mila.stone@admin-demo.test' => ['ADMIN_EMPLOYEE', 'ADMIN'],
            'ava.chen@sales-demo.test' => ['SALES_EMPLOYEE', 'SALES'],
            'leo.kim@sales-demo.test' => ['SALES_EMPLOYEE', 'SALES'],
            'nora.rivera@hr-demo.test' => ['HR_EMPLOYEE', 'HR'],
            'omar.patel@marketing-demo.test' => ['MARKETING_EMPLOYEE', 'MKT'],
            'ivy.wong@it-demo.test' => ['IT_EMPLOYEE', 'IT'],
            'noah.reed@it-demo.test' => ['IT_EMPLOYEE', 'IT'],
        ];
        $this->assertSame(7, User::query()->whereIn('email', array_keys($newEmployees))->count());
        foreach ($newEmployees as $email => [$role, $departmentCode]) {
            $employee = User::query()->where('email', $email)->with('department')->firstOrFail();
            $this->assertSame($role, $employee->role);
            $this->assertSame($departmentCode, $employee->department->code);
        }
        foreach (['ADMIN', 'HR', 'SALES', 'MKT', 'IT'] as $departmentCode) {
            $this->assertTrue(
                User::query()->whereHas('department', fn ($query) => $query->where('code', $departmentCode))
                    ->where('role', 'like', '%_EMPLOYEE')->exists(),
                "Expected an active demo employee in {$departmentCode}.",
            );
        }
        foreach ([
            'HR' => ['HR_MANAGER', 'HR_EMPLOYEE'],
            'SALES' => ['SALES_MANAGER', 'SALES_EMPLOYEE'],
            'IT' => ['IT_MANAGER', 'IT_EMPLOYEE'],
            'MKT' => ['MARKETING_MANAGER', 'MARKETING_EMPLOYEE'],
        ] as $departmentCode => [$managerRole, $employeeRole]) {
            $department = Department::query()->where('code', $departmentCode)->firstOrFail();
            $this->assertSame(1, User::query()->where('department_id', $department->id)->where('role', $managerRole)->where('status', 'ACTIVE')->count());
            $this->assertSame(6, User::query()->where('department_id', $department->id)->where('role', $employeeRole)->where('status', 'ACTIVE')->count());
        }

        $settings = CompanySettings::current();
        $scheduledDays = collect();
        $monthStart = Carbon::create(2026, 9, 1, 0, 0, 0, $settings->timezone);
        for ($day = 1; $day <= 30; $day++) {
            $date = $monthStart->copy()->setDay($day);
            if (in_array(strtoupper($date->format('D')), $settings->working_days, true)) {
                $scheduledDays->push($date->toDateString());
            }
        }
        $demoUsers = User::query()->whereIn('email', [
            'admin@company.com',
            'mila.stone@admin-demo.test',
            'hr.manager@company.com',
            'sales.manager@company.com',
            'mgmg@company.com',
            'marketing.manager@company.com',
            'mathida@company.com',
            'it.manager@company.com',
            'masu@company.com',
            ...array_keys($newEmployees),
        ])->where('status', 'ACTIVE')->get();
        $actualAttendanceDays = Attendance::query()->whereIn('user_id', $demoUsers->pluck('id'))
            ->whereDate('attendance_date', '>=', '2026-09-01')
            ->whereDate('attendance_date', '<=', '2026-09-30')
            ->distinct()->orderBy('attendance_date')->pluck('attendance_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString());
        $this->assertSame(
            $scheduledDays->values()->all(),
            $actualAttendanceDays->values()->all(),
            'Attendance dates should cover configured workdays. Actual: ' . json_encode($actualAttendanceDays->values()->all()),
        );
        $this->assertSame(
            $demoUsers->count() * $scheduledDays->count(),
            Attendance::query()->whereIn('user_id', $demoUsers->pluck('id'))
                ->whereDate('attendance_date', '>=', '2026-09-01')
                ->whereDate('attendance_date', '<=', '2026-09-30')->count(),
        );
        foreach (['PRESENT', 'LATE', 'LEAVE', 'HOLIDAY', 'ABSENT', 'EARLY_DEPARTURE', 'HALF_DAY'] as $status) {
            $this->assertGreaterThan(0, Attendance::query()
                ->whereDate('attendance_date', '>=', '2026-09-01')
                ->whereDate('attendance_date', '<=', '2026-09-30')
                ->where('status', $status)->count(), "Expected demo attendance status {$status}.");
        }
        $this->assertSame(0, Attendance::query()
            ->whereIn('status', ['ABSENT', 'LEAVE', 'HOLIDAY'])
            ->where(fn ($query) => $query->whereNotNull('check_in')->orWhereNotNull('check_out'))
            ->count());
        $this->assertSame(0, Attendance::query()->where('status', 'LATE')->where('late_minutes', '<=', 0)->count());
        $this->assertSame(0, Attendance::query()->whereIn('status', ['EARLY_DEPARTURE', 'HALF_DAY'])
            ->whereNull('notes')->count());

        $seededLeaves = LeaveRequest::query()->where('request_number', 'like', 'LR-DEMO-2026-%')->get();
        $this->assertCount(6, $seededLeaves);
        $this->assertSame(
            ['APPROVED' => 2, 'PENDING' => 2, 'REJECTED' => 2],
            $seededLeaves->countBy('status')->sortKeys()->all(),
        );
        foreach ($seededLeaves as $leaveRequest) {
            $approval = LeaveApproval::query()->where('leave_request_id', $leaveRequest->id)->firstOrFail();
            $this->assertSame($leaveRequest->status, $approval->status);
            $this->assertSame($leaveRequest->status === 'PENDING', $approval->reviewed_at === null);
            $this->assertSame(
                $leaveRequest->user->department_id,
                $approval->reviewer->department_id,
            );
            $balance = LeaveBalance::query()
                ->where('user_id', $leaveRequest->user_id)
                ->where('leave_type_id', $leaveRequest->leave_type_id)
                ->where('year', 2026)
                ->firstOrFail();
            $employeeRequests = LeaveRequest::query()
                ->where('user_id', $leaveRequest->user_id)
                ->where('leave_type_id', $leaveRequest->leave_type_id)
                ->whereYear('start_date', 2026);
            $this->assertSame(
                (float) (clone $employeeRequests)->where('status', 'APPROVED')->sum('total_days'),
                (float) $balance->used_days,
            );
            $this->assertSame(
                (float) (clone $employeeRequests)->where('status', 'PENDING')->sum('total_days'),
                (float) $balance->pending_days,
            );
            $attendance = Attendance::query()
                ->where('user_id', $leaveRequest->user_id)
                ->whereDate('attendance_date', $leaveRequest->start_date->toDateString())
                ->first();
            if ($leaveRequest->status === 'APPROVED') {
                $this->assertSame('LEAVE', $attendance?->status);
            } else {
                $this->assertNotSame('LEAVE', $attendance?->status);
            }
        }
        $this->assertSame(13, Notification::query()->where('event_key', 'like', 'demo.%')->count());
        $this->assertSame(2, LeaveRequest::query()->where('request_number', 'like', 'LR-DEMO-PRES-HR-%')->count());

        $countsBeforeRepeat = $this->demoRecordCounts();
        $seeder->run();
        $this->assertSame($countsBeforeRepeat, $this->demoRecordCounts());

        $this->assertSame(5, Sale::query()->count());
        $this->assertSame(12, Opportunity::query()->count());
        $this->assertSame(12, Document::query()->count());
        $this->assertSame(2, DocumentShare::query()->count());
    }

    public function test_opportunity_seeder_is_idempotent_and_uses_supported_closed_states(): void
    {
        $this->seed(DatabaseSeeder::class);
        $seeder = new OpportunitySeeder();
        $seeder->run();
        $seeder->run();

        $opportunities = Opportunity::query()
            ->where('opportunity_number', 'like', 'OPP-DEMO-EXTRA-2026-%')
            ->get();
        $this->assertCount(6, $opportunities);
        $this->assertSame(4, $opportunities->where('status', 'OPEN')->count());
        $this->assertSame(2, $opportunities->where('status', 'CLOSED')->count());
        $this->assertSame(1, $opportunities->where('stage', 'WON')->where('status', 'CLOSED')->count());
        $this->assertSame(1, $opportunities->where('stage', 'LOST')->where('status', 'CLOSED')->count());
    }

    public function test_demo_attendance_uses_configured_timezone_workdays_and_holiday_dates(): void
    {
        Storage::fake('public');
        CompanySettings::create([
            'company_name' => 'Demo Calendar Test',
            'timezone' => 'Asia/Tokyo',
            'currency' => 'USD',
            'date_format' => 'Y-m-d',
            'working_days' => ['MON', 'THU'],
            'work_start' => '08:30',
            'work_end' => '16:30',
        ]);

        (new DatabaseSeeder())->run();

        $scheduledDates = collect();
        $monthStart = Carbon::create(2026, 9, 1, 0, 0, 0, 'Asia/Tokyo');
        for ($day = 1; $day <= 30; $day++) {
            $date = $monthStart->copy()->setDay($day);
            if (in_array(strtoupper($date->format('D')), ['MON', 'THU'], true)
                || $date->toDateString() === '2026-09-16') {
                $scheduledDates->push($date->toDateString());
            }
        }
        $demoUsers = User::query()->whereIn('email', [
            'admin@company.com',
            'mila.stone@admin-demo.test',
            'hr.manager@company.com',
            'sales.manager@company.com',
            'mgmg@company.com',
            'marketing.manager@company.com',
            'mathida@company.com',
            'it.manager@company.com',
            'masu@company.com',
            'ava.chen@sales-demo.test',
            'leo.kim@sales-demo.test',
            'nora.rivera@hr-demo.test',
            'omar.patel@marketing-demo.test',
            'ivy.wong@it-demo.test',
            'noah.reed@it-demo.test',
        ])->get();
        $attendances = Attendance::query()->whereIn('user_id', $demoUsers->pluck('id'))
            ->whereDate('attendance_date', '>=', '2026-09-01')
            ->whereDate('attendance_date', '<=', '2026-09-30')
            ->get();
        $this->assertSame($demoUsers->count() * $scheduledDates->count(), $attendances->count());

        $holidayAttendance = $attendances->filter(
            fn (Attendance $attendance) => $attendance->attendance_date->toDateString() === '2026-09-16',
        );
        $this->assertCount($demoUsers->count(), $holidayAttendance);
        $this->assertSame(['HOLIDAY'], $holidayAttendance->pluck('status')->unique()->values()->all());
        $firstPresent = $attendances->first(fn (Attendance $attendance) => $attendance->status === 'PRESENT');
        $this->assertNotNull($firstPresent);
        $this->assertSame('08:30:00', $firstPresent->check_in);
        $this->assertSame('16:30:00', $firstPresent->check_out);
    }

    public function test_department_demo_accounts_can_log_in_and_open_their_department_workspace(): void
    {
        Storage::fake('public');
        (new DatabaseSeeder())->run();
        $users = User::query()
            ->whereIn('role', [
                'HR_MANAGER', 'HR_EMPLOYEE',
                'SALES_MANAGER', 'SALES_EMPLOYEE',
                'IT_MANAGER', 'IT_EMPLOYEE',
                'MARKETING_MANAGER', 'MARKETING_EMPLOYEE',
            ])
            ->where('status', 'ACTIVE')
            ->with('department')
            ->get();

        $this->assertCount(28, $users);
        foreach ($users as $user) {
            $password = Hash::check('Demo-Only-2026!', $user->password) ? 'Demo-Only-2026!' : 'password';
            $this->assertTrue(Hash::check($password, $user->password), "Missing demo password for {$user->email}.");
            $this->post(route('login'), ['email' => $user->email, 'password' => $password])
                ->assertRedirect(route('dashboard'));
            $this->get(route('dashboard'))->assertOk();

            $workspaceRoute = match ($user->department->code) {
                'HR' => $user->role === 'HR_MANAGER' ? 'hr.attendance.index' : 'employee.attendance.index',
                'SALES' => 'crm.sales.index',
                'IT' => 'documents.index',
                'MKT' => 'crm.customers.index',
                default => throw new \RuntimeException("Unexpected demo department {$user->department->code}."),
            };
            $this->get(route($workspaceRoute))->assertOk();
            $this->post(route('logout'))->assertRedirect(route('login'));
            Auth::logout();
        }
    }

    public function test_hr_dashboard_attendance_chart_covers_the_latest_full_calendar_month(): void
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $hrEmployee = User::query()->where('email', 'masu@company.com')->firstOrFail();

        $this->actingAs($hrEmployee)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('data', function (array $data): bool {
                $chart = $data['attendanceChart'];
                $latestAttendanceDate = Carbon::parse(Attendance::query()->max('attendance_date'));
                $this->assertSame($latestAttendanceDate->format('F Y'), $chart['month']);
                $this->assertCount($latestAttendanceDate->daysInMonth, $chart['labels']);
                foreach (['present', 'late', 'absent', 'leave', 'holiday'] as $series) {
                    $this->assertCount($latestAttendanceDate->daysInMonth, $chart[$series]);
                    $this->assertContainsOnly('int', $chart[$series]);
                }
                $this->assertGreaterThan(0, array_sum($chart['present']));
                $this->assertGreaterThan(0, array_sum($chart['late']));
                $this->assertGreaterThan(0, LeaveRequest::query()
                    ->where('status', 'APPROVED')
                    ->where('request_number', 'like', 'LR-DEMO-%')
                    ->count());

                return true;
            });

        $hrManager = User::query()->where('email', 'hr.manager@company.com')->firstOrFail();
        $this->actingAs($hrManager)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('data', function (array $data): bool {
                $chart = $data['attendanceChart'];
                $latestAttendanceDate = Carbon::parse(Attendance::query()->max('attendance_date'));
                $this->assertSame($latestAttendanceDate->format('F Y'), $chart['month']);
                $firstDayTotal = array_sum(array_map(
                    fn (string $series) => $chart[$series][0],
                    ['present', 'late', 'absent', 'leave', 'holiday'],
                ));
                $this->assertLessThanOrEqual(3, $firstDayTotal);

                return true;
            });
    }

    public function test_seeding_preserves_unrelated_existing_employee_attendance_and_customers(): void
    {
        $salesDepartment = Department::create([
            'name' => 'Sales',
            'code' => 'SALES',
            'status' => 'ACTIVE',
        ]);
        $unmanagedEmployee = User::create([
            'employee_id' => 'EXISTING-EMP-01',
            'name' => 'Existing Synthetic Employee',
            'email' => 'existing.employee@company.test',
            'password' => bcrypt('Existing-Demo-Only!'),
            'department_id' => $salesDepartment->id,
            'role' => 'SALES_EMPLOYEE',
            'status' => 'ACTIVE',
        ]);
        $attendance = Attendance::create([
            'user_id' => $unmanagedEmployee->id,
            'attendance_date' => '2026-09-01',
            'status' => 'LATE',
            'check_in' => '09:15:00',
            'late_minutes' => 15,
            'notes' => 'Existing unrelated attendance record.',
        ]);
        $customer = Customer::create([
            'customer_code' => 'CUSTOMER-EXISTING-01',
            'name' => 'Existing Synthetic Customer',
            'email' => 'existing.customer@company.test',
            'assigned_to' => $unmanagedEmployee->id,
            'status' => 'ACTIVE',
        ]);

        (new DatabaseSeeder())->run();

        $this->assertSame('Existing unrelated attendance record.', $attendance->fresh()->notes);
        $this->assertSame('LATE', $attendance->fresh()->status);
        $this->assertSame('Existing Synthetic Customer', $customer->fresh()->name);
        $this->assertSame(1, Customer::query()->where('email', 'existing.customer@company.test')->count());
        $this->assertSame(1, Attendance::query()->where('user_id', $unmanagedEmployee->id)
            ->whereDate('attendance_date', '2026-09-01')->count());
    }

    public function test_demo_seeders_refuse_to_run_in_production_without_writing_records(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        foreach ([new DatabaseSeeder(), new DemoWorkflowSeeder(), new CompanyPresentationSeeder(), new OpportunitySeeder()] as $seeder) {
            try {
                $seeder->run();
                $this->fail('Expected production seeding to be blocked.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Demo data seeding is disabled in production.', $exception->getMessage());
            }
        }

        $this->assertSame(0, Department::query()->count());
        $this->assertSame(0, User::query()->count());
    }

    private function demoRecordCounts(): array
    {
        return [
            'users' => User::query()->count(),
            'customers' => \App\Models\Customer::query()->count(),
            'attendance' => Attendance::query()->count(),
            'leaves' => LeaveRequest::query()->count(),
            'approvals' => LeaveApproval::query()->count(),
            'notifications' => Notification::query()->count(),
            'documents' => Document::query()->count(),
            'versions' => DocumentVersion::query()->count(),
            'opportunities' => Opportunity::query()->count(),
            'sales' => Sale::query()->count(),
            'sale_items' => SaleItem::query()->count(),
            'follow_ups' => FollowUp::query()->count(),
            'reminders' => Reminder::query()->count(),
            'leave_balances' => LeaveBalance::query()->count(),
            'customer_sources' => CustomerSource::query()->count(),
        ];
    }
}

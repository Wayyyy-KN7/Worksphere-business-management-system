<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\CompanySettings;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Holiday;
use App\Models\LeaveApproval;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Sale;
use App\Models\User;
use App\Services\CrmNotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DemoWorkflowSeeder extends Seeder
{
    private const DEMO_YEAR = 2026;

    private const EMPLOYEES = [
        [
            'employee_id' => 'DEMO-ADMIN-001',
            'name' => 'Mila Stone',
            'email' => 'mila.stone@admin-demo.test',
            'role' => 'ADMIN_EMPLOYEE',
            'department' => 'ADMIN',
        ],
        [
            'employee_id' => 'DEMO-SALES-001',
            'name' => 'Ava Chen',
            'email' => 'ava.chen@sales-demo.test',
            'role' => 'SALES_EMPLOYEE',
            'department' => 'SALES',
        ],
        [
            'employee_id' => 'DEMO-SALES-002',
            'name' => 'Leo Kim',
            'email' => 'leo.kim@sales-demo.test',
            'role' => 'SALES_EMPLOYEE',
            'department' => 'SALES',
        ],
        [
            'employee_id' => 'DEMO-HR-001',
            'name' => 'Nora Rivera',
            'email' => 'nora.rivera@hr-demo.test',
            'role' => 'HR_EMPLOYEE',
            'department' => 'HR',
        ],
        [
            'employee_id' => 'DEMO-MKT-001',
            'name' => 'Omar Patel',
            'email' => 'omar.patel@marketing-demo.test',
            'role' => 'MARKETING_EMPLOYEE',
            'department' => 'MKT',
        ],
        [
            'employee_id' => 'DEMO-IT-001',
            'name' => 'Ivy Wong',
            'email' => 'ivy.wong@it-demo.test',
            'role' => 'IT_EMPLOYEE',
            'department' => 'IT',
        ],
        [
            'employee_id' => 'DEMO-IT-002',
            'name' => 'Noah Reed',
            'email' => 'noah.reed@it-demo.test',
            'role' => 'IT_EMPLOYEE',
            'department' => 'IT',
        ],
    ];

    private const MANAGERS = [
        'HR' => ['email' => 'hr.manager@company.com', 'role' => 'HR_MANAGER'],
        'SALES' => ['email' => 'sales.manager@company.com', 'role' => 'SALES_MANAGER'],
        'MKT' => ['email' => 'marketing.manager@company.com', 'role' => 'MARKETING_MANAGER'],
        'IT' => ['email' => 'it.manager@company.com', 'role' => 'IT_MANAGER'],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Demo data seeding is disabled in production.');
        }

        $settings = CompanySettings::current();
        $timezone = $settings->timezone;
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            throw new \RuntimeException("Invalid company timezone '{$timezone}' for demo attendance seeding.");
        }

        $workingDays = $settings->working_days ?? [];
        if ($workingDays === []) {
            throw new \RuntimeException('Company working_days must include at least one day before seeding attendance.');
        }
        $invalidWorkingDays = array_diff($workingDays, ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN']);
        if ($invalidWorkingDays !== []) {
            throw new \RuntimeException('Company working_days contains unsupported day values.');
        }
        $shiftStart = CarbonImmutable::parse('2026-09-01 ' . $settings->work_start, $timezone);
        $shiftEnd = CarbonImmutable::parse('2026-09-01 ' . $settings->work_end, $timezone);
        if (!$shiftEnd->gt($shiftStart)) {
            throw new \RuntimeException('Company work_end must be later than work_start to seed demo attendance.');
        }

        $departments = Department::query()->whereIn('code', ['ADMIN', 'HR', 'SALES', 'MKT', 'IT'])->get()->keyBy('code');
        $managers = $this->resolveManagers($departments);
        $employees = $this->createEmployees($departments);
        $demoUsers = $this->resolveDemoUsers($employees);

        $leaveType = LeaveType::query()->where('code', 'AL')->firstOrFail();
        $this->seedDemoHoliday();
        $businessDates = $this->businessDates($timezone, $workingDays);
        $leaveDates = $businessDates->reject(fn (CarbonImmutable $date) => Holiday::query()
            ->whereDate('holiday_date', $date->toDateString())
            ->exists())->values();
        if ($leaveDates->isEmpty()) {
            throw new \RuntimeException('No configured working days fall within the fixed demo month.');
        }

        $this->seedLeaveExamples($employees, $managers, $leaveType, $leaveDates, $timezone, $workingDays);
        $this->seedAttendance($demoUsers, $businessDates, $timezone, $settings->work_start, $settings->work_end);
        $this->seedInvoices();
    }

    private function resolveManagers($departments): array
    {
        $managers = [];
        foreach (self::MANAGERS as $departmentCode => $identity) {
            $department = $departments->get($departmentCode);
            $manager = User::query()->where('email', $identity['email'])->first();
            if (!$department || !$manager
                || $manager->role !== $identity['role']
                || (int) $manager->department_id !== (int) $department->id) {
                throw new \RuntimeException("Expected seeded {$departmentCode} manager is missing or has an incompatible role/department.");
            }
            $managers[$departmentCode] = $manager;
        }

        return $managers;
    }

    private function createEmployees($departments): array
    {
        $employees = [];
        foreach (self::EMPLOYEES as $identity) {
            $department = $departments->get($identity['department']);
            if (!$department) {
                throw new \RuntimeException("Missing department {$identity['department']} for demo employee.");
            }

            $employee = User::query()->firstOrCreate(
                ['email' => $identity['email']],
                [
                    'employee_id' => $identity['employee_id'],
                    'name' => $identity['name'],
                    'password' => Hash::make('Demo-Only-2026!'),
                    'department_id' => $department->id,
                    'role' => $identity['role'],
                    'position' => str_replace('_', ' ', ucfirst(strtolower($identity['role']))),
                    'joining_date' => '2026-01-05',
                    'status' => 'ACTIVE',
                ],
            );

            if ($employee->employee_id !== $identity['employee_id']
                || $employee->role !== $identity['role']
                || (int) $employee->department_id !== (int) $department->id) {
                throw new \RuntimeException("Demo employee {$identity['email']} already exists with conflicting demo identity data.");
            }
            $employees[$identity['email']] = $employee;
        }

        return $employees;
    }

    private function resolveDemoUsers(array $employees)
    {
        $baseEmails = [
            'admin@company.com',
            'mila.stone@admin-demo.test',
            'hr.manager@company.com',
            'sales.manager@company.com',
            'mgmg@company.com',
            'marketing.manager@company.com',
            'mathida@company.com',
            'it.manager@company.com',
            'masu@company.com',
        ];
        $baseUsers = User::query()->whereIn('email', $baseEmails)->get();

        return $baseUsers->concat(array_values($employees))
            ->where('status', 'ACTIVE')
            ->unique('id')
            ->values();
    }

    private function businessDates(string $timezone, array $workingDays)
    {
        $holidays = Holiday::query()
            ->whereDate('holiday_date', '>=', '2026-09-01')
            ->whereDate('holiday_date', '<=', '2026-09-30')
            ->pluck('holiday_date')
            ->map(fn ($date) => CarbonImmutable::parse($date, $timezone)->toDateString())
            ->all();
        $dates = collect();
        $start = new DateTimeImmutable(self::DEMO_YEAR . '-09-01', new DateTimeZone($timezone));
        $end = new DateTimeImmutable(self::DEMO_YEAR . '-10-01', new DateTimeZone($timezone));
        foreach (new DatePeriod($start, new DateInterval('P1D'), $end) as $localDate) {
            $date = CarbonImmutable::instance($localDate);
            if (in_array(strtoupper($date->format('D')), $workingDays, true)
                || in_array($date->toDateString(), $holidays, true)) {
                $dates->push($date);
            }
        }

        return $dates;
    }

    private function seedDemoHoliday(): void
    {
        if (!Holiday::query()->whereDate('holiday_date', '2026-09-16')->exists()) {
            Holiday::query()->create([
                'holiday_date' => '2026-09-16',
                'name' => 'Demo Company Holiday',
                'description' => 'Synthetic holiday included for attendance-history demonstrations.',
                'is_recurring' => false,
            ]);
        }
    }

    private function seedLeaveExamples(array $employees, array $managers, LeaveType $leaveType, $businessDates, string $timezone, array $workingDays): void
    {
        $futureDates = $this->nextBusinessDates(
            CarbonImmutable::create(self::DEMO_YEAR, 9, 30, 0, 0, 0, $timezone),
            4,
            $timezone,
            $workingDays,
        );
        $monthDateA = $businessDates->get((int) floor(($businessDates->count() - 1) * 0.2))->toDateString();
        $monthDateB = $businessDates->get((int) floor(($businessDates->count() - 1) * 0.75))->toDateString();
        $futureDates = $futureDates->map(fn (CarbonImmutable $date) => $date->toDateString())->values();

        $examples = [
            ['employee' => 'ava.chen@sales-demo.test', 'status' => 'APPROVED', 'date' => $monthDateA],
            ['employee' => 'leo.kim@sales-demo.test', 'status' => 'PENDING', 'date' => $futureDates[0]],
            ['employee' => 'nora.rivera@hr-demo.test', 'status' => 'REJECTED', 'date' => $futureDates[2]],
            ['employee' => 'omar.patel@marketing-demo.test', 'status' => 'APPROVED', 'date' => $monthDateB],
            ['employee' => 'ivy.wong@it-demo.test', 'status' => 'PENDING', 'date' => $futureDates[1]],
            ['employee' => 'noah.reed@it-demo.test', 'status' => 'REJECTED', 'date' => $futureDates[3]],
        ];

        foreach ($examples as $index => $example) {
            $employee = $employees[$example['employee']];
            $manager = $managers[$employee->department->code];
            $requestNumber = 'LR-DEMO-2026-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $reason = match ($example['status']) {
                'APPROVED' => 'Demo annual leave for a personal day.',
                'REJECTED' => 'Demo annual leave request for review.',
                default => 'Demo annual leave request awaiting review.',
            };
            $rejectionReason = $example['status'] === 'REJECTED'
                ? 'The requested dates conflict with planned team coverage.'
                : null;
            $reviewedAt = CarbonImmutable::parse($example['date'], $timezone)->setTime(16, 0);
            if ($example['status'] !== 'PENDING') {
                $reviewedAt = $reviewedAt->addDay();
            }

            DB::transaction(function () use ($employee, $manager, $leaveType, $requestNumber, $example, $reason, $rejectionReason, $reviewedAt) {
                $leaveRequest = LeaveRequest::query()->firstOrCreate(
                    ['request_number' => $requestNumber],
                    [
                        'user_id' => $employee->id,
                        'leave_type_id' => $leaveType->id,
                        'start_date' => $example['date'],
                        'end_date' => $example['date'],
                        'total_days' => 1,
                        'reason' => $reason,
                        'status' => $example['status'],
                        'rejection_reason' => $rejectionReason,
                    ],
                );

                if ((int) $leaveRequest->user_id !== (int) $employee->id
                    || (int) $leaveRequest->leave_type_id !== (int) $leaveType->id) {
                    throw new \RuntimeException("Demo leave request {$requestNumber} conflicts with existing data.");
                }

                $approvalDefaults = [
                    'status' => $leaveRequest->status === 'PENDING' ? 'PENDING' : $leaveRequest->status,
                    'comment' => $leaveRequest->status === 'REJECTED' ? $rejectionReason : 'Reviewed for demo workflow.',
                    'reviewed_at' => $leaveRequest->status === 'PENDING' ? null : $reviewedAt,
                ];
                LeaveApproval::query()->firstOrCreate(
                    ['leave_request_id' => $leaveRequest->id, 'reviewer_id' => $manager->id],
                    $approvalDefaults,
                );

                if ($created = $leaveRequest->wasRecentlyCreated) {
                    $daysByYear = app(\App\Services\LeaveBalanceService::class)->calendarDaysByYear(
                        $leaveRequest->start_date->toDateString(),
                        $leaveRequest->end_date->toDateString(),
                    );
                    foreach ($daysByYear as $year => $days) {
                        $balance = LeaveBalance::query()->firstOrCreate(
                            ['user_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'year' => $year],
                            ['total_days' => $leaveType->days_allowed, 'used_days' => 0, 'pending_days' => 0, 'carried_forward' => 0],
                        );
                        if ($leaveRequest->status === 'PENDING') {
                            $balance->increment('pending_days', $days);
                        } elseif ($leaveRequest->status === 'APPROVED') {
                            $balance->increment('used_days', $days);
                        }
                    }
                }
            });

            $leaveRequest = LeaveRequest::query()->where('request_number', $requestNumber)->firstOrFail();
            $notifications = app(CrmNotificationService::class);
            if ($leaveRequest->status === 'PENDING') {
                $notifications->notifyUser(
                    $manager,
                    'LEAVE_REQUEST',
                    'Leave Request Submitted',
                    "{$employee->name} submitted demo leave request {$leaveRequest->request_number} for review.",
                    $leaveRequest,
                    'demo.leave.pending.' . $leaveRequest->request_number,
                );
            } elseif (in_array($leaveRequest->status, ['APPROVED', 'REJECTED'], true)) {
                $approved = $leaveRequest->status === 'APPROVED';
                $notifications->notifyUser(
                    $employee,
                    $approved ? 'LEAVE_APPROVED' : 'LEAVE_REJECTED',
                    $approved ? 'Leave Request Approved' : 'Leave Request Rejected',
                    $approved
                        ? "Your demo leave request ({$leaveRequest->request_number}) was approved."
                        : "Your demo leave request ({$leaveRequest->request_number}) was rejected.",
                    $leaveRequest,
                    'demo.leave.' . strtolower($leaveRequest->status) . '.' . $leaveRequest->request_number,
                );
            }
        }
    }

    private function nextBusinessDates(CarbonImmutable $after, int $count, string $timezone, array $workingDays)
    {
        $holidays = Holiday::query()->pluck('holiday_date')
            ->map(fn ($date) => CarbonImmutable::parse($date, $timezone)->toDateString())
            ->all();
        $dates = collect();
        for ($date = $after->addDay(); $dates->count() < $count; $date = $date->addDay()) {
            if (in_array(strtoupper($date->format('D')), $workingDays, true)
                && !in_array($date->toDateString(), $holidays, true)) {
                $dates->push($date);
            }
        }

        return $dates;
    }

    private function seedAttendance($users, $businessDates, string $timezone, string $workStart, string $workEnd): void
    {
        $startAt = CarbonImmutable::parse('2026-09-01 ' . $workStart, $timezone);
        $endAt = CarbonImmutable::parse('2026-09-01 ' . $workEnd, $timezone);
        if (!$endAt->gt($startAt)) {
            throw new \RuntimeException('Company work_end must be later than work_start to seed demo attendance.');
        }
        $midpoint = $startAt->addMinutes((int) floor($startAt->diffInMinutes($endAt) / 2));
        $holidays = Holiday::query()
            ->whereDate('holiday_date', '>=', '2026-09-01')
            ->whereDate('holiday_date', '<=', '2026-09-30')
            ->get()
            ->keyBy(fn (Holiday $holiday) => $holiday->holiday_date->toDateString());
        $approvedLeaveDates = LeaveRequest::query()
            ->whereIn('request_number', [
                'LR-DEMO-2026-001',
                'LR-DEMO-2026-004',
            ])
            ->where('status', 'APPROVED')
            ->get()
            ->flatMap(function (LeaveRequest $leaveRequest) {
                $dates = [];
                for ($date = $leaveRequest->start_date->copy(); $date->lte($leaveRequest->end_date); $date->addDay()) {
                    $dates[] = $date->toDateString();
                }

                return $dates;
            })
            ->unique()
            ->all();

        foreach ($users->values() as $userIndex => $user) {
            foreach ($businessDates as $dateIndex => $businessDate) {
                $date = $businessDate->toDateString();
                $dayStart = CarbonImmutable::parse($date . ' ' . $workStart, $timezone);
                $dayEnd = CarbonImmutable::parse($date . ' ' . $workEnd, $timezone);
                $status = null;
                $checkIn = null;
                $checkOut = null;
                $workingHours = 0;
                $lateMinutes = 0;
                $manualReason = null;
                $notes = null;

                if (isset($holidays[$date])) {
                    $status = 'HOLIDAY';
                    $notes = $holidays[$date]->name;
                } elseif (in_array($date, $approvedLeaveDates, true)
                    && in_array($user->email, ['ava.chen@sales-demo.test', 'omar.patel@marketing-demo.test'], true)) {
                    $status = 'LEAVE';
                    $notes = 'Approved demo annual leave.';
                } else {
                    $pattern = ($dateIndex + ($userIndex * 3)) % 17;
                    if ($pattern === 0) {
                        $status = 'ABSENT';
                        $notes = 'Demo attendance record: absent on scheduled workday.';
                    } elseif ($pattern === 1 || $pattern === 2) {
                        $status = 'LATE';
                        $lateMinutes = 20;
                        $checkIn = $dayStart->addMinutes($lateMinutes)->format('H:i:s');
                        $checkOut = $dayEnd->format('H:i:s');
                        $workingHours = round(CarbonImmutable::parse($date . ' ' . $checkIn, $timezone)->diffInMinutes($dayEnd) / 60, 2);
                        $notes = 'Demo late arrival; check-in is 20 minutes after scheduled start.';
                    } elseif ($pattern === 3) {
                        $status = 'EARLY_DEPARTURE';
                        $checkIn = $dayStart->format('H:i:s');
                        $checkOut = $dayEnd->subHour()->format('H:i:s');
                        $workingHours = round($dayStart->diffInMinutes(CarbonImmutable::parse($date . ' ' . $checkOut, $timezone)) / 60, 2);
                        $notes = 'Early departure: approved personal appointment.';
                    } elseif ($pattern === 4) {
                        $status = 'HALF_DAY';
                        $checkIn = $dayStart->format('H:i:s');
                        $checkOut = $midpoint->format('H:i:s');
                        $workingHours = round($dayStart->diffInMinutes(CarbonImmutable::parse($date . ' ' . $checkOut, $timezone)) / 60, 2);
                        $notes = 'Half-day: employee reported feeling unwell.';
                    } else {
                        $status = 'PRESENT';
                        $checkIn = $dayStart->format('H:i:s');
                        $checkOut = $dayEnd->format('H:i:s');
                        $workingHours = round($dayStart->diffInMinutes($dayEnd) / 60, 2);
                    }
                }

                $existingAttendance = Attendance::query()
                    ->where('user_id', $user->id)
                    ->whereDate('attendance_date', $date)
                    ->first();
                if (!$existingAttendance) {
                    Attendance::query()->create([
                        'user_id' => $user->id,
                        'attendance_date' => $date,
                        'check_in' => $checkIn,
                        'check_out' => $checkOut,
                        'working_hours' => $workingHours,
                        'status' => $status,
                        'late_minutes' => $lateMinutes,
                        'is_manual' => false,
                        'manual_reason' => $manualReason,
                        'notes' => $notes,
                    ]);
                }
            }
        }
    }

    private function seedInvoices(): void
    {
        $sales = Sale::query()->whereIn('sale_number', [
            'SAL-DEMO-2026-01',
            'SAL-DEMO-2026-02',
            'SAL-DEMO-2026-03',
        ])->with(['customer', 'opportunity', 'assignedUser', 'items'])->get();

        foreach ($sales as $sale) {
            if (!$sale->assignedUser || !$sale->assignedUser->department_id) {
                throw new \RuntimeException("Demo sale {$sale->sale_number} has no assigned user or department.");
            }

            $title = 'DEMO Invoice ' . $sale->sale_number;
            $document = Document::query()->firstOrCreate(
                ['title' => $title],
                [
                    'description' => 'Synthetic invoice document linked to a demo sale.',
                    'document_type' => 'INVOICE',
                    'department_id' => $sale->assignedUser->department_id,
                    'uploaded_by' => $sale->assigned_to,
                    'access_level' => 'DEPARTMENT',
                    'status' => 'PUBLISHED',
                    'documentable_type' => Sale::class,
                    'documentable_id' => $sale->id,
                ],
            );
            if ($document->documentable_type !== Sale::class || (int) $document->documentable_id !== (int) $sale->id) {
                throw new \RuntimeException("Demo invoice title '{$title}' is already used by an unrelated document.");
            }

            $fileName = 'demo_' . $sale->sale_number . '.pdf';
            $filePath = 'demo/invoices/' . $fileName;
            if (!Storage::disk('public')->exists($filePath)) {
                $pdf = Pdf::loadView('crm.sales.invoice', ['sale' => $sale]);
                if (!Storage::disk('public')->put($filePath, $pdf->output())) {
                    throw new \RuntimeException("Unable to write invoice PDF for {$sale->sale_number}.");
                }
            }
            $version = DocumentVersion::query()->firstOrCreate(
                ['document_id' => $document->id, 'version_number' => '1.0'],
                [
                    'file_path' => $filePath,
                    'file_name' => $fileName,
                    'file_size' => Storage::disk('public')->size($filePath),
                    'mime_type' => 'application/pdf',
                    'change_description' => 'Synthetic invoice PDF generated for the demo sale.',
                    'uploaded_by' => $sale->assigned_to,
                ],
            );
            if ($version->file_path !== $filePath || !Storage::disk('public')->exists($version->file_path)) {
                throw new \RuntimeException("Demo invoice version for {$sale->sale_number} has no readable stored PDF.");
            }

            app(CrmNotificationService::class)->notifyUsers(
                [$sale->assigned_to],
                'INVOICE_GENERATED',
                'Invoice Generated',
                "A demo invoice for {$sale->sale_number} is available in Documents.",
                $document,
                'demo.invoice.generated.' . $sale->sale_number,
            );
        }
    }
}

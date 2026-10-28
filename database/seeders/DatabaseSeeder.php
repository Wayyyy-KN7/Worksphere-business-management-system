<?php

namespace Database\Seeders;

use App\Models\CompanySettings;
use App\Models\Customer;
use App\Models\CustomerSource;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\FollowUp;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\Reminder;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\EmployeeIdService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Demo data seeding is disabled in production.');
        }

        CompanySettings::query()->firstOrCreate(
            ['id' => 1],
            CompanySettings::defaults()
        );

        $deptModels = [];
        foreach ($this->departments() as $dept) {
            $deptModels[$dept['code']] = Department::query()->firstOrCreate(
                ['code' => $dept['code']],
                $dept
            );
        }

        $users = [
            ['name' => 'Admin User', 'email' => 'admin@company.com', 'role' => 'ADMIN', 'department' => 'ADMIN', 'position' => 'System Administrator'],
            ['name' => 'Daw Mya', 'email' => 'hr.manager@company.com', 'role' => 'HR_MANAGER', 'department' => 'HR', 'position' => 'HR Manager'],
            ['name' => 'U Zaw', 'email' => 'sales.manager@company.com', 'role' => 'SALES_MANAGER', 'department' => 'SALES', 'position' => 'Sales Manager'],
            ['name' => 'Mg Mg', 'email' => 'mgmg@company.com', 'role' => 'SALES_EMPLOYEE', 'department' => 'SALES', 'position' => 'Sales Executive'],
            ['name' => 'Ko Aung', 'email' => 'marketing.manager@company.com', 'role' => 'MARKETING_MANAGER', 'department' => 'MKT', 'position' => 'Marketing Manager'],
            ['name' => 'Ma Thida', 'email' => 'mathida@company.com', 'role' => 'MARKETING_EMPLOYEE', 'department' => 'MKT', 'position' => 'Marketing Executive'],
            ['name' => 'Ko Min', 'email' => 'it.manager@company.com', 'role' => 'IT_MANAGER', 'department' => 'IT', 'position' => 'IT Manager'],
            ['name' => 'Ma Su', 'email' => 'masu@company.com', 'role' => 'HR_EMPLOYEE', 'department' => 'HR', 'position' => 'HR Assistant'],
        ];

        foreach ($users as $userIndex => $userData) {
            $departmentId = $deptModels[$userData['department']]->id;
            User::query()->firstOrCreate(
                ['email' => $userData['email']],
                [
                    'employee_id' => EmployeeIdService::generateUnique(),
                    'name' => $userData['name'],
                    'password' => Hash::make('password'),
                    'phone' => '09-900-000-' . str_pad((string) ($userIndex + 1), 3, '0', STR_PAD_LEFT),
                    'department_id' => $departmentId,
                    'role' => $userData['role'],
                    'position' => $userData['position'],
                    'joining_date' => '2024-01-15',
                    'status' => 'ACTIVE',
                ]
            );
        }

        $leaveTypes = [
            ['name' => 'Annual Leave', 'code' => 'AL', 'days_allowed' => 20, 'is_paid' => true, 'can_carry_forward' => true, 'max_carry_forward' => 5],
            ['name' => 'Sick Leave', 'code' => 'SL', 'days_allowed' => 10, 'is_paid' => true, 'can_carry_forward' => false, 'requires_document' => true],
            ['name' => 'Casual Leave', 'code' => 'CL', 'days_allowed' => 7, 'is_paid' => true, 'can_carry_forward' => false],
        ];

        foreach ($leaveTypes as $leaveType) {
            LeaveType::query()->firstOrCreate(['code' => $leaveType['code']], $leaveType);
        }

        foreach (['Website', 'Referral', 'Social Media', 'Existing Customer'] as $source) {
            CustomerSource::query()->firstOrCreate(['name' => $source]);
        }

        foreach ([
            ['name' => 'New Year Day', 'holiday_date' => '2026-01-01', 'is_recurring' => true],
            ['name' => 'Union Day', 'holiday_date' => '2026-02-12', 'is_recurring' => true],
            ['name' => 'Labour Day', 'holiday_date' => '2026-05-01', 'is_recurring' => true],
            ['name' => 'Christmas Day', 'holiday_date' => '2026-12-25', 'is_recurring' => true],
        ] as $holiday) {
            if (!Holiday::query()->whereDate('holiday_date', $holiday['holiday_date'])->exists()) {
                Holiday::query()->create($holiday);
            }
        }

        $salesManager = User::query()->where('email', 'sales.manager@company.com')->where('role', 'SALES_MANAGER')->first();
        $salesEmployee = User::query()->where('email', 'mgmg@company.com')->where('role', 'SALES_EMPLOYEE')->first();
        $marketingEmployee = User::query()->where('email', 'mathida@company.com')->where('role', 'MARKETING_EMPLOYEE')->first();
        $admin = User::query()->where('email', 'admin@company.com')->where('role', 'ADMIN')->first();

        if ($salesManager && $salesEmployee) {
            $customer = Customer::query()->firstOrCreate(
                ['email' => 'contact@northstar.test'],
                [
                    'customer_code' => 'CUS-DEMO-2026-001',
                    'name' => 'Northstar Labs',
                    'company_name' => 'Northstar Labs Ltd',
                    'phone' => '+95-9-12345678',
                    'city' => 'Yangon',
                    'country' => 'Myanmar',
                    'assigned_to' => $salesEmployee->id,
                    'status' => 'ACTIVE',
                    'customer_type' => 'NEW',
                ]
            );

            $customers = [
                $customer,
                Customer::query()->firstOrCreate(
                    ['email' => 'buyer@harbor-retail.test'],
                    [
                        'customer_code' => 'CUS-DEMO-2026-002',
                        'name' => 'Harbor Retail Group',
                        'company_name' => 'Harbor Retail Group Ltd',
                        'phone' => '+95-9-23456789',
                        'city' => 'Yangon',
                        'country' => 'Myanmar',
                        'assigned_to' => $salesEmployee->id,
                        'status' => 'ACTIVE',
                        'customer_type' => 'NEW',
                    ]
                ),
                Customer::query()->firstOrCreate(
                    ['email' => 'office@summit-health.test'],
                    [
                        'customer_code' => 'CUS-DEMO-2026-003',
                        'name' => 'Summit Health',
                        'company_name' => 'Summit Health Services',
                        'phone' => '+95-9-34567890',
                        'city' => 'Mandalay',
                        'country' => 'Myanmar',
                        'assigned_to' => $salesEmployee->id,
                        'status' => 'ACTIVE',
                        'customer_type' => 'NEW',
                    ]
                ),
            ];

            $closedSales = [
                [
                    'customer' => $customers[0],
                    'sale_number' => 'SAL-DEMO-2026-01',
                    'opportunity_number' => 'OPP-DEMO-2026-0001',
                    'opportunity_title' => 'CRM Upgrade Project',
                    'product_name' => 'CRM License',
                    'amount' => 25000,
                    'sale_date' => '2026-09-03',
                ],
                [
                    'customer' => $customers[1],
                    'sale_number' => 'SAL-DEMO-2026-02',
                    'opportunity_number' => 'OPP-DEMO-2026-0002',
                    'opportunity_title' => 'Retail Analytics Rollout',
                    'product_name' => 'Analytics Setup',
                    'amount' => 18000,
                    'sale_date' => '2026-08-14',
                ],
                [
                    'customer' => $customers[2],
                    'sale_number' => 'SAL-DEMO-2026-03',
                    'opportunity_number' => 'OPP-DEMO-2026-0003',
                    'opportunity_title' => 'Patient CRM Setup',
                    'product_name' => 'CRM Implementation',
                    'amount' => 12500,
                    'sale_date' => '2026-06-15',
                ],
            ];

            foreach ($closedSales as $sample) {
                $opportunity = $sample['customer']->opportunities()->firstOrCreate(
                    ['title' => $sample['opportunity_title']],
                    [
                        'opportunity_number' => $sample['opportunity_number'],
                        'assigned_to' => $salesEmployee->id,
                        'estimated_value' => $sample['amount'],
                        'probability' => 100,
                        'expected_close_date' => $sample['sale_date'],
                        'actual_close_date' => $sample['sale_date'],
                        'stage' => 'WON',
                        'status' => 'CLOSED',
                        'description' => 'Closed demo opportunity for sales reporting.',
                    ]
                );

                $sale = Sale::query()->firstOrCreate(
                    ['sale_number' => $sample['sale_number']],
                    [
                        'customer_id' => $sample['customer']->id,
                        'opportunity_id' => $opportunity->id,
                        'assigned_to' => $salesEmployee->id,
                        'sale_date' => $sample['sale_date'],
                        'payment_status' => 'PAID',
                        'payment_method' => 'BANK_TRANSFER',
                        'total_amount' => $sample['amount'],
                        'discount_amount' => 0,
                        'tax_amount' => 0,
                        'final_amount' => $sample['amount'],
                        'notes' => 'Compact seeded sale for dashboard charts.',
                    ]
                );

                SaleItem::query()->firstOrCreate(
                    ['sale_id' => $sale->id, 'product_name' => $sample['product_name']],
                    [
                        'quantity' => 1,
                        'unit_price' => $sample['amount'],
                        'discount' => 0,
                        'tax' => 0,
                        'total' => $sample['amount'],
                    ]
                );
            }

            $pipelineOpportunities = [
                [
                    'customer' => $customers[0],
                    'title' => 'Service Desk Expansion',
                    'number' => 'OPP-DEMO-2026-0004',
                    'amount' => 14000,
                    'probability' => 55,
                    'stage' => 'QUALIFIED',
                    'expected_close_date' => '2026-10-25',
                ],
                [
                    'customer' => $customers[1],
                    'title' => 'Retail Support Renewal',
                    'number' => 'OPP-DEMO-2026-0005',
                    'amount' => 9000,
                    'probability' => 70,
                    'stage' => 'PROPOSAL',
                    'expected_close_date' => '2026-10-30',
                ],
            ];

            foreach ($pipelineOpportunities as $sample) {
                $sample['customer']->opportunities()->firstOrCreate(
                    ['title' => $sample['title']],
                    [
                        'opportunity_number' => $sample['number'],
                        'assigned_to' => $salesEmployee->id,
                        'estimated_value' => $sample['amount'],
                        'probability' => $sample['probability'],
                        'expected_close_date' => $sample['expected_close_date'],
                        'stage' => $sample['stage'],
                        'status' => 'OPEN',
                        'description' => 'Open demo pipeline item for forecast charts.',
                    ]
                );
            }

            FollowUp::query()->firstOrCreate(
                ['customer_id' => $customer->id, 'title' => 'Follow up on proposal'],
                [
                    'assigned_to' => $salesEmployee->id,
                    'follow_up_date' => '2026-10-15',
                    'follow_up_time' => '10:00:00',
                    'type' => 'CALL',
                    'status' => 'PLANNED',
                    'priority' => 'HIGH',
                    'description' => 'Confirm pricing and next steps.',
                    'created_by' => $salesManager->id,
                ]
            );

            Reminder::query()->firstOrCreate(
                ['user_id' => $salesEmployee->id, 'title' => 'Send contract copy'],
                [
                    'customer_id' => $customer->id,
                    'reminder_date' => '2026-10-16',
                    'reminder_time' => '09:30:00',
                    'status' => 'PENDING',
                    'description' => 'Send proposal contract to client.',
                ]
            );
        }

        $sampleEmployee = User::query()->where('email', 'mgmg@company.com')->where('role', 'SALES_EMPLOYEE')->first();
        if ($sampleEmployee) {
            Document::query()->firstOrCreate(
                ['title' => 'Sales Process Guide'],
                [
                    'description' => 'Short sales process overview for the team.',
                    'document_type' => 'POLICY',
                    'department_id' => $sampleEmployee->department_id,
                    'uploaded_by' => $sampleEmployee->id,
                    'access_level' => 'DEPARTMENT',
                    'status' => 'PUBLISHED',
                ]
            );

            Document::query()->firstOrCreate(
                ['title' => 'Client Onboarding Checklist'],
                [
                    'description' => 'Pending review before publishing.',
                    'document_type' => 'REPORT',
                    'department_id' => $sampleEmployee->department_id,
                    'uploaded_by' => $sampleEmployee->id,
                    'access_level' => 'PRIVATE',
                    'status' => 'PENDING_REVIEW',
                ]
            );

            if ($admin) {
                Document::query()->firstOrCreate(
                    ['title' => 'Company Operations Bulletin'],
                    [
                        'description' => 'Published company-wide operations update.',
                        'document_type' => 'ANNOUNCEMENT',
                        'department_id' => $sampleEmployee->department_id,
                        'uploaded_by' => $admin->id,
                        'access_level' => 'COMPANY_WIDE',
                        'status' => 'PUBLISHED',
                    ]
                );
            }

            if ($salesManager && $marketingEmployee) {
                $selectedUserDocument = Document::query()->firstOrCreate(
                    ['title' => 'Partner Pricing Brief'],
                    [
                        'description' => 'Published demo file shared with one selected colleague.',
                        'document_type' => 'REPORT',
                        'department_id' => $salesManager->department_id,
                        'uploaded_by' => $salesManager->id,
                        'access_level' => 'SELECTED_USERS',
                        'status' => 'PUBLISHED',
                    ]
                );

                DocumentShare::query()->firstOrCreate(
                    ['document_id' => $selectedUserDocument->id, 'user_id' => $marketingEmployee->id],
                    ['access_type' => 'VIEW']
                );

                $departmentSharedDocument = Document::query()->firstOrCreate(
                    ['title' => 'Cross-Team Campaign Brief'],
                    [
                        'description' => 'Published demo file shared with the marketing department.',
                        'document_type' => 'REPORT',
                        'department_id' => $salesManager->department_id,
                        'uploaded_by' => $salesManager->id,
                        'access_level' => 'SELECTED_USERS',
                        'status' => 'PUBLISHED',
                    ]
                );

                DocumentShare::query()->firstOrCreate(
                    ['document_id' => $departmentSharedDocument->id, 'department_id' => $marketingEmployee->department_id],
                    ['access_type' => 'VIEW']
                );
            }
        }

        $this->call(DemoWorkflowSeeder::class);
        $this->call(CompanyPresentationSeeder::class);
    }

    private function departments(): array
    {
        return [
            ['name' => 'Administration', 'code' => 'ADMIN', 'description' => 'System Administration', 'status' => 'ACTIVE'],
            ['name' => 'Human Resources', 'code' => 'HR', 'description' => 'HR Department', 'status' => 'ACTIVE'],
            ['name' => 'Sales', 'code' => 'SALES', 'description' => 'Sales Department', 'status' => 'ACTIVE'],
            ['name' => 'Marketing', 'code' => 'MKT', 'description' => 'Marketing Department', 'status' => 'ACTIVE'],
            ['name' => 'Information Technology', 'code' => 'IT', 'description' => 'IT Department', 'status' => 'ACTIVE'],
        ];
    }
}
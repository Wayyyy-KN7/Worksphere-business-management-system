<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Department;
use App\Models\LeaveBalance;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCreatedSalesAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_created_sales_accounts_match_seeded_roles_and_use_existing_workflows(): void
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@company.com')->firstOrFail();
        $department = Department::query()->where('code', 'SALES')->firstOrFail();
        $seededManager = User::query()->where('email', 'sales.manager@company.com')->firstOrFail();
        $seededEmployee = User::query()->where('email', 'mgmg@company.com')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.users.store'), $this->newUserData(
            $department,
            'SALES_MANAGER',
            'New Sales Manager',
            'new.sales.manager@example.test',
        ))->assertRedirect(route('admin.users.index'));

        $this->post(route('admin.users.store'), $this->newUserData(
            $department,
            'SALES_EMPLOYEE',
            'New Sales Employee',
            'new.sales.employee@example.test',
        ))->assertRedirect(route('admin.users.index'));

        $manager = User::query()->where('email', 'new.sales.manager@example.test')->firstOrFail();
        $employee = User::query()->where('email', 'new.sales.employee@example.test')->firstOrFail();

        $this->assertSame([$seededManager->role, $seededManager->department_id, $seededManager->status], [
            $manager->role,
            $manager->department_id,
            $manager->status,
        ]);
        $this->assertSame([$seededEmployee->role, $seededEmployee->department_id, $seededEmployee->status], [
            $employee->role,
            $employee->department_id,
            $employee->status,
        ]);
        $this->assertTrue($manager->isManager());
        $this->assertTrue($manager->isDepartmentManager());
        $this->assertTrue($manager->isSales());
        $this->assertTrue($employee->isSales());
        $this->assertFalse($employee->isManager());
        $this->assertSame($department->id, $employee->department->id);
        $this->assertGreaterThan(0, LeaveBalance::query()->where('user_id', $employee->id)->count());

        $customer = Customer::create([
            'customer_code' => 'CUS-NEW-SALES-001',
            'name' => 'New Account Workflow Customer',
            'assigned_to' => $employee->id,
            'status' => 'ACTIVE',
        ]);
        $sale = Sale::create([
            'sale_number' => 'SALE-NEW-SALES-001',
            'customer_id' => $customer->id,
            'sale_date' => today()->toDateString(),
            'total_amount' => 100,
            'final_amount' => 100,
            'payment_status' => 'PENDING',
            'assigned_to' => $employee->id,
        ]);

        $this->actingAs($employee)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('data', fn (array $data) => array_key_exists('myCustomers', $data));
        $this->get(route('employee.attendance.index'))->assertOk();
        $this->get(route('employee.leave.index'))->assertOk();
        $this->get(route('crm.customers.index'))->assertOk()->assertSee($customer->name);
        $this->get(route('documents.my'))->assertOk();

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('data', fn (array $data) => $data['managerDashboard']['departmentName'] === $department->name);
        $this->get(route('crm.sales.index'))->assertOk()->assertSee($sale->sale_number);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login'), [
            'email' => $employee->email,
            'password' => 'new-account-password',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($employee);
    }

    public function test_admin_cannot_create_or_update_account_with_role_from_another_department(): void
    {
        $sales = Department::create(['name' => 'Sales', 'code' => 'SALES', 'status' => 'ACTIVE']);
        $hr = Department::create(['name' => 'Human Resources', 'code' => 'HR', 'status' => 'ACTIVE']);
        $admin = User::create([
            'employee_id' => 'ADMIN-ROLE-TEST',
            'name' => 'Test Admin',
            'email' => 'role.validation.admin@example.test',
            'password' => bcrypt('password'),
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($admin)->post(route('admin.users.store'), $this->newUserData(
            $sales,
            'HR_MANAGER',
            'Mismatched Manager',
            'mismatched.manager@example.test',
        ))->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'mismatched.manager@example.test']);

        $employee = User::create([
            'employee_id' => 'EMP-ROLE-TEST',
            'name' => 'HR Employee',
            'email' => 'role.validation.employee@example.test',
            'password' => bcrypt('password'),
            'department_id' => $hr->id,
            'role' => 'HR_EMPLOYEE',
            'status' => 'ACTIVE',
        ]);

        $this->put(route('admin.users.update', $employee), $this->newUserData(
            $sales,
            'HR_EMPLOYEE',
            $employee->name,
            $employee->email,
        ))->assertSessionHasErrors('role');

        $this->assertSame('HR_EMPLOYEE', $employee->fresh()->role);
        $this->assertSame($hr->id, $employee->fresh()->department_id);
    }

    private function newUserData(Department $department, string $role, string $name, string $email): array
    {
        return [
            'name' => $name,
            'email' => $email,
            'password' => 'new-account-password',
            'password_confirmation' => 'new-account-password',
            'department_id' => $department->id,
            'role' => $role,
            'joining_date' => today()->toDateString(),
            'status' => 'ACTIVE',
        ];
    }
}

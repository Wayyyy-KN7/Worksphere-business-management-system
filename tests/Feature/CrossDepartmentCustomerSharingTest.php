<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossDepartmentCustomerSharingTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_lead_is_shared_and_assigned_to_sales_without_creating_a_second_customer(): void
    {
        [$marketingDepartment, $marketer] = $this->departmentUser('Marketing', 'MARKETING', 'MARKETING_EMPLOYEE', 'marketing@example.test');
        [$salesDepartment, $salesperson] = $this->departmentUser('Sales', 'SALES', 'SALES_EMPLOYEE', 'sales@example.test');
        $salesManager = $this->user($salesDepartment, 'SALES_MANAGER', 'sales.manager@example.test');

        $this->actingAs($marketer)->post(route('crm.customers.store'), [
            'name' => 'Shared lead',
            'email' => 'lead@example.test',
            'phone' => '555-0100',
            'status' => 'LEAD',
        ])->assertRedirect(route('crm.customers.index'));

        $customer = Customer::query()->where('name', 'Shared lead')->firstOrFail();
        $this->assertSame($marketer->id, $customer->created_by);
        $this->assertSame($marketingDepartment->id, $customer->created_department_id);
        $this->assertSame($marketingDepartment->id, $customer->assigned_department_id);
        $this->assertSame($marketer->id, $customer->assigned_to);

        $salesCustomerList = $this->actingAs($salesperson)->get(route('crm.customers.index'))
            ->assertOk()
            ->assertSee($customer->customer_code)
            ->assertSee($marketer->name);
        $this->assertSame(1, substr_count(
            $salesCustomerList->getContent(),
            '<strong>' . $customer->customer_code . '</strong>',
        ));
        $this->get(route('crm.customers.show', $customer))->assertOk();
        $this->get(route('crm.customers.edit', $customer))->assertForbidden();
        $this->post(route('crm.opportunities.store'), [
            'title' => 'Premature opportunity',
            'customer_id' => $customer->id,
            'estimated_value' => 1000,
            'probability' => 50,
            'expected_close_date' => today()->addMonth()->toDateString(),
            'stage' => 'NEW',
            'status' => 'OPEN',
            'assigned_to' => $salesperson->id,
        ])->assertForbidden();
        $this->assertDatabaseMissing('opportunities', ['title' => 'Premature opportunity']);

        $this->actingAs($salesManager)->get(route('crm.customers.index'))
            ->assertOk()
            ->assertSee($customer->customer_code);

        $this->actingAs($marketer)->put(route('crm.customers.update', $customer), [
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'status' => 'LEAD',
            'assigned_department_id' => $salesDepartment->id,
            'assigned_to' => $salesperson->id,
        ])->assertRedirect(route('crm.customers.index'));

        $customer->refresh();
        $this->assertSame($marketer->id, $customer->created_by);
        $this->assertSame($marketingDepartment->id, $customer->created_department_id);
        $this->assertSame($salesDepartment->id, $customer->assigned_department_id);
        $this->assertSame($salesperson->id, $customer->assigned_to);
        $this->assertSame(1, Customer::query()->where('email', 'lead@example.test')->count());
        $this->assertDatabaseHas('audit_logs', [
            'table_name' => 'customers',
            'record_id' => $customer->id,
            'action' => 'UPDATED',
            'description' => "Customer reassigned: {$customer->name} ({$customer->customer_code})",
        ]);
        $this->assertSame(1, AuditLog::query()
            ->where('table_name', 'customers')
            ->where('record_id', $customer->id)
            ->where('description', "Customer reassigned: {$customer->name} ({$customer->customer_code})")
            ->count());

        $this->actingAs($salesperson)->get(route('crm.customers.index'))
            ->assertOk()
            ->assertSee($customer->customer_code);
        $this->get(route('crm.customers.edit', $customer))->assertOk();

        $this->post(route('crm.opportunities.store'), [
            'title' => 'Sales opportunity for shared lead',
            'customer_id' => $customer->id,
            'estimated_value' => 1000,
            'probability' => 50,
            'expected_close_date' => today()->addMonth()->toDateString(),
            'stage' => 'NEW',
            'status' => 'OPEN',
            'assigned_to' => $salesperson->id,
        ])->assertRedirect(route('crm.opportunities.index'));

        $this->assertDatabaseHas('opportunities', [
            'customer_id' => $customer->id,
            'title' => 'Sales opportunity for shared lead',
        ]);
    }

    public function test_customer_assignment_and_direct_access_remain_department_scoped(): void
    {
        [, $marketer] = $this->departmentUser('Marketing', 'MARKETING', 'MARKETING_EMPLOYEE', 'marketing.access@example.test');
        [$salesDepartment, $salesperson] = $this->departmentUser('Sales', 'SALES', 'SALES_EMPLOYEE', 'sales.access@example.test');
        [$hrDepartment, $hrEmployee] = $this->departmentUser('Human Resources', 'HR', 'HR_EMPLOYEE', 'hr.access@example.test');
        $customer = Customer::create([
            'customer_code' => 'CUST-CROSS-ACCESS',
            'name' => 'Protected lead',
            'created_by' => $marketer->id,
            'created_department_id' => $marketer->department_id,
            'assigned_department_id' => $salesDepartment->id,
            'assigned_to' => $salesperson->id,
            'status' => 'LEAD',
        ]);

        $this->actingAs($hrEmployee)->get(route('crm.customers.show', $customer))->assertForbidden();
        $this->put(route('crm.customers.update', $customer), [])->assertForbidden();

        $this->actingAs($salesperson)->put(route('crm.customers.update', $customer), [
            'name' => $customer->name,
            'status' => 'LEAD',
            'assigned_department_id' => $hrDepartment->id,
            'assigned_to' => $hrEmployee->id,
        ])->assertForbidden();

        $this->assertSame($salesDepartment->id, $customer->fresh()->assigned_department_id);
        $this->assertSame($salesperson->id, $customer->fresh()->assigned_to);
    }

    private function departmentUser(
        string $name,
        string $code,
        string $role,
        string $email,
    ): array {
        $department = Department::create(['name' => $name, 'code' => $code, 'status' => 'ACTIVE']);

        return [$department, $this->user($department, $role, $email)];
    }

    private function user(Department $department, string $role, string $email): User
    {
        return User::create([
            'employee_id' => strtoupper(str_replace(['@', '.', '-'], '', $email)),
            'name' => $role,
            'email' => $email,
            'password' => bcrypt('password'),
            'department_id' => $department->id,
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }
}

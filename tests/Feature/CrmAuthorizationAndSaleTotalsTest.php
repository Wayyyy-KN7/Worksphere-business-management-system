<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Department;
use App\Models\Opportunity;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmAuthorizationAndSaleTotalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_only_assign_sales_to_active_sales_employees_or_managers(): void
    {
        [$department, $employee, $manager] = $this->salesUsers();
        $admin = $this->user($department, 'ADMIN', 'crm.admin@example.test');
        $otherAdmin = $this->user($department, 'ADMIN', 'crm.other-admin@example.test');
        $inactiveEmployee = $this->user($department, 'SALES_EMPLOYEE', 'crm.inactive@example.test');
        $inactiveEmployee->update(['status' => 'INACTIVE']);
        $customer = $this->customer($employee, 'CUS-ADMIN-ASSIGN-001');

        $this->actingAs($admin)->get(route('crm.sales.create'))
            ->assertOk()
            ->assertDontSee('Assign to me (ADMIN)')
            ->assertSee($employee->name)
            ->assertSee($manager->name)
            ->assertViewHas('assignableUsers', fn ($users) => $users->contains('id', $employee->id)
                && $users->contains('id', $manager->id)
                && !$users->contains('id', $admin->id)
                && !$users->contains('id', $otherAdmin->id)
                && !$users->contains('id', $inactiveEmployee->id));

        $saleData = [
            'customer_id' => $customer->id,
            'sale_date' => today()->toDateString(),
            'payment_status' => 'PENDING',
            'items' => [[
                'product_name' => 'Consulting service',
                'quantity' => 1,
                'unit_price' => 100,
            ]],
        ];

        $this->post(route('crm.sales.store'), $saleData)
            ->assertSessionHasErrors('assigned_to');

        $this->post(route('crm.sales.store'), $saleData + ['assigned_to' => $admin->id])
            ->assertSessionHasErrors('assigned_to');

        $this->post(route('crm.sales.store'), $saleData + ['assigned_to' => $otherAdmin->id])
            ->assertSessionHasErrors('assigned_to');

        $this->post(route('crm.sales.store'), $saleData + ['assigned_to' => $inactiveEmployee->id])
            ->assertSessionHasErrors('assigned_to');

        $this->post(route('crm.sales.store'), $saleData + ['assigned_to' => $manager->id])
            ->assertRedirect(route('crm.sales.index'));

        $this->assertDatabaseHas('sales', [
            'customer_id' => $customer->id,
            'assigned_to' => $manager->id,
        ]);

        $sale = Sale::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->get(route('crm.sales.edit', $sale))
            ->assertOk()
            ->assertViewHas('assignableUsers', fn ($users) => $users->contains('id', $employee->id)
                && $users->contains('id', $manager->id)
                && !$users->contains('id', $admin->id)
                && !$users->contains('id', $otherAdmin->id)
                && !$users->contains('id', $inactiveEmployee->id));

        $updateData = $saleData + ['assigned_to' => $employee->id];
        $this->put(route('crm.sales.update', $sale), $updateData)
            ->assertRedirect(route('crm.sales.index'));
        $this->assertSame($employee->id, $sale->fresh()->assigned_to);

        foreach ([$admin, $otherAdmin, $inactiveEmployee] as $invalidAssignee) {
            $this->put(route('crm.sales.update', $sale), array_replace($updateData, ['assigned_to' => $invalidAssignee->id]))
                ->assertSessionHasErrors('assigned_to');
        }

        $this->assertSame($employee->id, $sale->fresh()->assigned_to);
    }

    public function test_sales_records_reject_cross_department_direct_access_and_updates(): void
    {
        [$salesDepartment, $employee, $manager] = $this->salesUsers();
        [$otherDepartment, $outsider] = $this->otherDepartmentEmployee();
        $customer = $this->customer($employee, 'CUS-ACCESS-001');
        $opportunity = Opportunity::create([
            'opportunity_number' => 'OPP-ACCESS-001',
            'customer_id' => $customer->id,
            'title' => 'Protected opportunity',
            'estimated_value' => 500,
            'probability' => 50,
            'expected_close_date' => today()->addMonth()->toDateString(),
            'stage' => 'QUALIFIED',
            'status' => 'OPEN',
            'assigned_to' => $employee->id,
        ]);
        $sale = Sale::create([
            'sale_number' => 'SALE-ACCESS-001',
            'customer_id' => $customer->id,
            'opportunity_id' => $opportunity->id,
            'sale_date' => today()->toDateString(),
            'total_amount' => 100,
            'final_amount' => 100,
            'payment_status' => 'PENDING',
            'assigned_to' => $employee->id,
        ]);

        $this->actingAs($outsider)->get(route('crm.opportunities.show', $opportunity))->assertForbidden();
        $this->put(route('crm.opportunities.updateStage', $opportunity), ['stage' => 'WON'])->assertForbidden();
        $this->delete(route('crm.opportunities.destroy', $opportunity))->assertForbidden();
        $this->get(route('crm.customers.show', $customer))->assertForbidden();
        $this->put(route('crm.customers.update', $customer), [])->assertForbidden();
        $this->get(route('crm.sales.show', $sale))->assertForbidden();
        $this->get(route('crm.sales.invoice', $sale))->assertForbidden();
        $this->get(route('crm.sales.edit', $sale))->assertForbidden();
        $this->put(route('crm.sales.update', $sale), [])->assertForbidden();
        $this->delete(route('crm.sales.destroy', $sale))->assertForbidden();

        $this->assertSame('OPEN', $opportunity->fresh()->status);
        $this->assertDatabaseHas('sales', ['id' => $sale->id]);
        $this->assertNotSame($salesDepartment->id, $otherDepartment->id);
        $this->actingAs($manager)->get(route('crm.opportunities.show', $opportunity))->assertOk();

        $otherCustomer = $this->customer($outsider, 'CUS-ACCESS-002');
        $this->actingAs($manager)->post(route('crm.opportunities.store'), [
            'title' => 'Cross-department opportunity',
            'customer_id' => $otherCustomer->id,
            'estimated_value' => 500,
            'probability' => 50,
            'expected_close_date' => today()->addMonth()->toDateString(),
            'stage' => 'NEW',
            'status' => 'OPEN',
            'assigned_to' => $employee->id,
        ])->assertForbidden();
        $this->assertDatabaseMissing('opportunities', ['title' => 'Cross-department opportunity']);
    }

    public function test_sales_employee_cannot_assign_customers_to_users_outside_their_department(): void
    {
        [, $employee] = $this->salesUsers();
        [, $outsider] = $this->otherDepartmentEmployee();

        $this->actingAs($employee)->post(route('crm.customers.store'), [
            'name' => 'Unauthorized assignment',
            'assigned_to' => $outsider->id,
            'status' => 'ACTIVE',
        ])->assertForbidden();

        $this->assertDatabaseMissing('customers', ['name' => 'Unauthorized assignment']);
    }

    public function test_sale_totals_are_derived_from_line_items_on_create_and_update(): void
    {
        [, $employee, $manager] = $this->salesUsers();
        $customer = $this->customer($employee, 'CUS-TOTALS-001');

        $this->actingAs($employee)->post(route('crm.sales.store'), [
            'customer_id' => $customer->id,
            'sale_date' => today()->toDateString(),
            'payment_status' => 'PENDING',
            'subtotal' => 0.01,
            'discount_amount' => 9999,
            'tax_amount' => 9999,
            'final_amount' => 0,
            'items' => [[
                'product_name' => 'Initial service',
                'quantity' => 2,
                'unit_price' => 100,
                'discount' => 10,
                'tax' => 10,
            ]],
        ])->assertRedirect(route('crm.sales.index'));

        $sale = Sale::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame(200.0, (float) $sale->total_amount);
        $this->assertSame(20.0, (float) $sale->discount_amount);
        $this->assertSame(18.0, (float) $sale->tax_amount);
        $this->assertSame(198.0, (float) $sale->final_amount);
        $this->assertSame(198.0, (float) $sale->items()->sum('total'));

        $this->actingAs($manager)->put(route('crm.sales.update', $sale), [
            'customer_id' => $customer->id,
            'assigned_to' => $employee->id,
            'sale_date' => today()->toDateString(),
            'payment_status' => 'PARTIAL',
            'subtotal' => 1,
            'discount_amount' => 1,
            'tax_amount' => 1,
            'final_amount' => 1,
            'items' => [
                [
                    'product_name' => 'Retained service',
                    'quantity' => 2,
                    'unit_price' => 100,
                    'discount' => 5,
                    'tax' => 8,
                ],
                [
                    'product_name' => 'Added service',
                    'quantity' => 1,
                    'unit_price' => 50,
                    'discount' => 0,
                    'tax' => 0,
                ],
            ],
        ])->assertRedirect(route('crm.sales.index'));

        $sale->refresh();
        $this->assertSame(250.0, (float) $sale->total_amount);
        $this->assertSame(10.0, (float) $sale->discount_amount);
        $this->assertSame(15.2, (float) $sale->tax_amount);
        $this->assertSame(255.2, (float) $sale->final_amount);
        $this->assertSame(2, $sale->items()->count());

        $this->put(route('crm.sales.update', $sale), [
            'customer_id' => $customer->id,
            'assigned_to' => $employee->id,
            'sale_date' => today()->toDateString(),
            'payment_status' => 'PARTIAL',
            'items' => [[
                'product_name' => 'Replacement service',
                'quantity' => 1,
                'unit_price' => 40,
                'discount' => 0,
                'tax' => 0,
            ]],
        ])->assertRedirect(route('crm.sales.index'));

        $sale->refresh();
        $this->assertSame(40.0, (float) $sale->total_amount);
        $this->assertSame(40.0, (float) $sale->final_amount);
        $this->assertSame(1, $sale->items()->count());
        $this->assertSame(40.0, (float) SaleItem::query()->where('sale_id', $sale->id)->sum('total'));
    }

    private function salesUsers(): array
    {
        $department = Department::create(['name' => 'Sales', 'code' => 'SALES', 'status' => 'ACTIVE']);
        $employee = $this->user($department, 'SALES_EMPLOYEE', 'crm.employee@example.test');
        $manager = $this->user($department, 'SALES_MANAGER', 'crm.manager@example.test');

        return [$department, $employee, $manager];
    }

    private function otherDepartmentEmployee(): array
    {
        $department = Department::create(['name' => 'Human Resources', 'code' => 'HR', 'status' => 'ACTIVE']);
        return [$department, $this->user($department, 'HR_EMPLOYEE', 'crm.hr@example.test')];
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

    private function customer(User $owner, string $code): Customer
    {
        return Customer::create([
            'customer_code' => $code,
            'name' => $code,
            'assigned_to' => $owner->id,
            'status' => 'ACTIVE',
        ]);
    }
}

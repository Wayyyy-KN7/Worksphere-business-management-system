<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CrmActivityHistory;
use App\Models\Customer;
use App\Models\Department;
use App\Models\FollowUp;
use App\Models\Opportunity;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerArchiveWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_archiving_preserves_customer_relationships_and_restore_returns_it_to_active_list(): void
    {
        [$department, $owner] = $this->salesEmployee('archive.owner@example.com', 'EMP-ARCHIVE-OWNER');
        $otherUser = $this->salesEmployee('archive.other@example.com', 'EMP-ARCHIVE-OTHER')[1];
        $customer = $this->customer($owner);
        $opportunity = Opportunity::create([
            'opportunity_number' => 'OPP-ARCHIVE-001',
            'customer_id' => $customer->id,
            'title' => 'Archive regression opportunity',
            'estimated_value' => 1250,
            'probability' => 50,
            'expected_close_date' => today()->addMonth()->toDateString(),
            'stage' => 'QUALIFIED',
            'status' => 'OPEN',
            'assigned_to' => $owner->id,
        ]);
        $followUp = FollowUp::create([
            'customer_id' => $customer->id,
            'opportunity_id' => $opportunity->id,
            'title' => 'Archive regression follow-up',
            'follow_up_date' => today()->addDay()->toDateString(),
            'follow_up_time' => '10:00',
            'type' => 'CALL',
            'priority' => 'NORMAL',
            'status' => 'PLANNED',
            'assigned_to' => $owner->id,
            'created_by' => $owner->id,
            'recurrence_rule' => 'NONE',
        ]);
        $activity = $followUp->histories()->create([
            'changed_by' => $owner->id,
            'action' => 'CREATED',
            'to_status' => 'PLANNED',
            'notes' => 'Retain this activity when its customer is archived.',
        ]);
        $sale = Sale::create([
            'sale_number' => 'SALE-ARCHIVE-001',
            'customer_id' => $customer->id,
            'opportunity_id' => $opportunity->id,
            'sale_date' => today()->toDateString(),
            'total_amount' => 1250,
            'final_amount' => 1250,
            'payment_status' => 'PAID',
            'assigned_to' => $owner->id,
        ]);

        $this->actingAs($owner)->get(route('crm.customers.index'))
            ->assertOk()
            ->assertSee($customer->customer_code);

        $this->delete(route('crm.customers.destroy', $customer))
            ->assertRedirect(route('crm.customers.index'))
            ->assertSessionHas('success', 'Customer archived successfully. Related records have been retained.');

        $archivedCustomer = Customer::withTrashed()->findOrFail($customer->id);
        $this->assertNotNull($archivedCustomer->deleted_at);
        $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'customer_id' => $customer->id]);
        $this->assertDatabaseHas('follow_ups', ['id' => $followUp->id, 'customer_id' => $customer->id]);
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'customer_id' => $customer->id]);
        $this->assertDatabaseHas('crm_activity_histories', [
            'id' => $activity->id,
            'activity_type' => FollowUp::class,
            'activity_id' => $followUp->id,
        ]);

        $this->get(route('crm.customers.index'))->assertOk()->assertDontSee($customer->customer_code);
        $this->get(route('crm.customers.archived'))->assertOk()->assertSee($customer->customer_code);
        $this->get(route('crm.customers.show', $customer))->assertOk()->assertSee('Restore Customer');
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'ARCHIVED',
            'table_name' => 'customers',
            'record_id' => $customer->id,
            'description' => 'Archived customer: ' . $customer->name . ' (' . $customer->customer_code . ')',
        ]);

        $this->post(route('crm.customers.restore', $customer->id))
            ->assertRedirect(route('crm.customers.archived'))
            ->assertSessionHas('success', 'Customer restored successfully.');

        $this->assertNull($customer->fresh()->deleted_at);
        $this->get(route('crm.customers.index'))->assertOk()->assertSee($customer->customer_code);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'RESTORED',
            'table_name' => 'customers',
            'record_id' => $customer->id,
            'description' => 'Restored customer: ' . $customer->name . ' (' . $customer->customer_code . ')',
        ]);
        $this->assertSame(2, AuditLog::where('record_id', $customer->id)->where('table_name', 'customers')->count());
        $this->assertNotSame($owner->id, $otherUser->id);
    }

    public function test_only_users_authorized_for_the_customer_can_archive_or_restore_it(): void
    {
        [$ownerDepartment, $owner] = $this->salesEmployee('archive.permission.owner@example.com', 'EMP-ARCHIVE-PERM-OWNER');
        [$otherDepartment, $outsider] = $this->employeeInOtherDepartment();
        $customer = $this->customer($owner);

        $this->actingAs($outsider)->delete(route('crm.customers.destroy', $customer))->assertForbidden();
        $this->assertNull($customer->fresh()->deleted_at);

        $this->actingAs($owner)->delete(route('crm.customers.destroy', $customer))->assertRedirect();
        $this->actingAs($outsider)->post(route('crm.customers.restore', $customer->id))->assertForbidden();

        $this->assertNotNull($customer->fresh()->deleted_at);
        $this->assertNotSame($ownerDepartment->id, $otherDepartment->id);
    }

    public function test_sales_employee_can_view_archived_customer_archived_by_department_manager(): void
    {
        [$department, $employee] = $this->salesEmployee('archive.team.employee@example.com', 'EMP-ARCHIVE-TEAM');
        $manager = User::create([
            'employee_id' => 'EMP-ARCHIVE-TEAM-MANAGER',
            'name' => 'Sales Manager',
            'email' => 'archive.team.manager@example.com',
            'password' => bcrypt('password'),
            'department_id' => $department->id,
            'role' => 'SALES_MANAGER',
            'status' => 'ACTIVE',
        ]);
        $customer = $this->customer($manager);

        $this->actingAs($manager)->delete(route('crm.customers.destroy', $customer))
            ->assertRedirect(route('crm.customers.index'));

        $this->actingAs($employee)->get(route('crm.customers.archived'))
            ->assertOk()
            ->assertSee($customer->customer_code)
            ->assertSee($customer->name);
        $this->get(route('crm.customers.show', $customer))
            ->assertOk()
            ->assertSee($customer->name)
            ->assertSee('Archived');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'assigned_to' => $manager->id,
        ]);
    }

    public function test_customer_creation_skips_codes_owned_by_archived_customers(): void
    {
        [, $owner] = $this->salesEmployee('customer.code.owner@example.com', 'EMP-CUSTOMER-CODE');
        $year = now()->format('y');
        $archivedCustomer = Customer::create([
            'customer_code' => "CUST-{$year}-0001",
            'name' => 'Archived customer',
            'assigned_to' => $owner->id,
            'status' => 'ACTIVE',
        ]);
        $archivedCustomer->delete();

        $this->actingAs($owner)
            ->post(route('crm.customers.store'), [
                'name' => 'New customer',
                'company_name' => 'New Customer Ltd',
                'status' => 'PROSPECT',
            ])
            ->assertRedirect(route('crm.customers.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customers', [
            'name' => 'New customer',
            'customer_code' => "CUST-{$year}-0002",
        ]);
        $this->assertSame("CUST-{$year}-0001", $archivedCustomer->fresh()->customer_code);
    }

    private function salesEmployee(string $email, string $employeeId): array
    {
        $department = Department::firstOrCreate(['code' => 'SALES'], [
            'name' => 'Sales',
            'status' => 'ACTIVE',
        ]);
        $user = User::create([
            'employee_id' => $employeeId,
            'name' => ucfirst(strstr($email, '@', true)),
            'email' => $email,
            'password' => bcrypt('password'),
            'department_id' => $department->id,
            'role' => 'SALES_EMPLOYEE',
            'status' => 'ACTIVE',
        ]);

        return [$department, $user];
    }

    private function employeeInOtherDepartment(): array
    {
        $department = Department::create([
            'name' => 'HR',
            'code' => 'HR-ARCHIVE',
            'status' => 'ACTIVE',
        ]);
        $user = User::create([
            'employee_id' => 'EMP-ARCHIVE-OUTSIDER',
            'name' => 'Archive Outsider',
            'email' => 'archive.outsider@example.com',
            'password' => bcrypt('password'),
            'department_id' => $department->id,
            'role' => 'HR_EMPLOYEE',
            'status' => 'ACTIVE',
        ]);

        return [$department, $user];
    }

    private function customer(User $owner): Customer
    {
        return Customer::create([
            'customer_code' => 'CUS-' . strtoupper(substr(md5($owner->email), 0, 8)),
            'name' => 'Customer ' . $owner->name,
            'company_name' => 'Archive Retention Co.',
            'assigned_to' => $owner->id,
            'status' => 'ACTIVE',
        ]);
    }
}

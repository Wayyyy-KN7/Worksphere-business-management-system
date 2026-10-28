<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_existing_audit_records_and_field_diffs(): void
    {
        $department = Department::create(['name' => 'Sales', 'code' => 'SALES', 'status' => 'ACTIVE']);
        $admin = $this->user('ADMIN', 'audit.admin@example.test');
        $actor = $this->user('SALES_MANAGER', 'audit.actor@example.test', $department);
        $auditLog = AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'UPDATED',
            'table_name' => 'customers',
            'module' => 'Customers',
            'record_id' => 42,
            'description' => 'Updated customer contact information.',
            'ip_address' => '192.0.2.10',
            'old_values' => [
                'name' => 'Northwind',
                'phone' => '111-111',
                'legacy_code' => 'OLD',
            ],
            'new_values' => [
                'name' => 'Northwind Trading',
                'phone' => '222-222',
                'status' => 'ACTIVE',
            ],
        ]);

        $this->actingAs($admin)->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('Audit Logs')
            ->assertSee($actor->name)
            ->assertSee('Updated customer contact information.')
            ->assertSee('Customers')
            ->assertSee('#42')
            ->assertSee(route('admin.audit-logs.show', $auditLog), false)
            ->assertSee('Details', false);

        $this->get(route('admin.audit-logs.show', $auditLog))
            ->assertOk()
            ->assertJsonPath('id', $auditLog->id)
            ->assertJsonPath('actor', $actor->name)
            ->assertJsonPath('changes.0.field', 'name')
            ->assertJsonPath('changes.0.type', 'changed')
            ->assertJsonPath('changes.0.before', 'Northwind')
            ->assertJsonPath('changes.0.after', 'Northwind Trading')
            ->assertJsonFragment(['field' => 'legacy_code', 'type' => 'removed', 'before' => 'OLD', 'after' => null])
            ->assertJsonFragment(['field' => 'status', 'type' => 'added', 'before' => null, 'after' => 'ACTIVE']);
    }

    public function test_managers_and_employees_are_denied_from_audit_page_and_detail_endpoint(): void
    {
        $department = Department::create(['name' => 'Sales', 'code' => 'SALES', 'status' => 'ACTIVE']);
        $manager = $this->user('SALES_MANAGER', 'audit.manager@example.test', $department);
        $employee = $this->user('SALES_EMPLOYEE', 'audit.employee@example.test', $department);
        $auditLog = AuditLog::create([
            'user_id' => $employee->id,
            'action' => 'OTHER',
            'table_name' => 'customers',
            'record_id' => 1,
            'description' => 'Confidential audit row.',
        ]);

        $this->actingAs($manager)->get(route('admin.audit-logs.index'))->assertForbidden();
        $this->get(route('admin.audit-logs.show', $auditLog))->assertForbidden();
        $this->actingAs($employee)->get(route('admin.audit-logs.index'))->assertForbidden();
        $this->get(route('admin.audit-logs.show', $auditLog))->assertForbidden();
    }

    private function user(string $role, string $email, ?Department $department = null): User
    {
        return User::create([
            'employee_id' => strtoupper(substr(md5($email), 0, 10)),
            'name' => ucfirst(strstr($email, '@', true)),
            'email' => $email,
            'password' => bcrypt('password'),
            'department_id' => $department?->id,
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }
}

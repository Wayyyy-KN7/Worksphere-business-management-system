<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_be_updated_without_changing_the_password(): void
    {
        [$admin, $employee] = $this->createAdminAndEmployee();
        $originalPassword = $employee->password;

        $this->actingAs($admin)->put(route('admin.users.update', $employee), $this->employeeUpdateData($employee))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame($originalPassword, $employee->fresh()->password);
        $this->assertSame('ACTIVE', $employee->fresh()->status);
    }

    public function test_password_changes_only_when_explicitly_selected(): void
    {
        [$admin, $employee] = $this->createAdminAndEmployee();
        $data = $this->employeeUpdateData($employee) + [
            'change_password' => '1',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ];

        $this->actingAs($admin)->put(route('admin.users.update', $employee), $data)
            ->assertRedirect(route('admin.users.index'));

        $this->assertNotSame($employee->password, $employee->fresh()->password);
        $this->assertTrue(password_verify('new-password-123', $employee->fresh()->password));
    }

    public function test_password_change_requires_a_confirmed_new_password(): void
    {
        [$admin, $employee] = $this->createAdminAndEmployee();
        $data = $this->employeeUpdateData($employee) + ['change_password' => '1'];

        $this->actingAs($admin)->put(route('admin.users.update', $employee), $data)
            ->assertSessionHasErrors('password');
    }

    public function test_employee_update_success_message_auto_dismisses_after_two_seconds(): void
    {
        [$admin, $employee] = $this->createAdminAndEmployee();

        $this->actingAs($admin)->put(route('admin.users.update', $employee), $this->employeeUpdateData($employee))
            ->assertRedirect(route('admin.users.index'));

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('data-auto-dismiss="2000"', false);
    }

    private function createAdminAndEmployee(): array
    {
        $department = Department::create([
            'name' => 'Sales',
            'code' => 'SALES',
            'status' => 'ACTIVE',
        ]);

        $admin = User::create([
            'employee_id' => strtoupper(Str::random(10)),
            'name' => 'Admin User',
            'email' => 'update.admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);

        $employee = User::create([
            'employee_id' => strtoupper(Str::random(10)),
            'name' => 'Sales Employee',
            'email' => 'update.employee@example.com',
            'password' => bcrypt('original-password'),
            'department_id' => $department->id,
            'role' => 'SALES_EMPLOYEE',
            'position' => 'Sales Executive',
            'joining_date' => '2022-01-01',
            'status' => 'ACTIVE',
        ]);

        return [$admin, $employee];
    }

    private function employeeUpdateData(User $employee): array
    {
        return [
            'name' => $employee->name,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'department_id' => $employee->department_id,
            'role' => $employee->role,
            'position' => $employee->position,
            'joining_date' => $employee->joining_date->format('Y-m-d'),
            'status' => $employee->status,
        ];
    }
}
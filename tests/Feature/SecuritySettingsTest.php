<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CompanySettings;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecuritySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_protection_settings_are_admin_only_and_exclude_removed_configuration(): void
    {
        $admin = $this->user('ADMIN', 'security.admin@example.com');
        $department = $this->department();
        $employee = $this->user('SALES_EMPLOYEE', 'security.employee@example.com', $department);

        $this->actingAs($admin)->get(route('admin.settings.security'))
            ->assertOk()
            ->assertSee('Login Protection')
            ->assertSee('Maximum failed sign-in attempts')
            ->assertSee('Inactive session timeout')
            ->assertDontSee('Password Policy')
            ->assertDontSee('Audit &amp; Monitoring');

        $this->put(route('admin.settings.security.update'), [
            'max_failed_login_attempts' => 4,
            'account_lockout_minutes' => 20,
            'session_timeout_minutes' => 45,
        ])->assertRedirect(route('admin.settings.security'));

        $settings = CompanySettings::query()->findOrFail(1);
        $this->assertSame(4, $settings->max_failed_login_attempts);
        $this->assertSame(20, $settings->account_lockout_minutes);
        $this->assertSame(45, $settings->session_timeout_minutes);

        $this->actingAs($employee)->get(route('admin.settings.security'))->assertForbidden();
        $this->put(route('admin.settings.security.update'), [])->assertForbidden();
    }

    public function test_admin_created_and_changed_passwords_keep_baseline_minimum_and_hashing(): void
    {
        $admin = $this->user('ADMIN', 'password.policy.admin@example.com');
        $department = $this->department();
        $employee = $this->user('SALES_EMPLOYEE', 'password.policy.employee@example.com', $department);

        $this->actingAs($admin)->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('Use at least 8 characters.')
            ->assertDontSee('Password Policy');

        $this->post(route('admin.users.store'), [
            'name' => 'New Employee',
            'email' => 'password.policy.new@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'department_id' => $department->id,
            'role' => 'SALES_EMPLOYEE',
            'joining_date' => '2024-01-01',
            'status' => 'ACTIVE',
        ])->assertSessionHasErrors('password');

        $update = $this->employeeUpdateData($employee) + [
            'change_password' => '1',
            'password' => 'short',
            'password_confirmation' => 'short',
        ];
        $this->put(route('admin.users.update', $employee), $update)->assertSessionHasErrors('password');

        $update['password'] = 'SecurePassword';
        $update['password_confirmation'] = 'SecurePassword';
        $this->put(route('admin.users.update', $employee), $update)
            ->assertRedirect(route('admin.users.index'));

        $this->assertTrue(password_verify('SecurePassword', $employee->fresh()->password));
    }

    public function test_failed_login_lockout_uses_configured_attempts_and_duration(): void
    {
        $this->settings([
            'max_failed_login_attempts' => 2,
            'account_lockout_minutes' => 1,
        ]);
        $user = $this->user('SALES_EMPLOYEE', 'security.lockout@example.com', $this->department());

        foreach (range(1, 2) as $attempt) {
            $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertSessionHasErrors('email');
        }

        $lockedResponse = $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
        $lockedResponse->assertSessionHasErrors('email');
        $errors = $lockedResponse->getSession()->get('errors');
        $this->assertStringContainsString('Too many failed login attempts', $errors->getBag('default')->first('email'));
        $this->assertGuest();

        $this->travel(61)->seconds();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactivity_timeout_expires_an_authenticated_session(): void
    {
        $this->settings(['session_timeout_minutes' => 5]);
        $user = $this->user('SALES_EMPLOYEE', 'security.session@example.com', $this->department());

        $this->actingAs($user)
            ->withSession(['security_last_activity_at' => now()->subMinutes(6)->timestamp])
            ->get(route('profile.show'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_legacy_audit_toggle_cannot_disable_business_audit_logging(): void
    {
        $settings = $this->settings(['audit_logging_enabled' => false]);
        $admin = $this->user('ADMIN', 'security.audit@example.com');
        CompanySettings::query()->whereKey($settings->id)->update(['audit_logging_enabled' => false]);
        $this->actingAs($admin);

        AuditLog::log('UPDATED', 'users', $admin->id, 'Recorded regardless of legacy setting.');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'table_name' => 'users',
            'description' => 'Recorded regardless of legacy setting.',
        ]);
    }

    private function settings(array $overrides = []): CompanySettings
    {
        $settings = CompanySettings::query()->firstOrNew(['id' => 1], CompanySettings::defaults());
        $settings->fill(array_replace(CompanySettings::defaults(), $overrides));
        $settings->save();

        return $settings;
    }

    private function department(): Department
    {
        return Department::create([
            'name' => 'Sales',
            'code' => 'SALES',
            'status' => 'ACTIVE',
        ]);
    }

    private function user(string $role, string $email, ?Department $department = null): User
    {
        return User::create([
            'employee_id' => strtoupper(substr(md5($email), 0, 12)),
            'name' => ucfirst(strstr($email, '@', true)),
            'email' => $email,
            'password' => bcrypt('password'),
            'department_id' => $department?->id,
            'role' => $role,
            'position' => 'Test User',
            'joining_date' => '2024-01-01',
            'status' => 'ACTIVE',
        ]);
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
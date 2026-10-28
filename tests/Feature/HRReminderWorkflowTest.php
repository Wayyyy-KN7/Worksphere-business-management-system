<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class HRReminderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_activity_and_hr_reminder_routes_are_removed_without_removing_crm_follow_ups(): void
    {
        $this->assertFalse(Route::has('hr.activities.index'));
        $this->assertFalse(Route::has('hr.reminders.index'));
        $this->assertFalse(Route::has('hr.follow-ups.index'));
        $this->assertTrue(Route::has('crm.follow-ups.index'));
        $this->assertTrue(Route::has('crm.reminders.index'));

        $hrUser = $this->user($this->department('HR'), 'HR_MANAGER', 'removed.hr.features@example.com');
        $this->actingAs($hrUser)->get(route('crm.reminders.index'))->assertOk();
        $this->get(route('crm.follow-ups.index'))->assertOk();
    }

    public function test_legacy_hr_reminders_are_not_exposed_in_crm_reminders(): void
    {
        $hrUser = $this->user($this->department('HR'), 'HR_MANAGER', 'legacy.hr.reminder@example.com');
        DB::table('reminders')->insert([
            'user_id' => $hrUser->id,
            'employee_id' => $hrUser->id,
            'reminder_type' => 'PROBATION_REVIEW',
            'title' => 'Legacy HR-only reminder',
            'reminder_date' => today()->addDay()->toDateString(),
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($hrUser)->get(route('crm.reminders.index'))
            ->assertOk()
            ->assertDontSee('Legacy HR-only reminder');
    }

    private function department(string $code): Department
    {
        return Department::create(['name' => $code, 'code' => $code, 'status' => 'ACTIVE']);
    }

    private function user(Department $department, string $role, string $email): User
    {
        return User::create([
            'employee_id' => strtoupper(Str::random(10)),
            'name' => ucfirst(strstr($email, '@', true)),
            'email' => $email,
            'password' => bcrypt('password'),
            'department_id' => $department->id,
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }
}

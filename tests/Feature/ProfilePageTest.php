<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_profile_shows_current_information_without_admin_settings(): void
    {
        $user = $this->createUser('SALES_EMPLOYEE', 'Ma Su', 'Sales Executive', 'profile.employee@example.com');

        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Ma Su')
            ->assertSee('Information')
            ->assertSee('Employee ID')
            ->assertSee('Email')
            ->assertSee('Phone')
            ->assertSee('Department')
            ->assertSee('Position')
            ->assertSee('Joining Date')
            ->assertSee('Role')
            ->assertSee('Status')
            ->assertSee($user->initials)
            ->assertDontSee('Last Login')
            ->assertDontSee('Administrator settings')
            ->assertDontSee('System Settings');
    }

    public function test_manager_profile_does_not_show_admin_only_controls(): void
    {
        $manager = $this->createUser('SALES_MANAGER', 'Min Min', 'Sales Manager', 'profile.manager@example.com');

        $this->actingAs($manager)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Min Min')
            ->assertSee('Sales Department')
            ->assertDontSee('Last Login')
            ->assertDontSee('Account Settings')
            ->assertDontSee('System Settings');
    }

    public function test_admin_profile_uses_the_current_profile_design(): void
    {
        $admin = $this->createUser('ADMIN', 'Kaung Sett Naing', 'Administrator', 'profile.admin@example.com');
        $admin->update(['last_login' => now()]);

        $this->actingAs($admin)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Kaung Sett Naing')
            ->assertSee('Information')
            ->assertSee('Employee ID')
            ->assertSee('Role')
            ->assertSee('Status')
            ->assertDontSee('Last Login')
            ->assertDontSee('Account Settings')
            ->assertDontSee('System Settings')
            ->assertSee(route('admin.settings.index'));
    }

    public function test_user_can_upload_and_remove_profile_photo(): void
    {
        Storage::fake('public');
        $user = $this->createUser('SALES_EMPLOYEE', 'Ma Su', 'Sales Executive', 'profile.photo@example.com');

        $this->actingAs($user)->post(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->create('profile.png', 10, 'image/png'),
        ])->assertRedirect(route('profile.show'));

        $photoPath = $user->fresh()->profile_photo_path;
        $this->assertNotNull($photoPath);
        Storage::disk('public')->assertExists($photoPath);

        $this->get(route('profile.show'))
            ->assertOk()
            ->assertSee(asset('storage/' . $photoPath), false);

        $this->delete(route('profile.photo.destroy'))
            ->assertRedirect(route('profile.show'));

        $this->assertNull($user->fresh()->profile_photo_path);
        Storage::disk('public')->assertMissing($photoPath);
    }

    private function createUser(string $role, string $name, string $position, string $email): User
    {
        $department = Department::create([
            'name' => 'Sales Department',
            'code' => 'SALES',
            'status' => 'ACTIVE',
        ]);

        return User::create([
            'employee_id' => strtoupper(Str::random(10)),
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password'),
            'phone' => '09-123-456-789',
            'department_id' => $department->id,
            'role' => $role,
            'position' => $position,
            'joining_date' => '2022-03-15',
            'status' => 'ACTIVE',
        ]);
    }
}
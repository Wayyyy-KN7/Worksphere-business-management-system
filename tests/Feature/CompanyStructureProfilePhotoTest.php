<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyStructureProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_structure_shows_available_photos_and_initials_fallbacks_for_all_roles(): void
    {
        Storage::fake('public');

        $department = Department::create([
            'name' => 'Sales Department',
            'code' => 'SALES',
            'status' => 'ACTIVE',
        ]);
        $admin = $this->createUser($department, 'ADMIN', 'Alice Admin', 'admin@example.test');
        $manager = $this->createUser($department, 'SALES_MANAGER', 'Morgan Manager', 'manager@example.test');
        $employee = $this->createUser($department, 'SALES_EMPLOYEE', 'Evan Employee', 'employee@example.test');

        $availablePhotoPath = "profile-photos/{$admin->id}/available.jpg";
        $missingPhotoPath = "profile-photos/{$employee->id}/missing.jpg";
        Storage::disk('public')->put($availablePhotoPath, 'photo contents');
        $admin->update(['profile_photo_path' => $availablePhotoPath]);
        $employee->update(['profile_photo_path' => $missingPhotoPath]);
        $availablePhotoUrl = Storage::disk('public')->url($availablePhotoPath);

        foreach ([$admin, $manager, $employee] as $viewer) {
            $response = $this->actingAs($viewer)->get(route('company.structure'));

            $response->assertOk()
                ->assertSee('Sales Department')
                ->assertSee($admin->name)
                ->assertSee($manager->name)
                ->assertSee($employee->name)
                ->assertSee($admin->position)
                ->assertSee($manager->position)
                ->assertSee($employee->position)
                ->assertSee('this.hidden=true; this.nextElementSibling.hidden=false;', false);

            preg_match('/<table.*?<\/table>/s', $response->getContent(), $tableMatches);
            $this->assertNotEmpty($tableMatches, 'The company structure should retain its employee table.');
            $structureTable = $tableMatches[0];
            $this->assertStringContainsString($availablePhotoUrl, $structureTable);
            $this->assertStringNotContainsString($missingPhotoPath, $structureTable);
            $this->assertStringContainsString($manager->initials, $structureTable);
            $this->assertStringContainsString($employee->initials, $structureTable);
            $this->assertLessThan(
                strpos($structureTable, $manager->name),
                strpos($structureTable, $admin->name),
                'Admins should appear before managers and employees.'
            );
            $this->assertLessThan(
                strpos($structureTable, $employee->name),
                strpos($structureTable, $manager->name),
                'Managers should appear before employees even when employees sort earlier alphabetically.'
            );
        }
    }

    private function createUser(Department $department, string $role, string $name, string $email): User
    {
        return User::create([
            'employee_id' => strtoupper(Str::random(10)),
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password'),
            'department_id' => $department->id,
            'role' => $role,
            'position' => str_replace('_', ' ', $role),
            'joining_date' => '2022-03-15',
            'status' => 'ACTIVE',
        ]);
    }
}

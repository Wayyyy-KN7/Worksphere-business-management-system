<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\AuditLog;
use App\Models\CompanySettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminSettingsHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_worksphere_name_and_logo_are_shown_in_the_login_and_application_sidebar(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Worksphere')
            ->assertSee(asset('images/worksphere-mark.svg'), false);

        $admin = $this->createUser('ADMIN', 'admin.worksphere@example.com');
        $this->actingAs($admin)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Worksphere')
            ->assertSee(asset('images/worksphere-mark.svg'), false);
    }

    public function test_admin_settings_only_lists_configuration_options_not_sidebar_modules(): void
    {
        $admin = $this->createUser('ADMIN', 'admin.settings@example.com');

        $response = $this->actingAs($admin)->get(route('admin.settings.index'));

        $response->assertOk()
            ->assertSee('Manage your CRM system configuration')
            ->assertSee('Company information')
            ->assertSee(route('admin.settings.company'))
            ->assertDontSee('Users &amp; Employees')
            ->assertDontSee('CRM Settings')
            ->assertDontSee('Attendance &amp; Leave')
            ->assertDontSee('Sales &amp; Invoice')
            ->assertSee('Login Protection')
            ->assertDontSee('Password Policy')
            ->assertSee('Audit Logs')
            ->assertSee(route('admin.audit-logs.index'), false);
        $this->assertTrue(Route::has('admin.audit-logs.index'));
        $this->get(route('admin.audit-logs.index'))->assertOk();
        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.departments.index'))->assertOk();
        $settingsTemplate = file_get_contents(resource_path('views/admin/settings/index.blade.php'));
        $this->assertStringNotContainsString('<form', $settingsTemplate);
    }

    public function test_admin_audit_logs_page_is_available(): void
    {
        $admin = $this->createUser('ADMIN', 'admin.audit@example.com');
        $this->actingAs($admin)->get('/admin/audit-logs')->assertOk()->assertSee('Audit Logs');
        $this->assertTrue(Route::has('admin.audit-logs.index'));
    }

    public function test_hr_manager_and_employee_sidebar_only_show_my_documents_once(): void
    {
        foreach (['HR_MANAGER', 'HR_EMPLOYEE'] as $role) {
            $user = $this->createUser($role, strtolower($role) . '@example.com');
            $response = $this->actingAs($user)->get(route('documents.my'));

            $response->assertOk();
            $matches = substr_count($response->getContent(), 'bi-file-earmark-person');
            $this->assertSame(1, $matches, "{$role} should have one My Documents sidebar link.");
            Auth::logout();
        }
    }

    public function test_sidebar_does_not_repeat_department_documents_or_follow_up_destinations(): void
    {
        $sales = Department::create(['name' => 'Sales', 'code' => 'SALES', 'status' => 'ACTIVE']);
        $marketing = Department::create(['name' => 'Marketing', 'code' => 'MKT', 'status' => 'ACTIVE']);
        $it = Department::create(['name' => 'Information Technology', 'code' => 'IT', 'status' => 'ACTIVE']);

        foreach ([
            $this->createUser('ADMIN', 'sidebar.admin@example.com'),
            $this->createUser('HR_MANAGER', 'sidebar.hr@example.com'),
            $this->createUser('SALES_MANAGER', 'sidebar.sales@example.com', $sales),
            $this->createUser('SALES_EMPLOYEE', 'sidebar.sales.employee@example.com', $sales),
            $this->createUser('MARKETING_MANAGER', 'sidebar.marketing@example.com', $marketing),
            $this->createUser('IT_MANAGER', 'sidebar.it.manager@example.com', $it),
            $this->createUser('IT_EMPLOYEE', 'sidebar.it.employee@example.com', $it),
        ] as $user) {
            $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
            preg_match('/<nav class="sidebar".*?<\/nav>/s', $response->getContent(), $matches);
            $this->assertNotEmpty($matches, 'The authenticated page should render its sidebar.');
            $sidebar = $matches[0];

            $this->assertSame(1, substr_count($sidebar, 'href="' . route('documents.department') . '"'));
            $canSeeFollowUps = $user->isAdmin()
                || $user->isSales()
                || $user->isMarketing()
                || ($user->isManager() && !$user->isHR());
            $this->assertSame(
                $canSeeFollowUps ? 1 : 0,
                substr_count($sidebar, 'href="' . route('crm.follow-ups.index') . '"'),
                "{$user->role} should see the follow-up destination once only when permitted."
            );
        }
    }

    public function test_registered_route_names_are_unique(): void
    {
        $routeNames = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter();
        $duplicates = $routeNames->duplicates()->unique()->values()->all();

        $this->assertSame([], $duplicates);
    }

    public function test_company_settings_save_logo_and_write_auditable_old_and_new_values(): void
    {
        Storage::fake('public');
        $admin = $this->createUser('ADMIN', 'company.settings@example.com');
        CompanySettings::create([
            'company_name' => 'Old Company',
            'timezone' => 'Asia/Yangon',
            'currency' => 'USD',
            'date_format' => 'Y-m-d',
            'working_days' => ['MON', 'TUE', 'WED', 'THU', 'FRI'],
            'work_start' => '09:00',
            'work_end' => '17:00',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.settings.company.update'), [
            'company_name' => 'Acme Internal Systems',
            'logo' => UploadedFile::fake()->create('acme-logo.png', 20, 'image/png'),
            'address' => '100 Main Street, Yangon',
            'phone' => '+95 1 555 0100',
            'email' => 'office@example.com',
            'timezone' => 'Asia/Bangkok',
            'currency' => 'MMK',
            'date_format' => 'd M Y',
            'working_days' => ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'],
            'work_start' => '08:30',
            'work_end' => '17:30',
        ]);

        $response->assertRedirect(route('admin.settings.company'))->assertSessionHas('success');
        $settings = CompanySettings::findOrFail(1);
        $this->assertSame('Acme Internal Systems', $settings->company_name);
        $this->assertSame('Asia/Bangkok', $settings->timezone);
        $this->assertSame('MMK', $settings->currency);
        $this->assertSame(['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'], $settings->working_days);
        Storage::disk('public')->assertExists($settings->logo_path);

        $audit = AuditLog::where('module', 'Company Settings')->latest()->firstOrFail();
        $this->assertSame('Old Company', $audit->old_values['company_name']);
        $this->assertSame('Acme Internal Systems', $audit->new_values['company_name']);
        $this->assertSame('Asia/Bangkok', $audit->new_values['timezone']);
        $this->assertSame('MMK', $audit->new_values['currency']);
        $this->assertNotNull($audit->ip_address);

        $this->get(route('admin.settings.company'))
            ->assertOk()
            ->assertSee('Acme Internal Systems')
            ->assertSee(asset('storage/' . $settings->logo_path), false);
        $this->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('<link rel="icon" href="' . asset('storage/' . $settings->logo_path) . '">', false);
        Auth::logout();
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(asset('storage/' . $settings->logo_path), false)
            ->assertSee('<link rel="icon" href="' . asset('storage/' . $settings->logo_path) . '">', false);
        $this->assertSame('Asia/Bangkok', config('app.timezone'));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'table_name' => 'company_settings',
            'description' => 'Company configuration updated.',
        ]);
    }

    public function test_company_settings_reject_invalid_working_hours_and_non_admin_access(): void
    {
        $department = Department::create(['name' => 'Sales', 'code' => 'SALES', 'status' => 'ACTIVE']);
        $admin = $this->createUser('ADMIN', 'company.invalid@example.com');
        $employee = $this->createUser('SALES_EMPLOYEE', 'company.employee@example.com', $department);

        $this->actingAs($admin)->put(route('admin.settings.company.update'), [
            'company_name' => 'Internal CRM',
            'timezone' => 'Asia/Yangon',
            'currency' => 'USD',
            'date_format' => 'Y-m-d',
            'working_days' => ['MON', 'FRI'],
            'work_start' => '17:00',
            'work_end' => '09:00',
        ])->assertSessionHasErrors('work_end');

        $this->actingAs($employee)->get(route('admin.settings.company'))->assertForbidden();
    }

    private function createUser(string $role, string $email, ?Department $department = null): User
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
<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserOffboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_deactivates_employee_without_deleting_account_or_history(): void
    {
        $admin = $this->createUser('ADMIN', 'offboarding.admin@example.com');
        $employee = $this->createUser('SALES_EMPLOYEE', 'offboarding.employee@example.com');

        $this->actingAs($admin)
            ->patch(route('admin.users.deactivate', $employee))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'status' => 'INACTIVE',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'UPDATED',
            'record_id' => $employee->id,
            'description' => 'Deactivated employee: Offboarding Test (' . $employee->employee_id . ')',
        ]);
        $this->assertSame('ACTIVE', $admin->fresh()->status);
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->createUser('ADMIN', 'offboarding.self@example.com');

        $this->actingAs($admin)
            ->patch(route('admin.users.deactivate', $admin))
            ->assertSessionHas('error');

        $this->assertSame('ACTIVE', $admin->fresh()->status);
    }

    public function test_admin_user_list_offers_deactivation_instead_of_permanent_delete(): void
    {
        $admin = $this->createUser('ADMIN', 'offboarding.list.admin@example.com');
        $employee = $this->createUser('SALES_EMPLOYEE', 'offboarding.list.employee@example.com');

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee(route('admin.users.deactivate', $employee))
            ->assertSee('Deactivate')
            ->assertDontSee('Delete this employee?');
    }

    public function test_admin_user_list_pagination_links_to_and_loads_the_next_page(): void
    {
        $admin = $this->createUser('ADMIN', 'pagination.admin@example.com');

        foreach (range(1, 15) as $number) {
            $this->createUser('SALES_EMPLOYEE', "pagination.employee{$number}@example.com");
        }

        $firstPage = $this->actingAs($admin)->get(route('admin.users.index'));
        $firstPage->assertOk()
            ->assertSee(route('admin.users.index', ['page' => 2]), false)
            ->assertSee('<ul class="pagination">', false)
            ->assertDontSee('flex justify-between flex-1 sm:hidden', false)
            ->assertViewHas('users', function ($users) {
                $this->assertSame(1, $users->currentPage());
                $this->assertSame(16, $users->total());
                $this->assertCount(15, $users->items());

                return true;
            });

        $this->get(route('admin.users.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('users', function ($users) {
                $this->assertSame(2, $users->currentPage());
                $this->assertSame(16, $users->total());
                $this->assertCount(1, $users->items());

                return true;
            });
    }

    public function test_permanent_user_delete_route_is_not_available(): void
    {
        $this->assertFalse(Route::has('admin.users.destroy'));
    }

    private function createUser(string $role, string $email): User
    {
        return User::create([
            'employee_id' => strtoupper(Str::random(10)),
            'name' => 'Offboarding Test',
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }
}
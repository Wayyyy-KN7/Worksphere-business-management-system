<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoginWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_shows_accessible_validation_controls(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Hello!')
            ->assertSee('Welcome back')
            ->assertSee('login-card', false)
            ->assertSee('var(--primary)', false)
            ->assertDontSee('Forgot password?')
            ->assertSee('Accounts are created by an administrator.')
            ->assertDontSee('Create account')
            ->assertSee('type="email"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertSee('toggle-password', false);
    }

    public function test_login_requires_a_valid_email_and_password(): void
    {
        $this->post(route('login'), [
            'email' => 'not-an-email',
            'password' => 'short',
        ])->assertSessionHasErrors(['email', 'password']);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->post(route('login'), [
            'email' => 'person@example.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    public function test_inactive_accounts_cannot_sign_in(): void
    {
        $user = User::factory()->create([
            'employee_id' => (string) Str::uuid(),
            'status' => 'INACTIVE',
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_active_accounts_can_sign_in(): void
    {
        $user = User::factory()->create([
            'employee_id' => (string) Str::uuid(),
            'status' => 'ACTIVE',
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }
}
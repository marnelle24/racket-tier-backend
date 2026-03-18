<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login_from_admin_dashboard(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect(route('admin.login'));
    }

    public function test_non_admin_authenticated_user_gets_403_on_admin_dashboard(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertForbidden();
    }

    public function test_admin_can_view_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertSee('Dashboard', false);
    }

    public function test_admin_login_rejects_non_admin_credentials(): void
    {
        User::factory()->create([
            'email' => 'player@example.com',
            'password' => 'password',
            'role' => User::ROLE_USER,
        ]);

        $response = $this->post(route('admin.login.post'), [
            'email' => 'player@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_login_accepts_admin_user(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response = $this->post(route('admin.login.post'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs(User::where('email', 'admin@example.com')->first());
    }

    public function test_admin_register_form_available_when_no_admin_exists(): void
    {
        $response = $this->get(route('admin.register'));

        $response->assertOk();
        $response->assertSee('Initial admin setup', false);
    }

    public function test_admin_register_creates_first_admin_and_logs_them_in(): void
    {
        $response = $this->post(route('admin.register.post'), [
            'name' => 'First Admin',
            'email' => 'first-admin@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertRedirect(route('admin.dashboard'));

        $admin = User::where('email', 'first-admin@example.com')->firstOrFail();
        $this->assertEquals(User::ROLE_ADMIN, $admin->role);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_register_is_forbidden_if_admin_already_exists(): void
    {
        User::factory()->admin()->create();

        $this->get(route('admin.register'))->assertForbidden();
        $this->post(route('admin.register.post'), [
            'name' => 'Another',
            'email' => 'another@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ])->assertForbidden();
    }
}

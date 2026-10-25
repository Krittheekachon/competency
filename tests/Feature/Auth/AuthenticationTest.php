<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_authenticate_with_an_admin_assigned_username(): void
    {
        User::factory()->create([
            'username' => 'somchai.k',
            'password' => 'secure-password',
        ]);

        $response = $this->post('/login', [
            'email' => 'SOMCHAI.K',
            'password' => 'secure-password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_login_post_replaces_an_existing_authenticated_session(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin',
            'password' => 'admin-password',
        ]);
        $krit = User::factory()->create([
            'username' => 'krit',
            'password' => 'krit-password',
        ]);

        $this->actingAs($admin);
        config()->set('services.kku_sso.enabled', true);
        config()->set('services.kku_sso.app_id', 'test-app');
        config()->set('services.kku_sso.logout_url', 'https://sso.example/logout');
        session([
            'auth_via_kku_sso' => true,
            'kku_sso_authenticated' => true,
        ]);
        $oldSessionId = session()->getId();

        $response = $this->post('/login', [
            'email' => 'krit',
            'password' => 'krit-password',
        ]);

        $this->assertAuthenticatedAs($krit);
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertFalse(session()->has('auth_via_kku_sso'));
        $this->assertFalse(session()->has('kku_sso_authenticated'));
        $response->assertRedirect(route('dashboard', absolute: false));

        $this->post('/logout')->assertRedirect('/');
    }

    public function test_invalid_password_does_not_replace_an_existing_authenticated_session(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin',
            'password' => 'admin-password',
        ]);
        User::factory()->create([
            'username' => 'krit',
            'password' => 'krit-password',
        ]);

        $this->actingAs($admin)
            ->post('/login', [
                'email' => 'krit',
                'password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('email');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_password_login_disabled_does_not_change_an_existing_session(): void
    {
        config()->set('services.kku_sso.password_login_enabled', false);
        $admin = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($admin)
            ->post('/login', [
                'email' => $other->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_password_login_disabled_rejects_guest_password_authentication(): void
    {
        config()->set('services.kku_sso.password_login_enabled', false);
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('sso_error');

        $this->assertGuest();
    }

    public function test_test_email_prefix_is_not_treated_as_a_username(): void
    {
        User::factory()->create([
            'email' => 'legacy-user@test.com',
            'username' => null,
            'password' => 'secure-password',
        ]);

        $this->post('/login', [
            'email' => 'legacy-user',
            'password' => 'secure-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง กรุณาตรวจสอบและลองอีกครั้ง',
        ]);
    }

    public function test_inactive_users_can_not_authenticate(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ',
        ]);
    }

    public function test_existing_session_is_ended_when_account_becomes_inactive(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $response = $this->actingAs($user)->get('/dashboard');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}

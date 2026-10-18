<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KkuSsoAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.kku_sso', [
            'enabled' => true,
            'app_id' => 'en-idp-uat',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'web_url' => 'https://sso-uat-web.kku.ac.th',
            'api_url' => 'https://sso-uat-api.kku.ac.th',
            'redirect_url' => 'https://en-idp.test/auth/kku/callback',
            'password_login_enabled' => true,
            'timeout' => 10,
        ]);
    }

    public function test_sso_redirect_starts_a_login_attempt(): void
    {
        $response = $this->get(route('sso.redirect'));

        $response->assertRedirect('https://sso-uat-web.kku.ac.th/login?app=en-idp-uat');
        $response->assertSessionHas('kku_sso_login_attempt', true);
    }

    public function test_existing_user_can_authenticate_with_kku_sso(): void
    {
        $user = User::factory()->create([
            'sso' => '12345678',
            'email' => 'staff@kku.ac.th',
            'email_verified_at' => null,
            'is_active' => true,
        ]);

        Http::fake([
            'https://sso-uat-api.kku.ac.th/auth.token' => Http::response([
                'ok' => true,
                'accessToken' => 'secret-access-token',
                'email' => 'staff@kku.ac.th',
                'employeeId' => '12345678',
            ]),
            'https://sso-uat-api.kku.ac.th/user.profile' => Http::response([
                'ok' => true,
                'profile' => [
                    'email' => 'staff@kku.ac.th',
                    'userId' => 'staff.user',
                    'firstname' => 'สมชาย',
                    'lastname' => 'ใจดี',
                ],
            ]),
        ]);

        $response = $this->withSession(['kku_sso_login_attempt' => true])
            ->get(route('sso.callback.login', ['code' => 'one-time-code']));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertSessionHas('auth_via_kku_sso', true);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://sso-uat-api.kku.ac.th/auth.token'
            && $request['code'] === 'one-time-code'
            && $request['redirectUrl'] === 'https://en-idp.test/auth/kku/callback'
            && $request['clientId'] === 'client-id'
            && $request['clientSecret'] === 'client-secret');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://sso-uat-api.kku.ac.th/user.profile'
            && $request->hasHeader('Authorization', 'Bearer secret-access-token'));
    }

    public function test_sso_does_not_create_an_unknown_user(): void
    {
        Http::fake([
            'https://sso-uat-api.kku.ac.th/auth.token' => Http::response([
                'ok' => true,
                'accessToken' => 'secret-access-token',
                'email' => 'unknown@kku.ac.th',
                'employeeId' => 'not-registered',
            ]),
            'https://sso-uat-api.kku.ac.th/user.profile' => Http::response([
                'ok' => true,
                'profile' => [
                    'email' => 'unknown@kku.ac.th',
                    'userId' => 'unknown.user',
                ],
            ]),
        ]);

        $response = $this->withSession(['kku_sso_login_attempt' => true])
            ->get(route('sso.callback.login', ['code' => 'one-time-code']));

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('sso_error', 'ไม่พบบัญชีนี้ในระบบ EN-IDP กรุณาติดต่อผู้ดูแลระบบ');
    }

    public function test_callback_is_rejected_without_a_started_login_attempt(): void
    {
        Http::fake();

        $response = $this->get(route('sso.callback.login', ['code' => 'one-time-code']));

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('sso_error', 'ไม่พบคำขอเข้าสู่ระบบ SSO กรุณาลองใหม่อีกครั้ง');
        Http::assertNothingSent();
    }

    public function test_inactive_user_cannot_authenticate_with_kku_sso(): void
    {
        User::factory()->create([
            'sso' => '12345678',
            'email' => 'staff@kku.ac.th',
            'is_active' => false,
        ]);

        Http::fake([
            'https://sso-uat-api.kku.ac.th/auth.token' => Http::response([
                'ok' => true,
                'accessToken' => 'secret-access-token',
                'employeeId' => '12345678',
            ]),
            'https://sso-uat-api.kku.ac.th/user.profile' => Http::response([
                'ok' => true,
                'profile' => ['userId' => 'staff.user'],
            ]),
        ]);

        $response = $this->withSession(['kku_sso_login_attempt' => true])
            ->get(route('sso.callback.login', ['code' => 'one-time-code']));

        $this->assertGuest();
        $response->assertSessionHas('sso_error', 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ');
    }

    public function test_sso_logout_uses_the_kku_logout_endpoint(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['auth_via_kku_sso' => true])
            ->post(route('logout'));

        $this->assertGuest();
        $response->assertRedirect('https://sso-uat-web.kku.ac.th/logout?app=en-idp-uat');
    }
}

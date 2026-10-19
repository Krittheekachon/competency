<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
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
                    'facultyName' => 'คณะวิศวกรรมศาสตร์',
                    'workline' => 'สายสนับสนุน',
                    'positionName' => 'นักวิชาการคอมพิวเตอร์',
                    'positionTypeName' => 'พนักงานมหาวิทยาลัย',
                    'levelId' => 'L2',
                    'levelName' => 'ชำนาญการ',
                ],
            ]),
        ]);

        $response = $this->withSession(['kku_sso_login_attempt' => true])
            ->get(route('sso.callback.login', ['code' => 'one-time-code']));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame('นักวิชาการคอมพิวเตอร์', $user->fresh()->sso_position_name);
        $this->assertSame('support', $user->fresh()->sso_workline_type);
        $this->assertDatabaseHas('position_titles', [
            'workline_type' => 'support',
            'name' => 'นักวิชาการคอมพิวเตอร์',
            'position_type_name' => 'พนักงานมหาวิทยาลัย',
            'source' => 'sso',
        ]);
        $this->assertDatabaseHas('levels', [
            'name' => 'ชำนาญการ',
            'expected_level' => null,
            'source' => 'sso',
            'sso_level_id' => 'L2',
        ]);
        $this->assertDatabaseHas('sso_profile_changes', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
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

    public function test_sso_creates_an_unknown_user_as_employee_without_overwriting_internal_structure(): void
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
                    'firstname' => 'คนใหม่',
                    'lastname' => 'ทดสอบ',
                    'workline' => 'วิชาการ',
                    'positionName' => 'อาจารย์',
                    'positionTypeName' => 'ข้าราชการ',
                    'levelId' => 'AC-1',
                    'levelName' => 'ระดับ 1',
                ],
            ]),
        ]);

        $response = $this->withSession(['kku_sso_login_attempt' => true])
            ->get(route('sso.callback.login', ['code' => 'one-time-code']));

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'unknown@kku.ac.th')->firstOrFail();
        $this->assertSame('employee', $user->role->key);
        $this->assertSame('not-registered', $user->sso);
        $this->assertSame('unknown.user', $user->sso_user_id);
        $this->assertSame('academic', $user->sso_workline_type);
        $this->assertNull($user->workline);
        $this->assertNull($user->position_id);
        $this->assertNull($user->level_id);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_repeated_identical_sso_profile_does_not_duplicate_pending_change(): void
    {
        $responses = [
            'https://sso-uat-api.kku.ac.th/auth.token' => Http::response([
                'ok' => true,
                'accessToken' => 'secret-access-token',
                'email' => 'repeat@kku.ac.th',
                'employeeId' => 'repeat-1',
            ]),
            'https://sso-uat-api.kku.ac.th/user.profile' => Http::response([
                'ok' => true,
                'profile' => [
                    'email' => 'repeat@kku.ac.th',
                    'userId' => 'repeat.user',
                    'firstname' => 'ทดสอบ',
                    'lastname' => 'ซ้ำ',
                    'workline' => 'สายงานสนับสนุน',
                    'positionName' => 'นักวิชาการคอมพิวเตอร์',
                    'positionTypeName' => 'พนักงานมหาวิทยาลัย',
                    'levelId' => 'L2',
                    'levelName' => 'ชำนาญการ',
                ],
            ]),
        ];

        Http::fake($responses);
        $this->withSession(['kku_sso_login_attempt' => true])
            ->get(route('sso.callback.login', ['code' => 'first-code']))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->post(route('logout'));
        Http::fake($responses);
        $this->withSession(['kku_sso_login_attempt' => true])
            ->get(route('sso.callback.login', ['code' => 'second-code']))
            ->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('sso', 'repeat-1')->firstOrFail();
        $this->assertSame(1, DB::table('sso_profile_changes')->where('user_id', $user->id)->count());
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

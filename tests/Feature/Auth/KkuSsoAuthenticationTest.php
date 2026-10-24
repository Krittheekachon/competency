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
            'login_url' => 'https://sso-uat-web.kku.ac.th/login',
            'token_url' => 'https://sso-uat-api.kku.ac.th/auth.token',
            'logout_url' => 'https://sso-uat-web.kku.ac.th/logout',
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
        $response->assertSessionHas('kku_sso_login_attempt');
    }

    public function test_sso_redirect_uses_the_configured_login_url(): void
    {
        config()->set('services.kku_sso.login_url', 'https://sso-custom.example/login');

        $response = $this->get(route('sso.redirect'));

        $response->assertRedirect('https://sso-custom.example/login?app=en-idp-uat');
    }

    public function test_existing_user_can_authenticate_with_kku_sso(): void
    {
        $user = User::factory()->create([
            'sso' => '12345678',
            'email' => 'staff@kku.ac.th',
            'email_verified_at' => null,
            'is_active' => true,
        ]);

        config()->set('services.kku_sso.token_url', 'https://sso-custom.example/token');

        Http::fake([
            'https://sso-custom.example/token' => Http::response([
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

        $response = $this->withSession(['kku_sso_login_attempt' => now()->timestamp])
            ->get(route('sso.callback.login', ['code' => 'one-time-code']));

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->email_verified_at);
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
        Http::assertSent(fn ($request): bool => $request->url() === 'https://sso-custom.example/token'
            && $request['code'] === 'one-time-code'
            && $request['redirectUrl'] === 'https://en-idp.test/auth/kku/callback'
            && $request['clientId'] === 'client-id'
            && $request['clientSecret'] === 'client-secret');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://sso-uat-api.kku.ac.th/user.profile'
            && $request->hasHeader('Authorization', 'Bearer secret-access-token'));
    }

    public function test_sso_rejects_an_unknown_user_without_creating_an_account(): void
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

        $response = $this->withSession(['kku_sso_login_attempt' => now()->timestamp])
            ->get(route('sso.callback.login', ['code' => 'one-time-code']));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'unknown@kku.ac.th']);
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('sso_error');
    }

    public function test_sso_rejects_conflicting_identity_matches(): void
    {
        User::factory()->create(['sso' => 'employee-1', 'email' => 'first@kku.ac.th']);
        User::factory()->create(['sso' => 'employee-2', 'sso_user_id' => 'sso-user-1', 'email' => 'second@kku.ac.th']);
        Http::fake([
            'https://sso-uat-api.kku.ac.th/auth.token' => Http::response([
                'ok' => true,
                'accessToken' => 'secret-access-token',
                'employeeId' => 'employee-1',
            ]),
            'https://sso-uat-api.kku.ac.th/user.profile' => Http::response([
                'ok' => true,
                'profile' => ['userId' => 'sso-user-1', 'email' => 'second@kku.ac.th'],
            ]),
        ]);

        $response = $this->withSession(['kku_sso_login_attempt' => now()->timestamp])
            ->get(route('sso.callback.login', ['code' => 'one-time-code']));

        $this->assertGuest();
        $response->assertSessionHas('sso_error');
        $this->assertSame(0, DB::table('sso_profile_changes')->count());
        $this->assertNull(User::query()->where('sso', 'employee-1')->value('sso_user_id'));
    }

    public function test_sso_rejects_conflicting_employee_ids_from_token_and_profile(): void
    {
        User::factory()->create(['sso' => 'employee-token', 'email' => 'token@kku.ac.th']);
        Http::fake([
            'https://sso-uat-api.kku.ac.th/auth.token' => Http::response([
                'ok' => true,
                'accessToken' => 'secret-access-token',
                'employeeId' => 'employee-token',
            ]),
            'https://sso-uat-api.kku.ac.th/user.profile' => Http::response([
                'ok' => true,
                'profile' => [
                    'employeeId' => 'employee-profile',
                    'userId' => 'sso-user-1',
                    'email' => 'token@kku.ac.th',
                ],
            ]),
        ]);

        $response = $this->withSession(['kku_sso_login_attempt' => now()->timestamp])
            ->get(route('sso.callback.login', ['code' => 'one-time-code']));

        $this->assertGuest();
        $response->assertSessionHas('sso_error');
        $this->assertNull(User::query()->where('sso', 'employee-token')->value('sso_user_id'));
        $this->assertSame(0, DB::table('sso_profile_changes')->count());
    }

    public function test_sso_rejects_duplicate_sso_user_id_matches(): void
    {
        User::factory()->create(['sso' => 'employee-1', 'sso_user_id' => 'duplicate-user']);
        User::factory()->create(['sso' => 'employee-2', 'sso_user_id' => 'duplicate-user']);
        Http::fake([
            'https://sso-uat-api.kku.ac.th/auth.token' => Http::response([
                'ok' => true,
                'accessToken' => 'secret-access-token',
            ]),
            'https://sso-uat-api.kku.ac.th/user.profile' => Http::response([
                'ok' => true,
                'profile' => ['userId' => 'duplicate-user'],
            ]),
        ]);

        $response = $this->withSession(['kku_sso_login_attempt' => now()->timestamp])
            ->get(route('sso.callback.login', ['code' => 'one-time-code']));

        $this->assertGuest();
        $response->assertSessionHas('sso_error');
        $this->assertSame(0, DB::table('sso_profile_changes')->count());
        $this->assertSame(2, User::query()->where('sso_user_id', 'duplicate-user')->count());
    }

    public function test_unverified_sso_email_does_not_link_or_replace_an_account_email(): void
    {
        User::factory()->create([
            'email' => 'staff@kku.ac.th',
            'sso' => null,
            'email_verified_at' => null,
        ]);
        Http::fake([
            'https://sso-uat-api.kku.ac.th/auth.token' => Http::response([
                'ok' => true,
                'accessToken' => 'secret-access-token',
                'email' => 'staff@kku.ac.th',
            ]),
            'https://sso-uat-api.kku.ac.th/user.profile' => Http::response([
                'ok' => true,
                'profile' => ['email' => 'staff@kku.ac.th'],
            ]),
        ]);

        $response = $this->withSession(['kku_sso_login_attempt' => now()->timestamp])
            ->get(route('sso.callback.login', ['code' => 'one-time-code']));

        $this->assertGuest();
        $response->assertSessionHas('sso_error');
        $this->assertDatabaseHas('users', ['email' => 'staff@kku.ac.th', 'sso' => null]);
        $this->assertNull(User::query()->where('email', 'staff@kku.ac.th')->value('email_verified_at'));
    }

    public function test_repeated_identical_sso_profile_does_not_duplicate_pending_change(): void
    {
        User::factory()->create([
            'sso' => 'repeat-1',
            'email' => 'repeat@kku.ac.th',
        ]);

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
        $this->withSession(['kku_sso_login_attempt' => now()->timestamp])
            ->get(route('sso.callback.login', ['code' => 'first-code']))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->post(route('logout'));
        Http::fake($responses);
        $this->withSession(['kku_sso_login_attempt' => now()->timestamp])
            ->get(route('sso.callback.login', ['code' => 'second-code']))
            ->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('sso', 'repeat-1')->firstOrFail();
        $this->assertSame(1, DB::table('sso_profile_changes')->where('user_id', $user->id)->count());
    }

    public function test_missing_and_empty_structural_fields_preserve_last_synced_values(): void
    {
        $user = User::factory()->create([
            'sso' => 'preserve-1',
            'email' => 'preserve@kku.ac.th',
            'sso_workline' => 'สายสนับสนุน',
            'sso_workline_type' => 'support',
            'sso_faculty_name' => 'คณะเดิม',
            'sso_position_name' => 'ตำแหน่งเดิม',
            'sso_position_type_name' => 'ประเภทเดิม',
            'sso_level_id' => 'L-old',
            'sso_level_name' => 'ระดับเดิม',
            'sso_person_status' => 'active',
        ]);

        Http::fake([
            'https://sso-uat-api.kku.ac.th/auth.token' => Http::response([
                'ok' => true,
                'accessToken' => 'test-access-token',
                'employeeId' => 'preserve-1',
            ]),
            'https://sso-uat-api.kku.ac.th/user.profile' => Http::response([
                'ok' => true,
                'profile' => [
                    'employeeId' => 'preserve-1',
                    'userId' => 'preserve.user',
                    'workline' => '',
                    'facultyName' => null,
                    'positionName' => '   ',
                    'positionTypeName' => null,
                    'levelId' => '',
                    'levelName' => null,
                    'personStatus' => '',
                ],
            ]),
        ]);

        $response = $this->withSession(['kku_sso_login_attempt' => now()->timestamp])
            ->get(route('sso.callback.login', ['code' => 'one-time-code']));

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertSame('สายสนับสนุน', $user->sso_workline);
        $this->assertSame('support', $user->sso_workline_type);
        $this->assertSame('คณะเดิม', $user->sso_faculty_name);
        $this->assertSame('ตำแหน่งเดิม', $user->sso_position_name);
        $this->assertSame('ประเภทเดิม', $user->sso_position_type_name);
        $this->assertSame('L-old', $user->sso_level_id);
        $this->assertSame('ระดับเดิม', $user->sso_level_name);
        $this->assertSame('active', $user->sso_person_status);
        $this->assertSame(0, DB::table('sso_profile_changes')->where('user_id', $user->id)->count());
    }

    public function test_malformed_structural_payload_is_rejected_without_changing_user_data(): void
    {
        $user = User::factory()->create([
            'sso' => 'malformed-1',
            'email' => 'malformed@kku.ac.th',
            'sso_position_name' => 'ตำแหน่งเดิม',
        ]);

        Http::fake([
            'https://sso-uat-api.kku.ac.th/auth.token' => Http::response([
                'ok' => true,
                'accessToken' => 'test-access-token',
                'employeeId' => 'malformed-1',
            ]),
            'https://sso-uat-api.kku.ac.th/user.profile' => Http::response([
                'ok' => true,
                'profile' => [
                    'employeeId' => 'malformed-1',
                    'userId' => 'malformed.user',
                    'positionName' => ['unexpected' => 'array'],
                ],
            ]),
        ]);

        $response = $this->withSession(['kku_sso_login_attempt' => now()->timestamp])
            ->get(route('sso.callback.login', ['code' => 'one-time-code']));

        $this->assertGuest();
        $response->assertSessionHas('sso_error');
        $this->assertSame('ตำแหน่งเดิม', $user->fresh()->sso_position_name);
        $this->assertNull($user->fresh()->sso_user_id);
        $this->assertSame(0, DB::table('sso_profile_changes')->where('user_id', $user->id)->count());
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
            'name' => 'Existing Name',
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

        $response = $this->withSession(['kku_sso_login_attempt' => now()->timestamp])
            ->get(route('sso.callback.login', ['code' => 'one-time-code']));

        $this->assertGuest();
        $this->assertSame('Existing Name', User::query()->where('sso', '12345678')->value('name'));
        $this->assertNull(User::query()->where('sso', '12345678')->value('sso_user_id'));
        $this->assertSame(0, DB::table('sso_profile_changes')->count());
        $this->assertSame(0, DB::table('position_titles')->where('source', 'sso')->count());
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

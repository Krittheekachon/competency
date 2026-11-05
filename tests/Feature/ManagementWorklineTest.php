<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FacultyAnalyticsService;
use App\Services\FacultyAssessmentPermissionResolver;
use App\Services\PositionTitleResolver;
use App\Services\SsoProfileSyncService;
use App\Services\SsoWorklineNormalizer;
use App\Services\UserOrganizationPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManagementWorklineTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role): User
    {
        return User::factory()->create(['role_id' => DB::table('roles')->where('key', $role)->value('id')]);
    }

    private function structure(string $workline = 'สายงานบริหาร'): array
    {
        $wid = DB::table('worklines')->insertGetId(['name' => $workline]);
        $family = DB::table('job_families')->insertGetId(['name' => 'สำนักงานบริหารทดสอบ', 'workline_id' => $wid]);
        $level = DB::table('levels')->insertGetId(['name' => 'บริหาร', 'workline_id' => $wid]);
        return [$wid, $family, $level];
    }

    private function title(string $type = 'management', string $name = 'ผู้อำนวยการทดสอบ'): int
    {
        return DB::table('position_titles')->insertGetId([
            'workline_type' => $type, 'name' => $name, 'normalized_name' => $name,
            'workline_id' => $type === 'management' ? DB::table('worklines')->whereIn('name', ['สายงานบริหาร', 'สายบริหาร', 'บริหาร'])->value('id') : null,
        ]);
    }

    public function test_management_aliases_normalize_consistently_without_new_scoring_rights(): void
    {
        $normalizer = app(SsoWorklineNormalizer::class);
        foreach (['บริหาร', 'สายบริหาร', 'สายงานบริหาร'] as $name) {
            $this->assertSame('management', $normalizer->structureType($name));
            $this->assertSame('สายงานบริหาร', $normalizer->systemWorkline($name));
        }
        $employee = $this->actor('employee');
        $employee->workline = 'สายงานบริหาร';
        // A conflicting academic title must not silently change management into academic.
        $employee->position_title_id = $this->title('academic');
        $permissions = app(FacultyAssessmentPermissionResolver::class);
        foreach (['academic_department_head', 'deputy_dean', 'dean'] as $role) {
            foreach (['CC', 'FC1', 'MC'] as $type) {
                $this->assertFalse($permissions->canScore($this->actor($role), $employee, $type));
            }
        }
        $this->assertNull($normalizer->structureType('สายงานใหม่ที่ยังไม่จำแนก'));
    }

    public function test_admin_can_create_management_catalog_title_and_reject_duplicates_or_unknown_types(): void
    {
        $admin = $this->actor('admin');
        $data = ['workline_type' => 'management', 'name' => 'ผู้อำนวยการทดสอบ'];
        $this->actingAs($admin)->post(route('admin.structure.position-titles.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('position_titles', ['workline_type' => 'management', 'name' => $data['name'], 'source' => 'manual']);
        $this->postJson(route('admin.structure.position-titles.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(route('admin.structure.position-titles.store'), [...$data, 'workline_type' => 'made-up'])->assertUnprocessable()->assertJsonValidationErrors('workline_type');
        $this->actingAs($this->actor('employee'))->postJson(route('admin.structure.position-titles.store'), $data)->assertForbidden();
    }

    public function test_management_title_can_be_placed_and_assigned_to_a_user_and_chart(): void
    {
        [$wid, $family, $level] = $this->structure('สายบริหาร');
        $admin = $this->actor('admin');
        $title = $this->title();
        $this->actingAs($admin)->post(route('admin.structure.positions.store'), [
            'workline_name' => 'สายบริหาร', 'job_family_name' => 'สำนักงานบริหารทดสอบ',
            'name' => 'ผู้อำนวยการทดสอบ', 'position_title_id' => $title,
        ])->assertSessionHasNoErrors();
        $position = DB::table('positions')->where('job_family_id', $family)->first();
        $this->post(route('admin.users.store'), [
            'sso' => 'qa-management-user', 'username' => 'qa.management', 'password' => 'test-password',
            'password_confirmation' => 'test-password', 'fn' => 'บริหาร', 'ln' => 'ทดสอบ',
            'em' => 'management@example.test', 'w' => 'สายบริหาร', 'd' => 'สำนักงานบริหารทดสอบ',
            'p' => 'ผู้อำนวยการทดสอบ', 'l' => 'บริหาร', 'r' => 'employee', 'act' => true,
        ])->assertSessionHasNoErrors();
        $user = User::where('username', 'qa.management')->firstOrFail();
        $this->assertSame($title, $user->position_title_id);
        $this->assertSame($position->id, $user->position_id);
        $this->assertSame('management', app(UserOrganizationPresenter::class)->canonicalForUser($user)['type']);
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('positionTitles', fn ($titles) => collect($titles)->firstWhere('id', $title)['worklineName'] === 'สายบริหาร')
            ->where('organizationChart.nodes', fn ($nodes) => collect($nodes)->firstWhere('key', 'family:'.$family)['members'][0]['id'] === $user->id)
        );
    }

    public function test_cross_workline_catalog_assignment_is_rejected(): void
    {
        $this->structure();
        $title = $this->title('support');
        $this->actingAs($this->actor('admin'))->postJson(route('admin.structure.positions.store'), [
            'workline_name' => 'สายงานบริหาร', 'job_family_name' => 'สำนักงานบริหารทดสอบ',
            'name' => 'ตำแหน่งผิดสาย', 'position_title_id' => $title,
        ])->assertUnprocessable()->assertJsonValidationErrors('position_title_id');
        $this->assertDatabaseCount('positions', 0);
    }

    public function test_management_executives_can_save_root_titles_without_department_or_legacy_placement(): void
    {
        $admin = $this->actor('admin');
        $workline = DB::table('worklines')->insertGetId(['name' => 'สายบริหาร']);
        foreach (['deputy_dean', 'assistant_dean'] as $role) {
            $title = $this->title('management', 'QA '.$role);
            $payload = [
                'sso' => 'qa-root-'.$role, 'username' => 'qa.'.$role, 'password' => 'test-password',
                'password_confirmation' => 'test-password', 'fn' => 'QA', 'ln' => $role,
                'em' => $role.'@example.test', 'r' => $role, 'w' => 'สายบริหาร',
                'p' => 'QA '.$role, 'position_title_id' => $title,
            ];
            $this->actingAs($admin)->post(route('admin.users.store'), $payload)->assertSessionHasNoErrors();
            $user = User::where('sso', $payload['sso'])->firstOrFail();
            $this->assertDatabaseHas('users', ['id' => $user->id, 'workline' => 'สายบริหาร', 'department' => null, 'position_id' => null, 'position_title_id' => $title]);
            $this->put(route('admin.users.update', $user), [...$payload, 'preserve_existing_structure' => true])->assertSessionHasNoErrors();
            $this->assertDatabaseHas('users', ['id' => $user->id, 'position_title_id' => $title]);
            $this->assertSame('สายบริหาร', app(UserOrganizationPresenter::class)->forUser($user)['display']);
        }
        $this->assertDatabaseCount('positions', 0);
        $this->assertDatabaseCount('job_families', 0);
    }

    public function test_dean_can_be_assigned_a_management_catalog_position(): void
    {
        $admin = $this->actor('admin');
        DB::table('worklines')->insertGetId(['name' => 'สายบริหาร']);
        $title = $this->title('management', 'คณบดี');
        $payload = [
            'sso' => 'qa-dean-position', 'username' => 'qa.dean.position', 'password' => 'test-password',
            'password_confirmation' => 'test-password', 'fn' => 'QA', 'ln' => 'Dean',
            'em' => 'qa.dean.position@example.test', 'r' => 'dean', 'w' => 'สายบริหาร',
            'p' => 'คณบดี', 'position_title_id' => $title,
        ];

        $this->actingAs($admin)->post(route('admin.users.store'), $payload)->assertSessionHasNoErrors();

        $user = User::where('sso', $payload['sso'])->firstOrFail();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'workline' => 'สายบริหาร',
            'department' => null,
            'position' => 'คณบดี',
            'position_id' => null,
            'position_title_id' => $title,
        ]);
    }

    public function test_legacy_management_position_resolves_to_management_not_unclassified(): void
    {
        [, $family] = $this->structure('บริหาร');
        $position = DB::table('positions')->insertGetId(['name' => 'Legacy director', 'job_family_id' => $family]);
        $title = app(PositionTitleResolver::class)->forPositionId($position);
        $this->assertDatabaseHas('position_titles', ['id' => $title, 'workline_type' => 'management']);
    }

    public function test_sso_management_position_is_imported_with_management_code(): void
    {
        $user = $this->actor('employee');
        $user->forceFill(['sso' => 'qa-management-sso', 'email' => 'sso-management@example.test'])->save();
        $synced = app(SsoProfileSyncService::class)->sync(
            ['employeeId' => 'qa-management-sso', 'email' => $user->email],
            ['employeeId' => 'qa-management-sso', 'email' => $user->email, 'workline' => 'สายบริหาร', 'positionName' => 'SSO director'],
        );
        $this->assertSame('management', $synced->sso_workline_type);
        $this->assertDatabaseHas('position_titles', ['id' => $synced->position_title_id, 'workline_type' => 'management', 'source' => 'sso']);
        $this->assertDatabaseHas('sso_profile_changes', ['user_id' => $user->id, 'status' => 'pending']);
    }

    public function test_hr_can_map_all_competency_types_to_management_positions_and_analytics_keeps_the_scope(): void
    {
        [, $family] = $this->structure();
        $title = $this->title();
        $position = DB::table('positions')->insertGetId(['name' => 'ผู้อำนวยการทดสอบ', 'job_family_id' => $family, 'position_title_id' => $title]);
        $employee = $this->actor('employee');
        $employee->forceFill(['workline' => 'สายงานบริหาร', 'department' => 'สำนักงานบริหารทดสอบ', 'position_id' => $position, 'position_title_id' => $title])->save();
        $round = DB::table('assessment_rounds')->insertGetId(['name' => 'Management round', 'year' => 2569, 'is_active' => false]);
        $hr = $this->actor('hr');
        foreach (['CC', 'MC', 'FC1'] as $code) {
            $type = DB::table('competency_types')->insertGetId(['code' => $code, 'full_name' => $code, 'description' => $code]);
            $competency = DB::table('competencies')->insertGetId(['code' => 'QA-'.$code, 'name' => $code, 'competency_type_id' => $type]);
            $this->actingAs($hr)->post(route('hr.position-competencies.store'), ['position_id' => $position, 'competency_id' => $competency, 'assessment_round_id' => $round])->assertSessionHasNoErrors();
            $this->assertDatabaseHas('position_competencies', ['position_title_id' => $title, 'competency_id' => $competency, 'assessment_round_id' => $round]);
            if ($code === 'CC') {
                $assessment = DB::table('assessments')->insertGetId([
                    'assessment_round_id' => $round, 'user_id' => $employee->id,
                    'competency_id' => $competency, 'status' => 'approved', 'score' => 2,
                ]);
                DB::table('competency_gaps')->insert([
                    'assessment_id' => $assessment, 'competency_id' => $competency,
                    'expected_level' => 3, 'actual_level' => 2, 'gap' => -1,
                    'status' => 'approved', 'requires_idp' => true,
                ]);
            }
        }
        $analytics = app(FacultyAnalyticsService::class)->build($round, [$employee->id]);
        $management = collect($analytics['worklines'])->firstWhere('key', 'management');
        $this->assertSame(1, $management['totalEmployees']);
        $this->assertSame('สำนักงานบริหารทดสอบ', $management['organizations'][0]['label']);
        $this->assertStringStartsWith('management:', $management['organizations'][0]['key']);
        $this->assertSame(1, $analytics['heatmap'][0]['management']['peopleCount']);
        $this->assertSame(0, $analytics['heatmap'][0]['academic']['peopleCount']);
        $this->assertSame(0, $analytics['heatmap'][0]['support']['peopleCount']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FacultyIdpPlanTest extends TestCase
{
    use RefreshDatabase;

    private int $roundId;

    private User $employee;

    private User $reviewer;

    /** @var array<string, int> competency id by code */
    private array $competencyIds = [];

    /** @var array<string, int> gap id by competency code */
    private array $gapIds = [];

    private int $toolId;
    private int $titleId;
    private int $positionId;

    protected function setUp(): void
    {
        parent::setUp();
        app(\App\Services\IdpFillMode::class)->save('form');
        Mail::fake();

        $this->roundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบวิธีคณะ',
            'year' => 2569,
            'assessment_method' => 'faculty',
            'assessment_method_version' => 1,
            'is_active' => true,
            'snapshot_required' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->reviewer = User::factory()->create(['role_id' => $this->roleId('supervisor')]);
        $this->employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $this->titleId = DB::table('position_titles')->insertGetId(['workline_type' => 'academic', 'name' => 'QA Faculty IDP', 'normalized_name' => 'qa-faculty-idp']);
        $worklineId = DB::table('worklines')->insertGetId(['name' => 'QA faculty line']);
        $familyId = DB::table('job_families')->insertGetId(['workline_id' => $worklineId, 'name' => 'QA faculty family']);
        $this->positionId = DB::table('positions')->insertGetId(['job_family_id' => $familyId, 'position_title_id' => $this->titleId, 'name' => 'QA faculty position']);
        $this->employee->forceFill(['position_title_id' => $this->titleId, 'position_id' => $this->positionId])->save();
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $this->employee->id,
            'reviewer_id' => $this->reviewer->id,
            'step_order' => 1,
            'chain_type' => 'idp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // CC-A 2/4 = 50% and CC-B 3/4 = 75% need IDP; CC-C 4/5 = 80% does not.
        $this->gap('CC-A', 2, 4, true);
        $this->gap('CC-B', 3, 4, true);
        $this->gap('CC-C', 4, 5, false);

        DB::table('learning_method_types')->insert([
            ['key' => 'experiential-learning', 'label' => 'Experiential Learning', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'formal-learning', 'label' => 'Formal Learning', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->toolId = DB::table('idp_learning_methods')->insertGetId([
            'focus_type' => 'experiential',
            'title' => 'Project Assignment',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_one_plan_covers_every_failed_competency_and_is_submitted_once(): void
    {
        // The client points at CC-B but the server anchors the plan and covers both failed gaps.
        $this->actingAs($this->employee)
            ->post(route('employee.idp.submit-item'), ['item' => $this->plan($this->gapIds['CC-B'], [60, 40])])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('idp_items')->count());
        $item = DB::table('idp_items')->first();
        $this->assertSame('faculty-plan', $item->behavior_key);
        $this->assertSame('review_step_1', $item->status);
        $this->assertSame(2, DB::table('idp_activities')->where('idp_item_id', $item->id)->count());
        $this->assertEqualsCanonicalizing(
            [$this->gapIds['CC-A'], $this->gapIds['CC-B']],
            DB::table('idp_item_competency_gaps')->where('idp_item_id', $item->id)->pluck('competency_gap_id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    public function test_activity_weights_must_total_one_hundred_percent_per_plan(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.idp.submit-item'), ['item' => $this->plan($this->gapIds['CC-A'], [60, 30])])
            ->assertSessionHasErrors(['items.0.activities' => 'น้ำหนักกิจกรรมในแผนต้องรวม 100%']);
    }

    public function test_faculty_round_accepts_only_one_plan_per_person(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.idp.draft'), ['items' => [
                $this->plan($this->gapIds['CC-A'], [100]),
                $this->plan($this->gapIds['CC-B'], [100]),
            ]])
            ->assertSessionHasErrors('items');
    }

    public function test_formal_course_may_come_from_any_failed_competency_but_not_a_passed_one(): void
    {
        $courseForB = $this->catalog('หลักสูตรทีม', 'CC-B');
        $courseForPassed = $this->catalog('หลักสูตรที่ผ่านแล้ว', 'CC-C');

        $this->actingAs($this->employee)
            ->post(route('employee.idp.draft'), ['items' => [$this->formalPlan($courseForB)]])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->employee)
            ->post(route('employee.idp.draft'), ['items' => [$this->formalPlan($courseForPassed)]])
            ->assertSessionHasErrors('items.0.activities.0.learningCatalogId');
    }

    public function test_formal_course_in_faculty_plan_keeps_only_course_name_and_description(): void
    {
        $courseId = $this->catalog('หลักสูตรทีม', 'CC-B');
        DB::table('learning_catalogs')->where('id', $courseId)->update([
            'code' => 'TEAM-01',
            'description' => 'ฝึกการทำงานเป็นทีม',
            'hours' => 6,
            'cost' => 1500,
        ]);

        // Submitted without the training form: no dates, goal, hours or cost.
        $this->actingAs($this->employee)
            ->post(route('employee.idp.submit-item'), ['item' => $this->formalPlan($courseId)])
            ->assertSessionHasNoErrors();

        $activity = DB::table('idp_activities')->first();
        $this->assertSame('หลักสูตรทีม', $activity->activity_name);
        $this->assertSame('ฝึกการทำงานเป็นทีม', $activity->description);
        $this->assertNull($activity->start_date);
        $this->assertSame(
            [['courseName' => 'หลักสูตรทีม', 'courseDescription' => 'ฝึกการทำงานเป็นทีม']],
            json_decode($activity->form_details, true)['planRows'],
        );
    }

    public function test_behavior_selections_survive_faculty_course_normalization_and_reload(): void
    {
        app(\App\Services\IdpFillMode::class)->save('quick');
        $courseId = $this->catalog('หลักสูตรทีม', 'CC-B');
        $plan = $this->formalPlan($courseId);
        $plan['activities'][0]['formDetails'] = [
            '_behaviorPlan' => true, '_saved' => true,
            'behaviors' => [['key' => 'team-1', 'gapId' => $this->gapIds['CC-B'], 'level' => 1, 'code' => '1.2', 'description' => 'ทำงานร่วมกับทีม']],
        ];
        $this->actingAs($this->employee)->postJson(route('employee.idp.draft'), ['items' => [$plan]])->assertOk();
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('currentUserIdp.items.0.activities.0.formDetails._behaviorPlan', true)
            ->where('currentUserIdp.items.0.activities.0.formDetails.behaviors.0.gapId', $this->gapIds['CC-B']));
        $this->post(route('employee.idp.submit-item'), ['item' => $plan])->assertSessionHasNoErrors();
    }

    public function test_complete_formal_draft_edits_reload_with_all_activities_and_failed_gaps(): void
    {
        $courseId = $this->catalog('หลักสูตรทีม', 'CC-B');
        $plan = $this->formalPlan($courseId);
        $this->actingAs($this->employee)->postJson(route('employee.idp.draft'), ['items' => [$plan]])->assertOk();

        $plan['goal'] = 'เป้าหมายฉบับแก้ไข';
        $plan['successCriteria'] = 'เกณฑ์ฉบับแก้ไข';
        $plan['activities'][0]['weightPercent'] = 60;
        $plan['activities'][] = [...$plan['activities'][0], 'weightPercent' => 40];
        $this->postJson(route('employee.idp.draft'), ['items' => [$plan]])->assertOk();

        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('currentUserIdp.items', 1)
            ->where('currentUserIdp.items.0.status', 'draft')
            ->where('currentUserIdp.items.0.goal', 'เป้าหมายฉบับแก้ไข')
            ->where('currentUserIdp.items.0.successCriteria', 'เกณฑ์ฉบับแก้ไข')
            ->has('currentUserIdp.items.0.activities', 2)
            ->where('currentUserIdp.items.0.activities.0.activityName', 'หลักสูตรทีม')
            ->where('currentUserIdp.items.0.activities.0.weightPercent', 60)
            ->where('currentUserIdp.items.0.activities.1.weightPercent', 40));
        $this->assertDatabaseCount('idp_items', 1);
        $this->assertDatabaseCount('idp_item_competency_gaps', 2);
    }

    public function test_reviewer_sees_the_plan_labelled_with_all_covered_competencies(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.idp.submit-item'), ['item' => $this->plan($this->gapIds['CC-A'], [100])])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->reviewer)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('idpReviewItems.0.competencyCode', '')
                ->where('idpReviewItems.0.competencyName', 'แผนพัฒนารายบุคคล · สมรรถนะ CC-A, สมรรถนะ CC-B')
                ->has('idpReviewItems.0.planCompetencies', 2)
            );
    }

    private function gap(string $code, int $actual, int $expected, bool $requiresIdp): void
    {
        $typeId = DB::table('competency_types')->insertGetId([
            'code' => $code.'-T', 'full_name' => 'CC', 'description' => 'CC', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $competencyId = DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId, 'code' => $code, 'name' => "สมรรถนะ {$code}", 'created_at' => now(), 'updated_at' => now(),
        ]);
        $assessmentId = DB::table('assessments')->insertGetId([
            'assessment_round_id' => $this->roundId,
            'user_id' => $this->employee->id,
            'competency_id' => $competencyId,
            'score' => $actual,
            'status' => 'approved',
            'last_draft_saved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->competencyIds[$code] = $competencyId;
        DB::table('position_competencies')->insert(['assessment_round_id' => $this->roundId, 'position_title_id' => $this->titleId, 'position_id' => $this->positionId, 'competency_id' => $competencyId]);
        $this->gapIds[$code] = DB::table('competency_gaps')->insertGetId([
            'assessment_id' => $assessmentId,
            'competency_id' => $competencyId,
            'expected_level' => $expected,
            'actual_level' => $actual,
            'gap' => $actual - $expected,
            'requires_idp' => $requiresIdp,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_employee_can_type_their_own_other_development_method(): void
    {
        $plan = $this->otherPlan('  เข้าร่วมเวทีแลกเปลี่ยนเรียนรู้ของคณะ  ');
        $this->actingAs($this->employee)
            ->post(route('employee.idp.submit-item'), ['item' => $plan])
            ->assertSessionHasNoErrors();

        $activity = DB::table('idp_activities')->first();
        $this->assertSame('เข้าร่วมเวทีแลกเปลี่ยนเรียนรู้ของคณะ', $activity->activity_name);
        $this->assertNull($activity->idp_learning_method_id);
        $this->assertNull($activity->learning_catalog_id);
        $this->assertSame(
            DB::table('learning_method_types')->where('key', 'other-learning')->value('id'),
            $activity->method_type_id,
        );
    }

    public function test_other_development_method_needs_a_name_and_no_tool(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.idp.submit-item'), ['item' => $this->otherPlan('   ')])
            ->assertSessionHasErrors('items.0.activities.0.activityName');

        $withTool = $this->otherPlan('อ่านหนังสือ');
        $withTool['activities'][0]['developmentToolId'] = $this->toolId;
        $this->post(route('employee.idp.submit-item'), ['item' => $withTool])
            ->assertSessionHasErrors('items.0.activities.0.methodKey');
        $this->assertSame(0, DB::table('idp_activities')->count());
    }

    private function otherPlan(string $name): array
    {
        return [
            'competencyGapId' => $this->gapIds['CC-A'],
            'goal' => 'พัฒนาสมรรถนะ',
            'successCriteria' => 'ผ่านเกณฑ์',
            'activities' => [[
                'methodKey' => 'other-learning',
                'developmentToolId' => null,
                'learningCatalogId' => null,
                'activityName' => $name,
                'weightPercent' => 100,
            ]],
        ];
    }

    private function plan(int $gapId, array $weights): array
    {
        return [
            'competencyGapId' => $gapId,
            'goal' => 'พัฒนาสมรรถนะที่ต่ำกว่าเกณฑ์',
            'successCriteria' => 'ผลประเมินรอบถัดไปไม่ต่ำกว่า 80%',
            'activities' => array_map(fn (int $weight): array => [
                'methodKey' => 'experiential-learning',
                'developmentToolId' => $this->toolId,
                'learningCatalogId' => null,
                'weightPercent' => $weight,
                'startDate' => '2026-12-01',
                'endDate' => '2027-01-31',
            ], $weights),
        ];
    }

    private function formalPlan(int $catalogId): array
    {
        return [
            'competencyGapId' => $this->gapIds['CC-A'],
            'goal' => 'พัฒนาสมรรถนะ',
            'successCriteria' => 'ผ่านเกณฑ์',
            'activities' => [[
                'methodKey' => 'formal-learning',
                'learningCatalogId' => $catalogId,
                'weightPercent' => 100,
            ]],
        ];
    }

    private function catalog(string $name, string $competencyCode): int
    {
        $catalogId = DB::table('learning_catalogs')->insertGetId([
            'name' => $name,
            'method_type_id' => DB::table('learning_method_types')->where('key', 'formal-learning')->value('id'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('learning_catalog_competency')->insert([
            'learning_catalog_id' => $catalogId,
            'competency_id' => $this->competencyIds[$competencyCode],
        ]);

        return $catalogId;
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PositionTitleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FcUnitRuleTest extends TestCase
{
    use RefreshDatabase;

    private int $roundId;

    private int $positionTitleId;

    /** @var array{a: int, b: int} */
    private array $positionIds;

    /** @var array{a: int, b: int} */
    private array $unitIds;

    /** @var array<int, int> */
    private array $fcIds;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->buildSupportPositionInTwoUnits();
    }

    public function test_hr_can_override_and_reset_the_fc_count_for_one_unit(): void
    {
        $hr = User::factory()->create(['role_id' => $this->roleId('hr')]);
        $payload = [
            'position_id' => $this->positionIds['b'],
            'assessment_round_id' => $this->roundId,
            'support_unit_id' => $this->unitIds['b'],
        ];

        $this->actingAs($hr)
            ->put(route('hr.position-fc-unit-rules.update'), $payload + ['required_fc_count' => 3])
            ->assertSessionHasErrors('unit_required_fc_count');

        $this->actingAs($hr)
            ->put(route('hr.position-fc-unit-rules.update'), $payload + ['required_fc_count' => 1])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('position_fc_unit_rules', [
            'assessment_round_id' => $this->roundId,
            'position_title_id' => $this->positionTitleId,
            'support_unit_id' => $this->unitIds['b'],
            'required_fc_count' => 1,
        ]);

        $this->actingAs($hr)
            ->delete(route('hr.position-fc-unit-rules.destroy'), $payload)
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('position_fc_unit_rules', 0);
    }

    public function test_unit_rule_overrides_the_position_count_only_for_that_unit(): void
    {
        $this->unitRule($this->unitIds['b'], 1);
        $employeeA = $this->employeeIn('a');
        $employeeB = $this->employeeIn('b');

        $this->actingAs($employeeA)
            ->post(route('employee.fc-topic-selection.submit'), ['competency_ids' => [$this->fcIds[0]]])
            ->assertSessionHasErrors('competency_ids');
        $this->actingAs($employeeB)
            ->post(route('employee.fc-topic-selection.submit'), ['competency_ids' => [$this->fcIds[0]]])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('fc_topic_selections', ['user_id' => $employeeB->id, 'status' => 'submitted']);
        $this->assertDatabaseMissing('fc_topic_selections', ['user_id' => $employeeA->id]);
    }

    public function test_unit_rule_and_organization_follow_users_department_on_a_global_support_position(): void
    {
        $this->unitRule($this->unitIds['b'], 1);
        $globalPositionId = DB::table('positions')->insertGetId([
            'job_family_id' => DB::table('positions')->where('id', $this->positionIds['a'])->value('job_family_id'),
            'support_unit_id' => null,
            'position_title_id' => $this->positionTitleId,
            'name' => 'นักวิชาการคอมพิวเตอร์',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $row = fn (int $id, ?string $department): object => (object) [
            'id' => $id, 'position_id' => $globalPositionId, 'position_title_id' => $this->positionTitleId, 'department' => $department,
        ];

        $counts = app(\App\Services\FcSelectionRuleResolver::class)->requiredCountsForUsers(collect([
            $row(1, 'ฝ่ายทดสอบ > งานทดสอบ > หน่วย b'),
            $row(2, 'ฝ่ายทดสอบ > งานทดสอบ > หน่วย a'),
            $row(3, 'ฝ่ายทดสอบ > งานทดสอบ > หน่วยที่ไม่มีจริง'),
        ]), $this->roundId);
        $this->assertSame([1 => 1, 2 => 2, 3 => 2], $counts->all());

        $user = User::factory()->create([
            'workline' => 'สายสนับสนุน',
            'department' => 'ฝ่ายทดสอบ > งานทดสอบ > หน่วย b',
            'position_id' => $globalPositionId,
        ]);
        $organization = app(\App\Services\UserOrganizationPresenter::class)->canonicalOrUnassignedForUser($user);
        $this->assertSame('support', $organization['type']);
        $this->assertSame('หน่วย b', $organization['unitName']);
        $this->assertSame('งานทดสอบ · หน่วย b', $organization['display']);

        $user->forceFill(['department' => 'ฝ่ายทดสอบ > งานทดสอบ > หน่วยที่ไม่มีจริง'])->save();
        $organization = app(\App\Services\UserOrganizationPresenter::class)->canonicalOrUnassignedForUser($user->fresh());
        $this->assertSame('งานทดสอบ', $organization['workName']);
        $this->assertNull($organization['unitName']);
    }

    public function test_unit_rule_of_zero_turns_off_fc_preselection_for_that_unit(): void
    {
        $this->unitRule($this->unitIds['b'], 0);
        $employeeB = $this->employeeIn('b');

        $this->actingAs($employeeB)
            ->post(route('employee.fc-topic-selection.submit'), ['competency_ids' => [$this->fcIds[0]]])
            ->assertSessionHasErrors('competency_ids');
    }

    public function test_importing_another_round_copies_unit_rules(): void
    {
        $this->unitRule($this->unitIds['b'], 1);
        $hr = User::factory()->create(['role_id' => $this->roleId('hr')]);
        $targetRoundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบถัดไป',
            'year' => 2570,
            'is_active' => false,
            'snapshot_required' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($hr)
            ->post(route('hr.position-competencies.copy-round'), [
                'source_round_id' => $this->roundId,
                'target_round_id' => $targetRoundId,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('position_fc_unit_rules', [
            'assessment_round_id' => $targetRoundId,
            'position_title_id' => $this->positionTitleId,
            'support_unit_id' => $this->unitIds['b'],
            'required_fc_count' => 1,
        ]);
    }

    private function buildSupportPositionInTwoUnits(): void
    {
        $now = now();
        $this->roundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบทดสอบ FC หน่วย',
            'year' => 2569,
            'self_assess_start' => $now->copy()->subMonth()->toDateString(),
            'self_assess_end' => $now->copy()->addMonth()->toDateString(),
            'supervisor_assess_end' => $now->copy()->addMonths(2)->toDateString(),
            'is_active' => true,
            'snapshot_required' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $worklineId = DB::table('worklines')->insertGetId(['name' => 'สายสนับสนุน', 'created_at' => $now, 'updated_at' => $now]);
        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId, 'name' => 'งานทดสอบ', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $departmentId = DB::table('support_departments')->insertGetId(['name' => 'ฝ่ายทดสอบ', 'created_at' => $now, 'updated_at' => $now]);
        $workId = DB::table('support_works')->insertGetId([
            'support_department_id' => $departmentId, 'name' => 'งานทดสอบ', 'created_at' => $now, 'updated_at' => $now,
        ]);

        foreach (['a', 'b'] as $key) {
            $this->unitIds[$key] = DB::table('support_units')->insertGetId([
                'support_work_id' => $workId, 'name' => "หน่วย {$key}", 'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->positionIds[$key] = DB::table('positions')->insertGetId([
                'job_family_id' => $jobFamilyId,
                'support_unit_id' => $this->unitIds[$key],
                'name' => 'นักวิชาการคอมพิวเตอร์',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $fcTypeId = DB::table('competency_types')->insertGetId([
            'code' => 'FC1', 'full_name' => 'FC1', 'description' => 'FC1', 'created_at' => $now, 'updated_at' => $now,
        ]);
        foreach ([1, 2] as $index) {
            $competencyId = DB::table('competencies')->insertGetId([
                'competency_type_id' => $fcTypeId,
                'code' => "FC1-UNIT-0{$index}",
                'name' => "FC หน่วย {$index}",
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->fcIds[] = $competencyId;
            DB::table('position_competencies')->insert([
                'assessment_round_id' => $this->roundId,
                'position_id' => $this->positionIds['a'],
                'competency_id' => $competencyId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        DB::table('position_fc_selection_rules')->insert([
            'assessment_round_id' => $this->roundId,
            'position_id' => $this->positionIds['a'],
            'required_fc_count' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $titles = app(PositionTitleResolver::class);
        $this->positionTitleId = (int) $titles->forPositionId($this->positionIds['a']);
        $this->assertSame($this->positionTitleId, $titles->forPositionId($this->positionIds['b']));
    }

    private function unitRule(int $unitId, int $count): void
    {
        DB::table('position_fc_unit_rules')->insert([
            'assessment_round_id' => $this->roundId,
            'position_title_id' => $this->positionTitleId,
            'support_unit_id' => $unitId,
            'required_fc_count' => $count,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function employeeIn(string $unit): User
    {
        $reviewer = User::factory()->create(['role_id' => $this->roleId('supervisor')]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'workline' => 'สายสนับสนุน',
            'position' => 'นักวิชาการคอมพิวเตอร์',
            'position_id' => $this->positionIds[$unit],
            'position_title_id' => $this->positionTitleId,
            'is_active' => true,
        ]);
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employee->id,
            'reviewer_id' => $reviewer->id,
            'step_order' => 1,
            'chain_type' => 'assessment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $employee;
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }
}

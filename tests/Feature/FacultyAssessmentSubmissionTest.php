<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FacultyAssessmentSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_faculty_method_ignores_client_score_and_calculates_from_checked_behaviors(): void
    {
        $reviewer = User::factory()->create(['role_id' => $this->roleId('supervisor')]);
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $roundId = $this->activeFacultyRound();
        [$competencyId, $levelIds] = $this->makeAssessable($employee, $roundId);

        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employee->id,
            'chain_type' => 'assessment',
            'step_order' => 1,
            'reviewer_id' => $reviewer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $checked = [
            "{$competencyId}:{$levelIds[1]}:0" => true,
            "{$competencyId}:{$levelIds[1]}:1" => true,
            "{$competencyId}:{$levelIds[1]}:2" => true,
            "{$competencyId}:{$levelIds[3]}:0" => true,
        ];

        $this->actingAs($employee)
            ->postJson(route('assessments.draft'), [
                'competency_id' => $competencyId,
                'checked_indicators' => $checked,
                'score' => 99,
                'note' => 'คะแนนต้องมาจากผลติ๊ก',
            ])
            ->assertOk();

        $assessment = DB::table('assessments')
            ->where('assessment_round_id', $roundId)
            ->where('user_id', $employee->id)
            ->where('competency_id', $competencyId)
            ->first();

        $this->assertNotNull($assessment);
        $this->assertSame(3.0, (float) $assessment->score);
        $this->assertDatabaseHas('competency_gaps', [
            'assessment_id' => $assessment->id,
            'competency_id' => $competencyId,
            'actual_level' => 3,
            'gap' => 0,
        ]);
    }

    public function test_faculty_method_rejects_a_forged_indicator_index(): void
    {
        $reviewer = User::factory()->create(['role_id' => $this->roleId('supervisor')]);
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $roundId = $this->activeFacultyRound();
        [$competencyId, $levelIds] = $this->makeAssessable($employee, $roundId);

        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employee->id,
            'chain_type' => 'assessment',
            'step_order' => 1,
            'reviewer_id' => $reviewer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($employee)
            ->postJson(route('assessments.draft'), [
                'competency_id' => $competencyId,
                'checked_indicators' => ["{$competencyId}:{$levelIds[1]}:99" => true],
                'score' => 5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('checked_indicators');

        $this->assertDatabaseMissing('assessments', [
            'assessment_round_id' => $roundId,
            'user_id' => $employee->id,
            'competency_id' => $competencyId,
        ]);
    }

    public function test_authorized_academic_head_score_becomes_actual_level_while_self_score_is_kept(): void
    {
        $reviewer = User::factory()->create(['role_id' => $this->roleId('academic_department_head')]);
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $roundId = $this->activeFacultyRound();
        [$competencyId, $levelIds] = $this->makeAssessable($employee, $roundId);
        $this->assignReviewer($employee, $reviewer);
        $assessmentId = $this->submittedAssessment($employee, $roundId, $competencyId, 2);
        $checked = [
            "{$competencyId}:{$levelIds[1]}:0",
            "{$competencyId}:{$levelIds[1]}:1",
            "{$competencyId}:{$levelIds[1]}:2",
            "{$competencyId}:{$levelIds[3]}:0",
            "{$competencyId}:{$levelIds[3]}:1",
            "{$competencyId}:{$levelIds[3]}:2",
        ];

        $this->actingAs($reviewer)->post(route('assessments.approve'), [
            'user_id' => $employee->id,
            'competency_id' => $competencyId,
            'checked' => $checked,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', ['id' => $assessmentId, 'score' => 2, 'status' => 'approved']);
        $this->assertDatabaseHas('scores', [
            'assessment_id' => $assessmentId,
            'assessor_id' => $reviewer->id,
            'score' => 4,
            'status' => 'approved',
        ]);
        $this->assertSame(6, DB::table('assessment_reviewer_indicator_results')
            ->where('assessment_id', $assessmentId)
            ->where('reviewer_id', $reviewer->id)
            ->count());
        $this->assertDatabaseHas('competency_gaps', [
            'assessment_id' => $assessmentId,
            'actual_level' => 4,
            'gap' => 1,
            'requires_idp' => false,
            'status' => 'approved',
        ]);
    }

    public function test_later_approval_only_step_keeps_the_authorized_reviewer_score(): void
    {
        $academicHead = User::factory()->create(['role_id' => $this->roleId('academic_department_head')]);
        $assistantDean = User::factory()->create(['role_id' => $this->roleId('assistant_dean')]);
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $roundId = $this->activeFacultyRound();
        [$competencyId, $levelIds] = $this->makeAssessable($employee, $roundId);
        $this->assignReviewer($employee, $academicHead, 1);
        $this->assignReviewer($employee, $assistantDean, 2);
        $assessmentId = $this->submittedAssessment($employee, $roundId, $competencyId, 2);

        $this->actingAs($academicHead)->post(route('assessments.approve'), [
            'user_id' => $employee->id,
            'competency_id' => $competencyId,
            'checked' => [
                "{$competencyId}:{$levelIds[1]}:0",
                "{$competencyId}:{$levelIds[1]}:1",
                "{$competencyId}:{$levelIds[1]}:2",
                "{$competencyId}:{$levelIds[3]}:0",
                "{$competencyId}:{$levelIds[3]}:1",
                "{$competencyId}:{$levelIds[3]}:2",
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($assistantDean)->post(route('assessments.approve'), [
            'user_id' => $employee->id,
            'competency_id' => $competencyId,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', ['id' => $assessmentId, 'score' => 2, 'status' => 'approved']);
        $this->assertDatabaseHas('competency_gaps', [
            'assessment_id' => $assessmentId,
            'actual_level' => 4,
            'gap' => 1,
            'requires_idp' => false,
            'status' => 'approved',
        ]);
    }

    public function test_dashboard_shows_self_score_and_faculty_missing_behaviors_from_reviewer_result(): void
    {
        $reviewer = User::factory()->create(['role_id' => $this->roleId('academic_department_head')]);
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $roundId = $this->activeFacultyRound();
        [$competencyId, $levelIds] = $this->makeAssessable($employee, $roundId);
        $this->assignReviewer($employee, $reviewer);
        $this->submittedAssessment($employee, $roundId, $competencyId, 4);

        $this->actingAs($reviewer)->post(route('assessments.approve'), [
            'user_id' => $employee->id,
            'competency_id' => $competencyId,
            'checked' => [
                "{$competencyId}:{$levelIds[1]}:0",
                "{$competencyId}:{$levelIds[1]}:1",
                "{$competencyId}:{$levelIds[1]}:2",
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($employee)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentUserCompetencyGaps.0.selfScore', 4)
                ->where('currentUserCompetencyGaps.0.actual', 2)
                ->where('currentUserCompetencyGaps.0.requiresIdp', true)
                ->where('currentUserCompetencyGaps.0.missingIndicatorCount', 3)
                ->where('currentUserCompetencyGaps.0.missingIndicators.0.level', 3)
            );
    }

    public function test_approval_only_role_cannot_override_faculty_score_through_http_payload(): void
    {
        $reviewer = User::factory()->create(['role_id' => $this->roleId('assistant_dean')]);
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $roundId = $this->activeFacultyRound();
        [$competencyId, $levelIds] = $this->makeAssessable($employee, $roundId);
        $this->assignReviewer($employee, $reviewer);
        $assessmentId = $this->submittedAssessment($employee, $roundId, $competencyId, 2);

        $this->actingAs($reviewer)->post(route('assessments.approve'), [
            'user_id' => $employee->id,
            'competency_id' => $competencyId,
            'checked' => [
                "{$competencyId}:{$levelIds[1]}:0",
                "{$competencyId}:{$levelIds[1]}:1",
                "{$competencyId}:{$levelIds[1]}:2",
                "{$competencyId}:{$levelIds[3]}:0",
                "{$competencyId}:{$levelIds[3]}:1",
                "{$competencyId}:{$levelIds[3]}:2",
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', ['id' => $assessmentId, 'score' => 2, 'status' => 'approved']);
        $this->assertDatabaseHas('scores', [
            'assessment_id' => $assessmentId,
            'assessor_id' => $reviewer->id,
            'score' => null,
            'status' => 'approved',
        ]);
        $this->assertDatabaseMissing('assessment_reviewer_indicator_results', [
            'assessment_id' => $assessmentId,
            'reviewer_id' => $reviewer->id,
        ]);
    }

    private function activeFacultyRound(): int
    {
        return (int) DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบวิธีคณะ',
            'year' => 2569,
            'assessment_method' => 'faculty',
            'assessment_method_version' => 1,
            'self_assess_start' => now()->subDay()->toDateString(),
            'self_assess_end' => now()->addMonth()->toDateString(),
            'supervisor_assess_end' => now()->addMonths(2)->toDateString(),
            'is_active' => true,
            'snapshot_required' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeAssessable(User $employee, int $roundId): array
    {
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายวิชาการ', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'ภาควิชาทดสอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $positionId = DB::table('positions')->insertGetId([
            'job_family_id' => $jobFamilyId,
            'name' => 'อาจารย์',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $levelId = DB::table('levels')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'ระดับวิชาการ',
            'expected_level' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $typeId = DB::table('competency_types')->insertGetId([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'Core Competency',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $competencyId = DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId,
            'code' => 'CC-FACULTY-01',
            'name' => 'สมรรถนะวิธีคณะ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $levelIds = [];
        foreach ([1, 3, 5] as $level) {
            $competencyLevelId = DB::table('competency_levels')->insertGetId([
                'competency_id' => $competencyId,
                'level' => $level,
                'description' => "เกณฑ์ระดับ {$level}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $levelIds[$level] = $competencyLevelId;

            foreach (range(1, 3) as $indicator) {
                DB::table('comp_level_indicators')->insert([
                    'competency_level_id' => $competencyLevelId,
                    'description' => "พฤติกรรม {$level}.{$indicator}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('position_competencies')->insert([
            'assessment_round_id' => $roundId,
            'position_id' => $positionId,
            'competency_id' => $competencyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $employee->forceFill([
            'workline' => 'สายวิชาการ',
            'department' => 'ภาควิชาทดสอบ',
            'position' => 'อาจารย์',
            'level' => 'ระดับวิชาการ',
            'position_id' => $positionId,
            'level_id' => $levelId,
        ])->save();

        return [$competencyId, $levelIds];
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }

    private function assignReviewer(User $employee, User $reviewer, int $step = 1): void
    {
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employee->id,
            'chain_type' => 'assessment',
            'step_order' => $step,
            'reviewer_id' => $reviewer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function submittedAssessment(User $employee, int $roundId, int $competencyId, int $score): int
    {
        $assessmentId = DB::table('assessments')->insertGetId([
            'assessment_round_id' => $roundId,
            'user_id' => $employee->id,
            'competency_id' => $competencyId,
            'score' => $score,
            'status' => 'self_submitted',
            'last_draft_saved_at' => now(),
            'self_submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('competency_gaps')->insert([
            'assessment_id' => $assessmentId,
            'competency_id' => $competencyId,
            'expected_level' => 3,
            'actual_level' => $score,
            'gap' => $score - 3,
            'requires_idp' => $score < 3,
            'status' => 'self_submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $assessmentId;
    }
}

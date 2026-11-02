<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\BuildsFacultyAssessment;
use Tests\TestCase;

class FacultyAssessmentSubmissionTest extends TestCase
{
    use BuildsFacultyAssessment;
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
}

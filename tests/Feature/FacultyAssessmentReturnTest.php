<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsFacultyAssessment;
use Tests\TestCase;

class FacultyAssessmentReturnTest extends TestCase
{
    use BuildsFacultyAssessment;
    use RefreshDatabase;

    public function test_later_approver_returns_support_result_to_the_deputy_dean_instead_of_the_employee(): void
    {
        $deputy = User::factory()->create(['role_id' => $this->roleId('deputy_dean')]);
        $assistant = User::factory()->create(['role_id' => $this->roleId('assistant_dean')]);
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $roundId = $this->activeFacultyRound();
        [$competencyId, $levelIds] = $this->makeAssessable($employee, $roundId, 'สายสนับสนุน');
        $this->assignReviewer($employee, $deputy, 1);
        $this->assignReviewer($employee, $assistant, 2);
        $assessmentId = $this->submittedAssessment($employee, $roundId, $competencyId, 2);

        $this->approve($deputy, $employee, $competencyId, $this->levelThreeKeys($competencyId, $levelIds));
        $this->assertDatabaseHas('assessments', ['id' => $assessmentId, 'status' => 'unit_evaluated']);

        $this->actingAs($assistant)
            ->post(route('assessments.reject'), [
                'user_id' => $employee->id,
                'competency_id' => $competencyId,
                'comment' => 'ขอให้ทบทวนผลประเมินใหม่',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', ['id' => $assessmentId, 'score' => 2, 'status' => 'self_submitted']);
        $this->assertDatabaseHas('competency_gaps', [
            'assessment_id' => $assessmentId,
            'status' => 'self_submitted',
            'rejected_by' => $assistant->id,
            'reject_comment' => 'ขอให้ทบทวนผลประเมินใหม่',
        ]);

        // The deputy re-scores; the employee is never reopened for editing.
        $this->actingAs($employee)
            ->postJson(route('assessments.draft'), [
                'competency_id' => $competencyId,
                'checked_indicators' => [],
                'score' => 0,
            ])
            ->assertUnprocessable();
        $this->approve($deputy, $employee, $competencyId, $this->levelThreeKeys($competencyId, $levelIds, full: true));
        $this->approve($assistant, $employee, $competencyId);

        $this->assertDatabaseHas('competency_gaps', [
            'assessment_id' => $assessmentId,
            'actual_level' => 4,
            'status' => 'approved',
        ]);
    }

    public function test_result_returns_to_employee_when_no_scorer_precedes_the_rejecting_reviewer(): void
    {
        $deputy = User::factory()->create(['role_id' => $this->roleId('deputy_dean')]);
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $roundId = $this->activeFacultyRound();
        [$competencyId] = $this->makeAssessable($employee, $roundId, 'สายสนับสนุน');
        $this->assignReviewer($employee, $deputy, 1);
        $assessmentId = $this->submittedAssessment($employee, $roundId, $competencyId, 2);

        $this->actingAs($deputy)
            ->post(route('assessments.reject'), [
                'user_id' => $employee->id,
                'competency_id' => $competencyId,
                'comment' => 'กรุณาประเมินตนเองใหม่',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', ['id' => $assessmentId, 'status' => 'revision_required']);
    }

    public function test_every_deputy_dean_in_the_chain_scores_and_the_latest_score_wins(): void
    {
        $firstDeputy = User::factory()->create(['role_id' => $this->roleId('deputy_dean')]);
        $lastDeputy = User::factory()->create(['role_id' => $this->roleId('deputy_dean')]);
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $roundId = $this->activeFacultyRound();
        [$competencyId, $levelIds] = $this->makeAssessable($employee, $roundId, 'สายสนับสนุน');
        $this->assignReviewer($employee, $firstDeputy, 1);
        $this->assignReviewer($employee, $lastDeputy, 2);
        $assessmentId = $this->submittedAssessment($employee, $roundId, $competencyId, 2);

        $this->approve($firstDeputy, $employee, $competencyId, $this->levelThreeKeys($competencyId, $levelIds, full: true));
        $this->assertDatabaseHas('scores', [
            'assessment_id' => $assessmentId,
            'assessor_id' => $firstDeputy->id,
            'score' => 4,
            'status' => 'approved',
        ]);

        $this->travel(1)->minute();
        $this->approve($lastDeputy, $employee, $competencyId, $this->levelThreeKeys($competencyId, $levelIds));

        $this->assertDatabaseHas('competency_gaps', [
            'assessment_id' => $assessmentId,
            'actual_level' => 3,
            'requires_idp' => false,
            'status' => 'approved',
        ]);
    }

    public function test_second_deputy_dean_returns_result_to_the_first_deputy_dean(): void
    {
        $firstDeputy = User::factory()->create(['role_id' => $this->roleId('deputy_dean')]);
        $lastDeputy = User::factory()->create(['role_id' => $this->roleId('deputy_dean')]);
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $roundId = $this->activeFacultyRound();
        [$competencyId, $levelIds] = $this->makeAssessable($employee, $roundId, 'สายสนับสนุน');
        $this->assignReviewer($employee, $firstDeputy, 1);
        $this->assignReviewer($employee, $lastDeputy, 2);
        $assessmentId = $this->submittedAssessment($employee, $roundId, $competencyId, 2);

        $this->approve($firstDeputy, $employee, $competencyId, $this->levelThreeKeys($competencyId, $levelIds));
        $this->actingAs($lastDeputy)
            ->post(route('assessments.reject'), [
                'user_id' => $employee->id,
                'competency_id' => $competencyId,
                'comment' => 'ขอให้ทบทวนพฤติกรรมระดับ 3',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', ['id' => $assessmentId, 'status' => 'self_submitted']);
    }

    private function approve(User $reviewer, User $employee, int $competencyId, ?array $checked = null): void
    {
        $this->actingAs($reviewer)
            ->post(route('assessments.approve'), array_filter([
                'user_id' => $employee->id,
                'competency_id' => $competencyId,
                'checked' => $checked,
            ], fn ($value): bool => $value !== null))
            ->assertSessionHasNoErrors();
    }

    private function levelThreeKeys(int $competencyId, array $levelIds, bool $full = false): array
    {
        $keys = array_map(fn (int $index): string => "{$competencyId}:{$levelIds[1]}:{$index}", [0, 1, 2]);
        foreach ($full ? [0, 1, 2] : [0] as $index) {
            $keys[] = "{$competencyId}:{$levelIds[3]}:{$index}";
        }

        return $keys;
    }
}

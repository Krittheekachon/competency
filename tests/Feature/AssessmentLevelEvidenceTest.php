<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsFacultyAssessment;
use Tests\TestCase;

class AssessmentLevelEvidenceTest extends TestCase
{
    use BuildsFacultyAssessment;
    use RefreshDatabase;

    private int $roundId;

    public function test_self_level_five_cannot_be_submitted_without_evidence(): void
    {
        [$employee, $competencyId, $levelIds] = $this->employeeWithFacultyCompetency();
        $this->saveDraft($employee, $competencyId, $this->levelFiveKeys($competencyId, $levelIds));

        $this->actingAs($employee)
            ->post(route('assessments.save'), [
                'competency_id' => $competencyId,
                'checked_indicators' => $this->levelFiveKeys($competencyId, $levelIds),
                'score' => 5,
            ])
            ->assertSessionHasErrors('evidence');

        $this->assertDatabaseHas('assessments', [
            'user_id' => $employee->id,
            'competency_id' => $competencyId,
            'status' => 'draft',
        ]);
    }

    public function test_self_level_five_submits_after_evidence_is_attached(): void
    {
        Storage::fake('local');
        [$employee, $competencyId, $levelIds] = $this->employeeWithFacultyCompetency();
        $this->saveDraft($employee, $competencyId, $this->levelFiveKeys($competencyId, $levelIds));

        $this->actingAs($employee)
            ->post(route('assessments.evidence.store'), [
                'competency_id' => $competencyId,
                'evidenceFiles' => [UploadedFile::fake()->create('award.pdf', 20, 'application/pdf')],
                'evidenceLinks' => [['url' => 'https://example.test/project', 'description' => 'ผลงาน']],
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonCount(2, 'evidences');

        $evidence = DB::table('assessment_level_evidences')->where('kind', 'file')->firstOrFail();
        Storage::disk('local')->assertExists($evidence->storage_path);

        $this->actingAs($employee)
            ->post(route('assessments.save'), [
                'competency_id' => $competencyId,
                'checked_indicators' => $this->levelFiveKeys($competencyId, $levelIds),
                'score' => 5,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', [
            'user_id' => $employee->id,
            'competency_id' => $competencyId,
            'score' => 5,
            'status' => 'self_submitted',
        ]);
    }

    public function test_evidence_is_locked_after_submission(): void
    {
        Storage::fake('local');
        [$employee, $competencyId, $levelIds] = $this->employeeWithFacultyCompetency();
        $this->saveDraft($employee, $competencyId, $this->levelFiveKeys($competencyId, $levelIds));
        $this->attachLink($employee, $competencyId);
        $this->actingAs($employee)->post(route('assessments.save'), [
            'competency_id' => $competencyId,
            'checked_indicators' => $this->levelFiveKeys($competencyId, $levelIds),
            'score' => 5,
        ])->assertSessionHasNoErrors();
        $evidenceId = DB::table('assessment_level_evidences')->value('public_id');

        $this->actingAs($employee)
            ->deleteJson(route('assessments.evidence.destroy', $evidenceId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('evidence');
        $this->actingAs($employee)
            ->postJson(route('assessments.evidence.store'), [
                'competency_id' => $competencyId,
                'evidenceLinks' => [['url' => 'https://example.test/late']],
            ])
            ->assertUnprocessable();

        $this->assertSame(1, DB::table('assessment_level_evidences')->count());
    }

    public function test_reviewer_cannot_approve_level_five_without_employee_evidence(): void
    {
        [$employee, $competencyId, $levelIds, $reviewer] = $this->employeeWithFacultyCompetency();
        $assessmentId = $this->submittedAssessment($employee, $this->roundId, $competencyId, 4);

        $this->actingAs($reviewer)
            ->post(route('assessments.approve'), [
                'user_id' => $employee->id,
                'competency_id' => $competencyId,
                'checked' => $this->levelFiveKeys($competencyId, $levelIds, asMap: false),
            ])
            ->assertSessionHasErrors('checked');

        $this->assertDatabaseHas('assessments', ['id' => $assessmentId, 'status' => 'self_submitted']);
        $this->assertDatabaseMissing('scores', ['assessment_id' => $assessmentId]);
    }

    public function test_reviewer_can_open_evidence_but_unrelated_user_cannot(): void
    {
        Storage::fake('local');
        [$employee, $competencyId, $levelIds, $reviewer] = $this->employeeWithFacultyCompetency();
        $this->saveDraft($employee, $competencyId, $this->levelFiveKeys($competencyId, $levelIds));
        $this->actingAs($employee)->post(route('assessments.evidence.store'), [
            'competency_id' => $competencyId,
            'evidenceFiles' => [UploadedFile::fake()->create('award.pdf', 20, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertOk();
        $evidenceId = DB::table('assessment_level_evidences')->value('public_id');
        $outsider = User::factory()->create(['role_id' => $this->roleId('employee')]);

        $this->actingAs($employee)->get(route('assessments.evidence.show', $evidenceId))->assertOk();
        $this->actingAs($reviewer)->get(route('assessments.evidence.show', $evidenceId))->assertOk();
        $this->actingAs($outsider)->get(route('assessments.evidence.show', $evidenceId))->assertForbidden();
    }

    public function test_standard_round_does_not_accept_level_evidence(): void
    {
        [$employee, $competencyId, $levelIds] = $this->employeeWithFacultyCompetency();
        $this->saveDraft($employee, $competencyId, $this->levelFiveKeys($competencyId, $levelIds));
        DB::table('assessment_rounds')->where('id', $this->roundId)->update(['assessment_method' => 'standard']);

        $this->actingAs($employee)
            ->postJson(route('assessments.evidence.store'), [
                'competency_id' => $competencyId,
                'evidenceLinks' => [['url' => 'https://example.test/project']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('evidence');
    }

    private function employeeWithFacultyCompetency(): array
    {
        $reviewer = User::factory()->create(['role_id' => $this->roleId('academic_department_head')]);
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $this->roundId = $this->activeFacultyRound();
        [$competencyId, $levelIds] = $this->makeAssessable($employee, $this->roundId);
        $this->assignReviewer($employee, $reviewer);

        return [$employee, $competencyId, $levelIds, $reviewer];
    }

    private function levelFiveKeys(int $competencyId, array $levelIds, bool $asMap = true): array
    {
        $keys = [];
        foreach ([1 => 3, 3 => 3, 5 => 2] as $level => $count) {
            foreach (range(0, $count - 1) as $index) {
                $keys[] = "{$competencyId}:{$levelIds[$level]}:{$index}";
            }
        }

        return $asMap ? array_fill_keys($keys, true) : $keys;
    }

    private function saveDraft(User $employee, int $competencyId, array $checked): void
    {
        $this->actingAs($employee)
            ->postJson(route('assessments.draft'), [
                'competency_id' => $competencyId,
                'checked_indicators' => $checked,
                'score' => 5,
            ])
            ->assertOk();
    }

    private function attachLink(User $employee, int $competencyId): void
    {
        $this->actingAs($employee)
            ->postJson(route('assessments.evidence.store'), [
                'competency_id' => $competencyId,
                'evidenceLinks' => [['url' => 'https://example.test/project']],
            ])
            ->assertOk();
    }
}

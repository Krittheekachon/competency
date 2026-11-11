<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\BuildsFacultyAssessment;
use Tests\TestCase;

/**
 * A faculty round assesses CC/FC with the faculty rules, but MC in the university format
 * (levels 1-5, 0.25 per behaviour, Gap below zero requires an IDP, approval-only reviewers).
 */
class MixedAssessmentMethodTest extends TestCase
{
    use BuildsFacultyAssessment;
    use RefreshDatabase;

    public function test_a_faculty_round_accepts_university_format_mc_but_not_other_university_types(): void
    {
        $roundId = $this->activeFacultyRound();
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $this->makeAssessable($employee, $roundId);
        $positionId = (int) $employee->position_id;
        $mc = $this->universityCompetency('MC', 'MC-001', 'การคิดเชิงกลยุทธ์');
        $cc = $this->universityCompetency('CC', 'CC-UNI-001', 'ความรับผิดชอบ');
        $hr = User::factory()->create(['role_id' => $this->roleId('hr')]);

        $this->actingAs($hr)
            ->post(route('hr.position-competencies.store'), [
                'position_id' => $positionId, 'assessment_round_id' => $roundId, 'competency_ids' => [$mc['id']],
            ])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('position_competencies', ['assessment_round_id' => $roundId, 'competency_id' => $mc['id']]);

        $this->post(route('hr.position-competencies.store'), [
            'position_id' => $positionId, 'assessment_round_id' => $roundId, 'competency_ids' => [$cc['id']],
        ])->assertSessionHasErrors('competency_id');
        $this->assertDatabaseMissing('position_competencies', ['assessment_round_id' => $roundId, 'competency_id' => $cc['id']]);
    }

    public function test_university_format_mc_keeps_the_submitted_score_and_uses_the_university_gap_rule(): void
    {
        $roundId = $this->activeFacultyRound();
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $reviewer = User::factory()->create(['role_id' => $this->roleId('supervisor')]);
        [$ccId] = $this->makeAssessable($employee, $roundId);
        $this->assignReviewer($employee, $reviewer);
        $mc = $this->universityCompetency('MC', 'MC-001', 'การคิดเชิงกลยุทธ์');
        $this->bindToPosition($employee, $roundId, $mc['id']);

        // 2.5 of expected 3: faculty rule (>= 80%) would pass, the university rule (Gap < 0) must not.
        $this->actingAs($employee)->postJson(route('assessments.draft'), [
            'competency_id' => $mc['id'],
            'checked_indicators' => [
                "{$mc['id']}:{$mc['levels'][1]}:0" => true, "{$mc['id']}:{$mc['levels'][1]}:1" => true,
                "{$mc['id']}:{$mc['levels'][2]}:0" => true, "{$mc['id']}:{$mc['levels'][2]}:1" => true,
                "{$mc['id']}:{$mc['levels'][3]}:0" => true, "{$mc['id']}:{$mc['levels'][3]}:1" => true,
                "{$mc['id']}:{$mc['levels'][4]}:0" => true, "{$mc['id']}:{$mc['levels'][4]}:1" => true,
                "{$mc['id']}:{$mc['levels'][5]}:0" => true, "{$mc['id']}:{$mc['levels'][5]}:1" => true,
            ],
            'score' => 2.5,
        ])->assertOk();

        $assessment = DB::table('assessments')->where('user_id', $employee->id)->where('competency_id', $mc['id'])->first();
        $this->assertSame(2.5, (float) $assessment->score);
        $this->assertDatabaseHas('competency_gaps', [
            'assessment_id' => $assessment->id,
            'competency_id' => $mc['id'],
            'actual_level' => 2.5,
            'gap' => -0.5,
            'requires_idp' => true,
        ]);
        $this->assertSame(10, DB::table('assessment_indicator_results')->where('assessment_id', $assessment->id)->count());

        // CC in the same round is still scored from the checked faculty behaviours, not the client score.
        $ccLevels = DB::table('competency_levels')->where('competency_id', $ccId)->orderBy('level')->pluck('id', 'level');
        $this->postJson(route('assessments.draft'), [
            'competency_id' => $ccId,
            'checked_indicators' => ["{$ccId}:{$ccLevels[1]}:0" => true],
            'score' => 99,
        ])->assertOk();
        $this->assertSame(1.0, (float) DB::table('assessments')->where('user_id', $employee->id)->where('competency_id', $ccId)->value('score'));
    }

    public function test_university_format_score_never_exceeds_the_five_point_maximum(): void
    {
        $roundId = $this->activeFacultyRound();
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $this->makeAssessable($employee, $roundId);
        $this->assignReviewer($employee, User::factory()->create(['role_id' => $this->roleId('supervisor')]));
        $mc = $this->universityCompetency('MC', 'MC-001', 'การคิดเชิงกลยุทธ์');
        $this->bindToPosition($employee, $roundId, $mc['id']);

        // 21 behaviours at 0.25 would be 5.25.
        $this->actingAs($employee)->postJson(route('assessments.draft'), [
            'competency_id' => $mc['id'], 'checked_indicators' => [], 'score' => 5.25,
        ])->assertOk();

        $this->assertSame(5.0, (float) DB::table('assessments')->where('user_id', $employee->id)->where('competency_id', $mc['id'])->value('score'));
    }

    public function test_university_format_mc_is_approval_only_for_the_dean_while_faculty_competencies_are_still_scored(): void
    {
        $roundId = $this->activeFacultyRound();
        $deputy = User::factory()->create(['role_id' => $this->roleId('deputy_dean')]);
        $dean = User::factory()->create(['role_id' => $this->roleId('dean')]);
        [$ccId] = $this->makeAssessable($deputy, $roundId);
        $this->assignReviewer($deputy, $dean);
        $mc = $this->universityCompetency('MC', 'MC-001', 'การคิดเชิงกลยุทธ์');
        $this->bindToPosition($deputy, $roundId, $mc['id']);
        $assessmentId = $this->submittedAssessment($deputy, $roundId, $mc['id'], 4);

        // The dean scores a deputy's MC under the faculty rules, which would demand ticked behaviours.
        // In the university format nobody re-scores it, so approving without ticks must work.
        $this->actingAs($dean)->post(route('assessments.approve'), [
            'user_id' => $deputy->id,
            'competency_id' => $mc['id'],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', ['id' => $assessmentId, 'status' => 'approved']);
        $this->assertDatabaseHas('competency_gaps', ['assessment_id' => $assessmentId, 'actual_level' => 4, 'status' => 'approved']);
    }

    public function test_assigned_competencies_report_how_each_one_is_assessed(): void
    {
        $roundId = $this->activeFacultyRound();
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        [$ccId] = $this->makeAssessable($employee, $roundId);
        $this->assignReviewer($employee, User::factory()->create(['role_id' => $this->roleId('supervisor')]));
        $mc = $this->universityCompetency('MC', 'MC-001', 'การคิดเชิงกลยุทธ์');
        $this->bindToPosition($employee, $roundId, $mc['id']);

        $this->actingAs($employee)->get('/dashboard')->assertInertia(function (Assert $page) use ($ccId, $mc): void {
            $methods = collect($page->toArray()['props']['currentUserCompetencies'])->pluck('assessmentMethod', 'id');
            $this->assertSame('faculty', $methods[$ccId]);
            $this->assertSame('standard', $methods[$mc['id']]);
        });
    }

    /** @return array{id: int, levels: array<int, int>} */
    private function universityCompetency(string $typeCode, string $code, string $name): array
    {
        $typeId = DB::table('competency_types')->where('code', $typeCode)->value('id')
            ?: DB::table('competency_types')->insertGetId([
                'code' => $typeCode, 'full_name' => $typeCode, 'description' => $typeCode,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        $id = DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId, 'code' => $code, 'name' => $name,
            'assessment_method' => 'standard', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $levels = [];
        foreach (range(1, 5) as $level) {
            $levels[$level] = DB::table('competency_levels')->insertGetId([
                'competency_id' => $id, 'level' => $level, 'description' => "ระดับ {$level}",
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ([1, 2] as $n) {
                DB::table('comp_level_indicators')->insert([
                    'competency_level_id' => $levels[$level], 'description' => "พฤติกรรม {$level}.{$n}",
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        return ['id' => $id, 'levels' => $levels];
    }

    private function bindToPosition(User $employee, int $roundId, int $competencyId): void
    {
        DB::table('position_competencies')->insert([
            'assessment_round_id' => $roundId,
            'position_id' => $employee->position_id,
            'position_title_id' => $employee->position_title_id,
            'competency_id' => $competencyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

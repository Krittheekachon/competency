<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ExpectedLevelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsFacultyAssessment;
use Tests\TestCase;

class RoundExpectedLevelPolicyTest extends TestCase
{
    use BuildsFacultyAssessment;
    use RefreshDatabase;

    public function test_a_round_defaults_to_the_level_based_expectation(): void
    {
        $roundId = $this->activeFacultyRound();
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        [$ccId] = $this->makeAssessable($employee, $roundId);

        $this->assertDatabaseHas('assessment_rounds', ['id' => $roundId, 'expected_level_mode' => 'by_level']);
        $this->assertSame(3, app(ExpectedLevelResolver::class)->forUserCompetency($employee, $ccId));
    }

    public function test_a_fixed_round_gives_everyone_the_same_expected_level(): void
    {
        $roundId = $this->activeFacultyRound();
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        [$ccId] = $this->makeAssessable($employee, $roundId);
        DB::table('assessment_rounds')->where('id', $roundId)->update(['expected_level_mode' => 'fixed', 'expected_level_fixed' => 4]);

        $this->assertSame(4, app(ExpectedLevelResolver::class)->forUserCompetency($employee, $ccId));

        // The level's own value is irrelevant, even when it is missing.
        DB::table('levels')->where('id', $employee->level_id)->update(['expected_level' => null]);
        app(ExpectedLevelResolver::class)->resetRequestCache();
        $this->assertSame(4, app(ExpectedLevelResolver::class)->forUserCompetency($employee, $ccId));
    }

    public function test_hr_chooses_the_expectation_rule_when_creating_a_round_for_either_method(): void
    {
        $hr = User::factory()->create(['role_id' => $this->roleId('hr')]);

        foreach (['faculty', 'standard'] as $method) {
            $this->actingAs($hr)->post(route('hr.assessment-rounds.store'), [
                'name' => "รอบคงที่ {$method}", 'is_active' => false, 'assessment_method' => $method,
                'expected_level_mode' => 'fixed', 'expected_level_fixed' => 4,
            ])->assertSessionHasNoErrors();
            $this->assertDatabaseHas('assessment_rounds', ['name' => "รอบคงที่ {$method}", 'expected_level_mode' => 'fixed', 'expected_level_fixed' => 4]);
        }

        $this->post(route('hr.assessment-rounds.store'), [
            'name' => 'รอบตามระดับ', 'is_active' => false, 'expected_level_mode' => 'by_level', 'expected_level_fixed' => 5,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('assessment_rounds', ['name' => 'รอบตามระดับ', 'expected_level_mode' => 'by_level', 'expected_level_fixed' => null]);
    }

    public function test_a_fixed_round_needs_a_level_between_one_and_five(): void
    {
        $hr = User::factory()->create(['role_id' => $this->roleId('hr')]);

        $this->actingAs($hr)->post(route('hr.assessment-rounds.store'), [
            'name' => 'รอบไม่มีระดับ', 'is_active' => false, 'expected_level_mode' => 'fixed',
        ])->assertSessionHasErrors('expected_level_fixed');
        $this->post(route('hr.assessment-rounds.store'), [
            'name' => 'รอบระดับเกิน', 'is_active' => false, 'expected_level_mode' => 'fixed', 'expected_level_fixed' => 6,
        ])->assertSessionHasErrors('expected_level_fixed');
        $this->assertDatabaseMissing('assessment_rounds', ['name' => 'รอบไม่มีระดับ']);
    }

    public function test_the_rule_can_change_before_assessment_starts_but_is_locked_afterwards(): void
    {
        $hr = User::factory()->create(['role_id' => $this->roleId('hr')]);
        $roundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบแก้ไข', 'year' => 2569, 'is_active' => false, 'assessment_method' => 'faculty',
            'assessment_method_version' => 1, 'snapshot_required' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $payload = fn (string $mode, ?int $level): array => [
            'name' => 'รอบแก้ไข', 'is_active' => false, 'expected_level_mode' => $mode, 'expected_level_fixed' => $level,
        ];

        $this->actingAs($hr)->put(route('hr.assessment-rounds.update', $roundId), $payload('fixed', 4))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('assessment_rounds', ['id' => $roundId, 'expected_level_mode' => 'fixed', 'expected_level_fixed' => 4]);

        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $competencyId = DB::table('competencies')->insertGetId([
            'competency_type_id' => DB::table('competency_types')->insertGetId(['code' => 'CC', 'full_name' => 'CC', 'description' => 'CC', 'created_at' => now(), 'updated_at' => now()]),
            'code' => 'CC-LOCK', 'name' => 'ทดสอบ', 'assessment_method' => 'faculty', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->submittedAssessment($employee, $roundId, $competencyId, 3);

        $this->put(route('hr.assessment-rounds.update', $roundId), $payload('by_level', null))->assertSessionHasErrors('expected_level_mode');
        $this->put(route('hr.assessment-rounds.update', $roundId), $payload('fixed', 5))->assertSessionHasErrors('expected_level_mode');
        $this->assertDatabaseHas('assessment_rounds', ['id' => $roundId, 'expected_level_mode' => 'fixed', 'expected_level_fixed' => 4]);

        // Saving the same rule again (e.g. editing only the dates) still works.
        $this->put(route('hr.assessment-rounds.update', $roundId), $payload('fixed', 4))->assertSessionHasNoErrors();
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\BuildsFacultyAssessment;
use Tests\TestCase;

/**
 * Dean / deputy dean / assistant dean are workline-level positions: no position row,
 * so HR binds their competencies to the position title itself.
 */
class WorklinePositionCompetencyTest extends TestCase
{
    use BuildsFacultyAssessment;
    use RefreshDatabase;

    public function test_hr_binds_competencies_to_a_workline_level_title_and_the_executive_receives_them(): void
    {
        $roundId = $this->activeFacultyRound();
        [$titleId] = $this->managementTitle(true);
        $executive = $this->executive($titleId);
        $competencyId = $this->competency('CC-A', 'faculty');
        $hr = User::factory()->create(['role_id' => $this->roleId('hr')]);

        $this->actingAs($hr)->post(route('hr.position-competencies.store'), [
            'position_title_id' => $titleId, 'assessment_round_id' => $roundId, 'competency_ids' => [$competencyId],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('position_competencies', [
            'assessment_round_id' => $roundId, 'position_id' => null,
            'position_title_id' => $titleId, 'competency_id' => $competencyId,
        ]);
        // Posting again must not duplicate the title-only row.
        $this->post(route('hr.position-competencies.store'), [
            'position_title_id' => $titleId, 'assessment_round_id' => $roundId, 'competency_id' => $competencyId,
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('position_competencies')->where('position_title_id', $titleId)->count());

        $this->actingAs($executive)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('currentUserCompetencies.0.id', $competencyId));
        $this->assertDatabaseHas('assessments', ['user_id' => $executive->id, 'competency_id' => $competencyId, 'assessment_round_id' => $roundId]);

        $this->actingAs($hr)->delete(route('hr.position-competencies.destroy'), [
            'position_title_id' => $titleId, 'assessment_round_id' => $roundId, 'competency_id' => $competencyId,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('position_competencies', 0);
    }

    public function test_hr_page_lists_title_only_bindings_under_their_own_key(): void
    {
        $roundId = $this->activeFacultyRound();
        [$titleId] = $this->managementTitle(true);
        $competencyId = $this->competency('CC-A', 'faculty');
        DB::table('position_competencies')->insert([
            'assessment_round_id' => $roundId, 'position_id' => null, 'position_title_id' => $titleId,
            'competency_id' => $competencyId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs(User::factory()->create(['role_id' => $this->roleId('hr')]))->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where("positionCompetencies.{$roundId}.title:{$titleId}.0", $competencyId)
                ->where('positionTitles', fn ($titles) => collect($titles)->contains(fn ($t) => $t['id'] === $titleId && $t['isWorklinePosition'] === true)));
    }

    public function test_a_title_that_is_not_workline_level_cannot_be_bound_by_title_alone(): void
    {
        $roundId = $this->activeFacultyRound();
        [$titleId] = $this->managementTitle(false);
        $competencyId = $this->competency('CC-A', 'faculty');

        $this->actingAs(User::factory()->create(['role_id' => $this->roleId('hr')]))
            ->post(route('hr.position-competencies.store'), [
                'position_title_id' => $titleId, 'assessment_round_id' => $roundId, 'competency_id' => $competencyId,
            ])->assertSessionHasErrors('position_title_id');
        $this->assertDatabaseCount('position_competencies', 0);
    }

    /** @return array{0: int, 1: int} title id, workline id */
    private function managementTitle(bool $worklinePosition): array
    {
        $worklineId = DB::table('worklines')->insertGetId(['name' => 'สายบริหาร', 'created_at' => now(), 'updated_at' => now()]);
        $titleId = DB::table('position_titles')->insertGetId([
            'workline_id' => $worklineId, 'workline_type' => 'management', 'name' => 'รองคณบดี', 'normalized_name' => 'รองคณบดี',
            'normalized_position_type_name' => '', 'is_workline_position' => $worklinePosition,
        ]);

        return [$titleId, $worklineId];
    }

    private function executive(int $titleId): User
    {
        return User::factory()->create([
            'role_id' => $this->roleId('deputy_dean'), 'workline' => 'สายบริหาร', 'position' => 'รองคณบดี',
            'position_title_id' => $titleId, 'position_id' => null,
        ]);
    }

    private function competency(string $code, string $method): int
    {
        $typeId = DB::table('competency_types')->where('code', 'CC')->value('id')
            ?: DB::table('competency_types')->insertGetId(['code' => 'CC', 'full_name' => 'CC', 'description' => 'CC', 'created_at' => now(), 'updated_at' => now()]);

        return (int) DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId, 'code' => $code, 'name' => 'สมรรถนะ '.$code,
            'assessment_method' => $method, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

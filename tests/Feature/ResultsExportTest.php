<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\BuildsFacultyAssessment;
use Tests\TestCase;

class ResultsExportTest extends TestCase
{
    use BuildsFacultyAssessment;
    use RefreshDatabase;

    public function test_export_page_lists_results_and_who_passed_each_review_step(): void
    {
        $reviewer = User::factory()->create(['role_id' => $this->roleId('academic_department_head'), 'name' => 'หัวหน้าสาขาทดสอบ']);
        $employee = User::factory()->create(['role_id' => $this->roleId('employee'), 'name' => 'อาจารย์ทดสอบ']);
        $roundId = $this->activeFacultyRound();
        [$competencyId, $levelIds] = $this->makeAssessable($employee, $roundId);
        $this->assignReviewer($employee, $reviewer);
        $this->submittedAssessment($employee, $roundId, $competencyId, 2);
        DB::table('assessments')->where('user_id', $employee->id)->update(['self_submitted_at' => '2026-11-05 10:00:00']);

        $this->actingAs($reviewer)->post(route('assessments.approve'), [
            'user_id' => $employee->id,
            'competency_id' => $competencyId,
            'checked' => ["{$competencyId}:{$levelIds[1]}:0", "{$competencyId}:{$levelIds[1]}:1", "{$competencyId}:{$levelIds[1]}:2"],
        ])->assertSessionHasNoErrors();

        $this->actingAs($employee)->get(route('employee.results.export', ['round' => $roundId]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employee/ResultsExport')
                ->where('exportUser.name', 'อาจารย์ทดสอบ')
                ->where('exportRound.id', $roundId)
                ->has('exportRows', 1)
                ->where('exportRows.0.status', 'approved')
                ->where('exportChain.self.submittedAt', '2026-11-05 10:00:00')
                ->has('exportChain.steps', 1)
                ->where('exportChain.steps.0.name', fn ($name) => str_contains($name, 'หัวหน้าสาขาทดสอบ'))
                ->where('exportRows.0.reviewHistory.0.reviewStep', 1)
                ->where('exportRows.0.reviewHistory.0.decision', 'approved')
                ->where('exportRows.0.reviewHistory.0.reviewerName', fn ($name) => str_contains($name, 'หัวหน้าสาขาทดสอบ')));
    }

    public function test_export_defaults_to_the_active_round_and_only_ever_shows_the_signed_in_user(): void
    {
        $roundId = $this->activeFacultyRound();
        $employee = User::factory()->create(['role_id' => $this->roleId('employee'), 'name' => 'ผู้ใช้ A']);
        $other = User::factory()->create(['role_id' => $this->roleId('employee'), 'name' => 'ผู้ใช้ B']);
        [$competencyId] = $this->makeAssessable($other, $roundId);
        $this->submittedAssessment($other, $roundId, $competencyId, 4);

        $this->actingAs($employee)->get(route('employee.results.export'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('exportUser.name', 'ผู้ใช้ A')
                ->where('exportRound.id', $roundId)
                ->has('exportRows', 0));
    }

    public function test_export_requires_sign_in_and_a_real_round(): void
    {
        $this->get(route('employee.results.export'))->assertRedirect();

        $this->actingAs(User::factory()->create(['role_id' => $this->roleId('employee')]))
            ->get(route('employee.results.export', ['round' => 999]))
            ->assertNotFound();
    }
}

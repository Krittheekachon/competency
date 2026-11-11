<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $reviewer;

    private int $oldRoundId;

    private int $currentRoundId;

    private int $competencyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $this->reviewer = User::factory()->create(['role_id' => $this->roleId('supervisor'), 'title' => 'ดร.', 'name' => 'ผู้อนุมัติ IDP']);
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $this->employee->id, 'reviewer_id' => $this->reviewer->id,
            'step_order' => 1, 'chain_type' => 'idp', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->oldRoundId = $this->round('รอบที่ 1/2567', 2567, false, '2024-10-01', '2025-03-31');
        $this->currentRoundId = $this->round('รอบที่ 1/2568', 2568, true, '2025-10-01', '2026-03-31');

        $typeId = DB::table('competency_types')->insertGetId([
            'code' => 'CC', 'full_name' => 'Core Competency', 'description' => 'Core Competency', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->competencyId = DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId, 'code' => 'CC-HIS', 'name' => 'การสื่อสาร',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_idp_history_lists_only_own_plans_across_rounds_with_reviews(): void
    {
        $oldGap = $this->gap($this->employee, $this->oldRoundId);
        $oldItem = $this->idpItem($this->employee, $this->oldRoundId, $oldGap, 'approved');
        $this->activity($oldItem, 'อบรมการสื่อสาร', [
            '_behaviorPlan' => true,
            'behaviors' => [['key' => "$oldGap:2:2.1", 'gapId' => $oldGap, 'level' => 2, 'code' => '2.1', 'description' => 'สื่อสารให้ตรงกัน']],
        ]);
        DB::table('idp_item_reviews')->insert([
            'idp_item_id' => $oldItem, 'submission_version' => 1, 'review_step' => 1,
            'reviewer_id' => $this->reviewer->id, 'decision' => 'approved', 'comment' => 'เห็นชอบ',
            'decided_at' => '2025-03-20 10:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $currentGap = $this->gap($this->employee, $this->currentRoundId);
        $currentItem = $this->idpItem($this->employee, $this->currentRoundId, $currentGap, 'review_step_1', 1);
        $this->activity($currentItem, 'Project Assignment', ['_saved' => true, 'planRows' => [['assignmentTopic' => 'โครงการ A']]], 'form_3_project_assignment');

        $other = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $this->idpItem($other, $this->oldRoundId, $this->gap($other, $this->oldRoundId), 'approved');

        $response = $this->actingAs($this->employee)->getJson(route('employee.history.idp'))->assertOk();

        $response->assertJsonPath('periods.0.id', (string) $this->currentRoundId)
            ->assertJsonPath('periods.0.current', true)
            ->assertJsonPath('periods.1.id', (string) $this->oldRoundId)
            ->assertJsonPath('periods.1.label', 'รอบที่ 1/2567')
            ->assertJsonPath('periods.1.current', false)
            ->assertJsonCount(2, 'plans');

        $plans = collect($response->json('plans'))->keyBy('id');
        $old = $plans[$oldItem];
        $this->assertSame((string) $this->oldRoundId, $old['roundId']);
        $this->assertSame('approved', $old['status']);
        $this->assertSame('quick', $old['fillMode']);
        $this->assertSame('การสื่อสาร', $old['gaps'][0]['name']);
        $this->assertSame('สื่อสารให้ตรงกัน', $old['activities'][0]['formDetails']['behaviors'][0]['description']);
        $this->assertSame('ดร.ผู้อนุมัติ IDP', $old['reviews'][0]['reviewerName']);
        $this->assertSame('approved', $old['reviews'][0]['decision']);
        $this->assertNull($old['pendingReviewer']);

        $current = $plans[$currentItem];
        $this->assertSame('form', $current['fillMode']);
        $this->assertSame('form_3_project_assignment', $current['activities'][0]['formCode']);
        $this->assertSame(['step' => 1, 'name' => 'ดร.ผู้อนุมัติ IDP'], $current['pendingReviewer']);
    }

    public function test_evaluation_history_returns_own_results_for_a_past_round(): void
    {
        $this->gap($this->employee, $this->oldRoundId);
        $this->gap(User::factory()->create(['role_id' => $this->roleId('employee')]), $this->oldRoundId);

        $this->actingAs($this->employee)->getJson(route('employee.history.evaluation-periods'))
            ->assertOk()
            ->assertJsonCount(2, 'periods')
            ->assertJsonPath('periods.0.current', true);

        $this->getJson(route('employee.history.evaluations', $this->oldRoundId))
            ->assertOk()
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.n', 'การสื่อสาร')
            ->assertJsonPath('rows.0.status', 'approved')
            ->assertJsonPath('rows.0.requiresIdp', true);

        $this->getJson(route('employee.history.evaluations', $this->currentRoundId))
            ->assertOk()
            ->assertJsonCount(0, 'rows');
    }

    public function test_history_requires_login_and_an_existing_round(): void
    {
        $this->getJson(route('employee.history.idp'))->assertUnauthorized();
        $this->actingAs($this->employee)->getJson(route('employee.history.evaluations', 999999))->assertNotFound();
    }

    private function round(string $name, int $year, bool $active, string $start, string $end): int
    {
        return (int) DB::table('assessment_rounds')->insertGetId([
            'name' => $name, 'year' => $year, 'is_active' => $active, 'snapshot_required' => false,
            'self_assess_start' => $start, 'self_assess_end' => $end, 'supervisor_assess_end' => $end,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function gap(User $user, int $roundId): int
    {
        $assessmentId = DB::table('assessments')->insertGetId([
            'user_id' => $user->id, 'competency_id' => $this->competencyId, 'assessment_round_id' => $roundId,
            'score' => 2, 'status' => 'approved', 'last_draft_saved_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) DB::table('competency_gaps')->insertGetId([
            'assessment_id' => $assessmentId, 'competency_id' => $this->competencyId,
            'expected_level' => 3, 'actual_level' => 2, 'gap' => -1, 'requires_idp' => true,
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function idpItem(User $user, int $roundId, int $gapId, string $status, ?int $step = null): int
    {
        $idpId = DB::table('idps')->insertGetId([
            'user_id' => $user->id, 'assessment_round_id' => $roundId, 'year' => 2568,
            'status' => 'submitted', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) DB::table('idp_items')->insertGetId([
            'idp_id' => $idpId, 'competency_gap_id' => $gapId, 'goal' => 'พัฒนา', 'status' => $status,
            'submission_version' => 1, 'current_review_step' => $step,
            'submitted_at' => '2025-03-10 09:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function activity(int $itemId, string $name, array $details, ?string $formCode = null): void
    {
        DB::table('idp_activities')->insert([
            'idp_item_id' => $itemId, 'activity_name' => $name, 'weight_percent' => 100,
            'form_code' => $formCode, 'form_details' => json_encode($details),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeIdpPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_save_one_competency_plan_with_multiple_activities(): void
    {
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $competencyId = $this->competencyId();
        $assessment = $this->assessment($employee, $competencyId);
        $gapId = DB::table('competency_gaps')->insertGetId([
            'assessment_id' => $assessment->id,
            'competency_id' => $competencyId,
            'expected_level' => 3,
            'actual_level' => 2,
            'gap' => -1,
            'requires_idp' => true,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $methodTypeId = DB::table('learning_method_types')->insertGetId([
            'key' => 'experiential',
            'label' => 'Experiential Learning',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($employee)
            ->post(route('employee.idp.draft'), [
                'items' => [[
                    'competencyGapId' => $gapId,
                    'goal' => 'ทำงานให้ตรงตามมาตรฐาน',
                    'successCriteria' => '',
                    'activities' => [
                        [
                            'methodKey' => 'experiential',
                            'developmentToolId' => null,
                            'learningCatalogId' => null,
                            'activityName' => 'Project Assignment',
                            'activityDescription' => 'ฝึกทำงานจริง',
                            'documentReferenceNumber' => 'กจ.01/2569',
                            'weightPercent' => 70,
                            'startDate' => '2026-06-16',
                            'endDate' => '2026-07-16',
                        ],
                        [
                            'methodKey' => 'experiential',
                            'developmentToolId' => null,
                            'learningCatalogId' => null,
                            'activityName' => 'Job Rotation',
                            'activityDescription' => 'หมุนเวียนงาน',
                            'weightPercent' => 30,
                            'startDate' => '2026-07-17',
                            'endDate' => '2026-08-16',
                        ],
                    ],
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('idps', [
            'user_id' => $employee->id,
            'year' => 2568,
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('idp_items', [
            'competency_gap_id' => $gapId,
            'goal' => 'ทำงานให้ตรงตามมาตรฐาน',
        ]);
        $this->assertDatabaseCount('idp_items', 1);
        $this->assertDatabaseCount('idp_activities', 2);
        $this->assertDatabaseHas('idp_activities', [
            'activity_name' => 'Project Assignment',
            'method_type_id' => $methodTypeId,
            'document_reference_number' => 'กจ.01/2569',
            'weight_percent' => 70,
        ]);
        $this->assertDatabaseHas('idp_activities', [
            'activity_name' => 'Job Rotation',
            'weight_percent' => 30,
        ]);
    }

    public function test_employee_must_complete_required_idp_fields_before_submit(): void
    {
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $competencyId = $this->competencyId('CC-SUBMIT');
        $assessment = $this->assessment($employee, $competencyId);
        $gapId = DB::table('competency_gaps')->insertGetId([
            'assessment_id' => $assessment->id,
            'competency_id' => $competencyId,
            'expected_level' => 3,
            'actual_level' => 2,
            'gap' => -1,
            'requires_idp' => true,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($employee)
            ->post(route('employee.idp.submit'), [
                'items' => [[
                    'competencyGapId' => $gapId,
                    'goal' => '',
                    'successCriteria' => '',
                    'activities' => [],
                ]],
            ])
            ->assertSessionHasErrors();
    }

    public function test_employee_cannot_choose_formal_catalog_from_another_competency(): void
    {
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $competencyId = $this->competencyId('CC-FORMAL');
        $otherCompetencyId = $this->competencyId('CC-OTHER');
        $assessment = $this->assessment($employee, $competencyId);
        $gapId = DB::table('competency_gaps')->insertGetId([
            'assessment_id' => $assessment->id,
            'competency_id' => $competencyId,
            'expected_level' => 3,
            'actual_level' => 2,
            'gap' => -1,
            'requires_idp' => true,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $formalMethodId = DB::table('learning_method_types')->insertGetId([
            'key' => 'formal',
            'label' => 'Formal Learning',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $catalogId = DB::table('learning_catalogs')->insertGetId([
            'name' => 'หลักสูตรคนละสมรรถนะ',
            'method_type_id' => $formalMethodId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('learning_catalog_competency')->insert([
            'learning_catalog_id' => $catalogId,
            'competency_id' => $otherCompetencyId,
        ]);

        $this->actingAs($employee)
            ->post(route('employee.idp.draft'), [
                'items' => [[
                    'competencyGapId' => $gapId,
                    'goal' => 'พัฒนาสมรรถนะ',
                    'successCriteria' => 'ทำงานได้ตามเกณฑ์',
                    'activities' => [[
                        'methodKey' => 'formal',
                        'learningCatalogId' => $catalogId,
                        'activityName' => 'หลักสูตรคนละสมรรถนะ',
                        'weightPercent' => 100,
                        'startDate' => '2026-06-16',
                        'endDate' => '2026-07-16',
                    ]],
                ]],
            ])
            ->assertSessionHasErrors('items.0.activities.0.learningCatalogId');
    }

    public function test_employee_can_submit_one_competency_plan_while_another_remains_draft(): void
    {
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $firstCompetencyId = $this->competencyId('CC-FIRST');
        $secondCompetencyId = $this->competencyId('CC-SECOND');
        $firstGapId = $this->approvedGap($employee, $firstCompetencyId);
        $secondGapId = $this->approvedGap($employee, $secondCompetencyId);
        DB::table('learning_method_types')->insert([
            'key' => 'experiential-learning',
            'label' => 'Experiential Learning',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $toolId = DB::table('idp_learning_methods')->insertGetId([
            'code' => 'EXP-TEST',
            'focus_type' => 'experiential',
            'title' => 'Project Assignment',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $draftItems = [
            $this->completePlanPayload($firstGapId, $toolId),
            $this->completePlanPayload($secondGapId, $toolId),
        ];

        $this->actingAs($employee)
            ->post(route('employee.idp.draft'), ['items' => $draftItems])
            ->assertSessionHasNoErrors();

        $this->actingAs($employee)
            ->post(route('employee.idp.submit-item'), [
                'competencyGapId' => $firstGapId,
                'item' => $draftItems[0],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('idp_items', [
            'competency_gap_id' => $firstGapId,
            'status' => 'submitted',
        ]);
        $this->assertDatabaseHas('idp_items', [
            'competency_gap_id' => $secondGapId,
            'status' => 'draft',
        ]);
    }

    public function test_auto_save_does_not_overwrite_submitted_competency_plan(): void
    {
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $competencyId = $this->competencyId('CC-LOCKED');
        $gapId = $this->approvedGap($employee, $competencyId);
        $idpId = DB::table('idps')->insertGetId([
            'user_id' => $employee->id,
            'year' => 2568,
            'status' => 'partially_submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('idp_items')->insert([
            'idp_id' => $idpId,
            'competency_gap_id' => $gapId,
            'goal' => 'เป้าหมายที่ส่งแล้ว',
            'success_criteria' => 'เกณฑ์เดิม',
            'status' => 'submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($employee)
            ->post(route('employee.idp.draft'), [
                'items' => [[
                    'competencyGapId' => $gapId,
                    'goal' => 'พยายามเขียนทับ',
                    'successCriteria' => 'พยายามเขียนทับ',
                    'activities' => [],
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('idp_items', [
            'competency_gap_id' => $gapId,
            'goal' => 'เป้าหมายที่ส่งแล้ว',
            'status' => 'submitted',
        ]);
    }

    private function approvedGap(User $employee, int $competencyId): int
    {
        $assessment = $this->assessment($employee, $competencyId);

        return (int) DB::table('competency_gaps')->insertGetId([
            'assessment_id' => $assessment->id,
            'competency_id' => $competencyId,
            'expected_level' => 3,
            'actual_level' => 2,
            'gap' => -1,
            'requires_idp' => true,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function completePlanPayload(int $gapId, int $toolId): array
    {
        return [
            'competencyGapId' => $gapId,
            'goal' => 'พัฒนาสมรรถนะ',
            'successCriteria' => 'ผ่านตามเกณฑ์',
            'activities' => [[
                'methodKey' => 'experiential-learning',
                'developmentToolId' => $toolId,
                'learningCatalogId' => null,
                'activityName' => 'Project Assignment',
                'activityDescription' => 'ฝึกทำงานจริง',
                'documentReferenceNumber' => '',
                'weightPercent' => 100,
                'startDate' => '2026-06-20',
                'endDate' => '2026-07-20',
            ]],
        ];
    }

    private function assessment(User $user, int $competencyId): Assessment
    {
        $values = [
            'user_id' => $user->id,
            'competency_id' => $competencyId,
            'score' => 2,
            'note' => 'ควรพัฒนาต่อ',
            'status' => 'approved',
            'last_draft_saved_at' => now(),
        ];

        if (Schema::hasColumn('assessments', 'assessment_round_id')) {
            $values['assessment_round_id'] = $this->assessmentRoundId();
        }

        return Assessment::create($values);
    }

    private function competencyId(string $code = 'CC-IDP'): int
    {
        $typeId = DB::table('competency_types')->insertGetId([
            'code' => $code.'-TYPE',
            'full_name' => 'Core Competency',
            'description' => 'Core Competency',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId,
            'code' => $code,
            'name' => 'Test Competency',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }

    private function assessmentRoundId(): int
    {
        return (int) DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบทดสอบ',
            'year' => 2568,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

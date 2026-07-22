<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\User;
use App\Mail\AssessmentStatusUpdateMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AssessmentReviewerChainTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_assessment_with_only_third_evaluator_is_sent_to_third_step(): void
    {
        $dean = User::factory()->create([
            'role_id' => $this->roleId('dean'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'supervisor_id_1' => null,
            'supervisor_id_2' => null,
            'supervisor_id_3' => $dean->id,
        ]);
        $competencyId = $this->competencyId();

        $this->actingAs($employee)
            ->post(route('assessments.save'), [
                'competency_id' => $competencyId,
                'checked_indicators' => [],
                'score' => 0,
                'note' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', [
            'user_id' => $employee->id,
            'competency_id' => $competencyId,
            'status' => 'dept_evaluated',
        ]);
        $this->assertDatabaseHas('competency_gaps', [
            'competency_id' => $competencyId,
            'status' => 'dept_evaluated',
        ]);
    }

    public function test_approval_skips_missing_second_evaluator_and_sends_to_third_step(): void
    {
        $firstReviewer = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);
        $thirdReviewer = User::factory()->create([
            'role_id' => $this->roleId('dean'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'supervisor_id_1' => $firstReviewer->id,
            'supervisor_id_2' => null,
            'supervisor_id_3' => $thirdReviewer->id,
        ]);
        $competencyId = $this->competencyId();
        $assessment = $this->assessment($employee, $competencyId, 'self_submitted');

        $this->actingAs($firstReviewer)
            ->post(route('assessments.approve'), [
                'user_id' => $employee->id,
                'competency_id' => $competencyId,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', [
            'id' => $assessment->id,
            'status' => 'dept_evaluated',
        ]);
        $this->assertDatabaseHas('competency_gaps', [
            'assessment_id' => $assessment->id,
            'competency_id' => $competencyId,
            'status' => 'dept_evaluated',
        ]);
    }

    public function test_third_evaluator_approval_completes_assessment(): void
    {
        $thirdReviewer = User::factory()->create([
            'role_id' => $this->roleId('dean'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'supervisor_id_1' => null,
            'supervisor_id_2' => null,
            'supervisor_id_3' => $thirdReviewer->id,
        ]);
        $competencyId = $this->competencyId();
        $assessment = $this->assessment($employee, $competencyId, 'dept_evaluated');

        $this->actingAs($thirdReviewer)
            ->post(route('assessments.approve'), [
                'user_id' => $employee->id,
                'competency_id' => $competencyId,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', [
            'id' => $assessment->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('competency_gaps', [
            'assessment_id' => $assessment->id,
            'competency_id' => $competencyId,
            'status' => 'approved',
        ]);
    }

    public function test_reviewer_can_approve_only_one_competency_without_touching_the_others(): void
    {
        $reviewer = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'supervisor_id_1' => $reviewer->id,
        ]);
        $firstCompetencyId = $this->competencyId('CC-ONE');
        $secondCompetencyId = $this->competencyId('CC-TWO');
        $firstAssessment = $this->assessment($employee, $firstCompetencyId, 'self_submitted');
        $secondAssessment = $this->assessment($employee, $secondCompetencyId, 'self_submitted');
        $reviewerComment = trim(str_repeat('ผ่านแล้ว พร้อมข้อเสนอแนะเพิ่มเติม ', 12));

        $this->actingAs($reviewer)
            ->post(route('assessments.approve'), [
                'user_id' => $employee->id,
                'competency_id' => $firstCompetencyId,
                'comment' => $reviewerComment,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', [
            'id' => $firstAssessment->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('assessments', [
            'id' => $secondAssessment->id,
            'status' => 'self_submitted',
        ]);
        $this->assertDatabaseHas('scores', [
            'assessment_id' => $firstAssessment->id,
            'competency_id' => $firstCompetencyId,
            'assessor_id' => $reviewer->id,
            'assessor_role' => 'supervisor_1',
            'comment' => $reviewerComment,
            'status' => 'approved',
        ]);
    }

    public function test_reviewer_can_reject_only_one_competency_without_touching_the_others(): void
    {
        $reviewer = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);
        $nextReviewer = User::factory()->create([
            'role_id' => $this->roleId('dept_head'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'supervisor_id_1' => $reviewer->id,
            'supervisor_id_2' => $nextReviewer->id,
        ]);
        $firstCompetencyId = $this->competencyId('CC-REJECT');
        $secondCompetencyId = $this->competencyId('CC-STAY');
        $firstAssessment = $this->assessment($employee, $firstCompetencyId, 'self_submitted');
        $firstAssessment->forceFill(['note' => 'ประเมินตนเองไว้ก่อนส่งหัวหน้า'])->save();
        $secondAssessment = $this->assessment($employee, $secondCompetencyId, 'self_submitted');
        $rejectComment = trim(str_repeat('ควรประเมินใหม่พร้อมเหตุผลละเอียด ', 12));

        $this->actingAs($reviewer)
            ->post(route('assessments.reject'), [
                'user_id' => $employee->id,
                'competency_id' => $firstCompetencyId,
                'comment' => $rejectComment,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', [
            'id' => $firstAssessment->id,
            'status' => 'revision_required',
            'note' => 'ประเมินตนเองไว้ก่อนส่งหัวหน้า',
        ]);
        $this->assertDatabaseHas('assessments', [
            'id' => $secondAssessment->id,
            'status' => 'self_submitted',
        ]);
        $this->assertDatabaseHas('competency_gaps', [
            'assessment_id' => $firstAssessment->id,
            'competency_id' => $firstCompetencyId,
            'status' => 'revision_required',
            'rejected_by' => $reviewer->id,
            'reject_comment' => $rejectComment,
        ]);
        $this->assertDatabaseHas('scores', [
            'assessment_id' => $firstAssessment->id,
            'competency_id' => $firstCompetencyId,
            'assessor_id' => $reviewer->id,
            'assessor_role' => 'supervisor_1',
            'comment' => $rejectComment,
            'status' => 'rejected',
        ]);

        $this->actingAs($employee)
            ->get(route('assessments.load', ['competency_id' => $firstCompetencyId]))
            ->assertOk()
            ->assertJsonPath('status', 'revision_required')
            ->assertJsonPath('reject_comment', $rejectComment)
            ->assertJsonPath('locked', false);
    }

    public function test_supervisor_approval_mail_uses_intermediate_status_copy(): void
    {
        Mail::fake();

        $reviewer = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);
        $nextReviewer = User::factory()->create([
            'role_id' => $this->roleId('dept_head'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'supervisor_id_1' => $reviewer->id,
            'supervisor_id_2' => $nextReviewer->id,
        ]);
        $competencyId = $this->competencyId('CC-MAIL');
        $this->assessment($employee, $competencyId, 'self_submitted');

        $this->actingAs($reviewer)
            ->post(route('assessments.approve'), [
                'user_id' => $employee->id,
                'competency_id' => $competencyId,
            ])
            ->assertSessionHasNoErrors();

        Mail::assertSent(AssessmentStatusUpdateMail::class, function (AssessmentStatusUpdateMail $mail) {
            if ($mail->status !== 'unit_evaluated') {
                return false;
            }

            $rendered = $mail->render();

            $this->assertStringContainsString('หัวหน้าหน่วยอนุมัติผลการประเมินแล้ว', $rendered);
            $this->assertStringContainsString('รอการตรวจสอบจากหัวหน้างาน', $rendered);
            $this->assertStringNotContainsString('สามารถเริ่มทำแผนพัฒนารายบุคคล (IDP) ได้', $rendered);

            return true;
        });
    }

    public function test_self_assessment_stores_checked_indicators_separately_from_evidence(): void
    {
        $reviewer = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'supervisor_id_1' => $reviewer->id,
        ]);
        $competencyId = $this->competencyId('CC-CHECKED');
        $checkedKey = $competencyId.':1:0';

        $this->actingAs($employee)
            ->post(route('assessments.save'), [
                'competency_id' => $competencyId,
                'checked_indicators' => [
                    $checkedKey => true,
                    $competencyId.':1:1' => false,
                ],
                'score' => 0.25,
                'note' => 'ประเมินตนเอง',
            ])
            ->assertSessionHasNoErrors();

        $assessment = Assessment::where('user_id', $employee->id)
            ->where('competency_id', $competencyId)
            ->firstOrFail();

        $this->assertDatabaseHas('assessment_indicator_results', [
            'assessment_id' => $assessment->id,
            'competency_id' => $competencyId,
            'indicator_key' => $checkedKey,
            'is_checked' => true,
            'checked_by' => $employee->id,
        ]);
        $this->assertDatabaseMissing('assessment_evidences', [
            'assessment_id' => $assessment->id,
            'competency_id' => $competencyId,
        ]);

        $checked = $this->actingAs($employee)
            ->get(route('assessments.load', ['competency_id' => $competencyId]))
            ->assertOk()
            ->json('checked');

        $this->assertTrue($checked[$checkedKey] ?? false);
    }

    private function assessment(User $user, int $competencyId, string $status): Assessment
    {
        $values = [
            'user_id' => $user->id,
            'competency_id' => $competencyId,
            'score' => 0,
            'note' => '',
            'status' => $status,
        ];

        if (Schema::hasColumn('assessments', 'assessment_round_id')) {
            $values['assessment_round_id'] = $this->assessmentRoundId();
        }

        $assessment = Assessment::create($values);

        DB::table('competency_gaps')->insert([
            'assessment_id' => $assessment->id,
            'competency_id' => $competencyId,
            'actual_level' => 0,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $assessment;
    }

    private function competencyId(string $code = 'CC-TEST'): int
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

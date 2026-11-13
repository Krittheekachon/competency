<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\FacultyAssessmentPermissionResolver;
use App\Services\FacultyIdpPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Concerns\BuildsFacultyAssessment;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/** Regression coverage for faculty workflow integrity. */
class FacultyWorkflowIntegrityTest extends TestCase
{
    use RefreshDatabase, BuildsFacultyAssessment;

    #[DataProvider('approvalOnlyCases')]
    public function test_final_approval_without_scorer_keeps_self_score(string $workline, string $role, string $type): void
    {
        Mail::fake();
        $reviewer=User::factory()->create(['role_id'=>$this->roleId('assistant_dean')]);
        $employee=User::factory()->create(['role_id'=>$this->roleId($role)]);
        $round=$this->activeFacultyRound();
        [$cid]=$this->makeAssessable($employee,$round,$workline);
        if ($type === 'MC') {
            DB::table('competency_types')->where('id',DB::table('competencies')->where('id',$cid)->value('competency_type_id'))->update(['code'=>'MC']);
        }
        $this->assignReviewer($employee,$reviewer);
        $aid=$this->submittedAssessment($employee,$round,$cid,3);
        // A dean in the chain must not gain MC scoring rights for these targets.
        if ($type === 'MC') {
            $reviewer->forceFill(['role_id'=>$this->roleId('dean')])->save();
        }
        $this->actingAs($reviewer)->post(route('assessments.approve'),['user_id'=>$employee->id,'competency_id'=>$cid])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('assessments',['id'=>$aid,'status'=>'approved','score'=>3]);
        $this->assertDatabaseHas('competency_gaps',['assessment_id'=>$aid,'status'=>'approved','actual_level'=>3]);
        $this->assertSame(0,DB::table('scores')->where('assessment_id',$aid)->whereNotNull('score')->count());
        $this->actingAs($reviewer)->postJson(route('assessments.approve'),['user_id'=>$employee->id,'competency_id'=>$cid])->assertUnprocessable();
    }

    public static function approvalOnlyCases(): array
    {
        return [
            'support CC without deputy' => ['สายสนับสนุน','employee','CC'],
            'academic head MC with dean' => ['สายวิชาการ','academic_department_head','MC'],
            'support employee MC with dean' => ['สายสนับสนุน','employee','MC'],
            'support head MC with dean' => ['สายสนับสนุน','supervisor','MC'],
            'management MC approval only' => ['สายงานบริหาร','employee','MC'],
        ];
    }

    public function test_standard_method_keeps_approval_only_chain_behavior(): void
    {
        Mail::fake();
        $reviewer=User::factory()->create(['role_id'=>$this->roleId('assistant_dean')]);
        $employee=User::factory()->create();$round=$this->activeFacultyRound();
        DB::table('assessment_rounds')->where('id',$round)->update(['assessment_method'=>'standard']);
        [$cid]=$this->makeAssessable($employee,$round);$this->assignReviewer($employee,$reviewer);
        $aid=$this->submittedAssessment($employee,$round,$cid,3);
        $this->actingAs($reviewer)->post(route('assessments.approve'),['user_id'=>$employee->id,'competency_id'=>$cid])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('assessments',['id'=>$aid,'status'=>'approved']);
    }

    public function test_legacy_support_user_with_unclassified_title_can_receive_deputy_score(): void
    {
        Mail::fake();
        $round = $this->activeFacultyRound();
        $employee = User::factory()->create();
        [$cid, $levels] = $this->makeAssessable($employee, $round, 'สายสนับสนุน');
        DB::table('position_titles')->where('id', $employee->position_title_id)->update(['workline_type' => null]);
        $reviewer = User::factory()->create(['role_id' => $this->roleId('deputy_dean')]);
        $this->assignReviewer($employee, $reviewer, 1, 'CC,FC');
        $aid = $this->submittedAssessment($employee, $round, $cid, 3);
        $this->actingAs($reviewer)->post(route('assessments.approve'), [
            'user_id' => $employee->id,
            'competency_id' => $cid,
            'checked' => ["{$cid}:{$levels[1]}:0", "{$cid}:{$levels[1]}:1", "{$cid}:{$levels[1]}:2"],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('assessments', ['id' => $aid, 'status' => 'approved', 'score' => 3]);
        $this->assertDatabaseHas('scores', ['assessment_id' => $aid, 'assessor_id' => $reviewer->id, 'score' => 2]);
        $this->assertDatabaseHas('competency_gaps', ['assessment_id' => $aid, 'status' => 'approved', 'actual_level' => 2]);
    }

    public function test_final_approver_requires_each_configured_scorer_score(): void
    {
        $round=$this->activeFacultyRound();$employee=User::factory()->create();
        [$cid]=$this->makeAssessable($employee,$round);
        $first=User::factory()->create(['role_id'=>$this->roleId('academic_department_head')]);
        $second=User::factory()->create(['role_id'=>$this->roleId('academic_department_head')]);
        $final=User::factory()->create(['role_id'=>$this->roleId('assistant_dean')]);
        foreach([$first,$second,$final]as$i=>$reviewer)$this->assignReviewer($employee,$reviewer,$i+1,$reviewer->id===$final->id?null:'CC');
        $aid=$this->submittedAssessment($employee,$round,$cid,2);
        DB::table('assessments')->where('id',$aid)->update(['status'=>'dept_evaluated']);
        DB::table('competency_gaps')->where('assessment_id',$aid)->update(['status'=>'dept_evaluated']);
        DB::table('scores')->insert(['assessment_id'=>$aid,'competency_id'=>$cid,'assessor_id'=>$first->id,'assessor_role'=>'supervisor_1','score'=>3,'status'=>'approved']);
        $this->actingAs($final)->postJson(route('assessments.approve'),['user_id'=>$employee->id,'competency_id'=>$cid])->assertUnprocessable()->assertJsonValidationErrors('assessment');
        $this->assertDatabaseHas('assessments',['id'=>$aid,'status'=>'dept_evaluated']);
        $this->assertDatabaseMissing('scores',['assessment_id'=>$aid,'assessor_id'=>$final->id]);
    }

    public function test_draft_is_preserved_until_all_results_complete_and_locked_plan_omissions_are_reported(): void
    {
        Mail::fake();
        $round=$this->activeFacultyRound();
        $employee=User::factory()->create();
        $reviewer=User::factory()->create(['role_id'=>$this->roleId('supervisor')]);
        [$cid]=$this->makeAssessable($employee,$round);
        DB::table('user_reviewer_steps')->insert(['user_id'=>$employee->id,'reviewer_id'=>$reviewer->id,'chain_type'=>'idp','step_order'=>1]);
        $a=$this->submittedAssessment($employee,$round,$cid,2);
        DB::table('assessments')->where('id',$a)->update(['status'=>'approved']);
        DB::table('competency_gaps')->where('assessment_id',$a)->update(['status'=>'approved']);
        $gapA=DB::table('competency_gaps')->where('assessment_id',$a)->value('id');
        $type=DB::table('competencies')->where('id',$cid)->value('competency_type_id');
        $cidB=DB::table('competencies')->insertGetId(['competency_type_id'=>$type,'code'=>'QA_ASTRA_B','name'=>'QA_ASTRA_B']);
        DB::table('position_competencies')->insert(['assessment_round_id'=>$round,'position_title_id'=>$employee->position_title_id,'position_id'=>$employee->position_id,'competency_id'=>$cidB]);
        DB::table('learning_method_types')->insert(['key'=>'experiential-learning','label'=>'Experiential','is_active'=>true]);
        $tool=DB::table('idp_learning_methods')->insertGetId(['focus_type'=>'experiential','title'=>'Project Assignment','is_active'=>true]);
        $item=['competencyGapId'=>$gapA,'goal'=>'QA_ASTRA_goal','successCriteria'=>'QA_ASTRA_criteria','activities'=>[['methodKey'=>'experiential-learning','developmentToolId'=>$tool,'weightPercent'=>100,'startDate'=>'2026-12-01','endDate'=>'2027-01-31']]];
        $this->actingAs($employee)->postJson(route('employee.idp.draft'),['items'=>[$item]])->assertOk();
        $this->postJson(route('employee.idp.submit-item'),['item'=>$item])->assertUnprocessable()->assertJsonValidationErrors('items');
        $plan=DB::table('idp_items')->first();
        $this->assertSame('draft',$plan->status);
        $this->assertDatabaseHas('idp_item_competency_gaps',['idp_item_id'=>$plan->id,'competency_gap_id'=>$gapA]);
        $this->assertContains('QA_ASTRA_B',app(FacultyIdpPlanService::class)->readiness($employee->id,$round)['pending']);
        $b=$this->submittedAssessment($employee,$round,$cidB,2);
        $gapB=DB::table('competency_gaps')->where('assessment_id',$b)->value('id');
        $this->postJson(route('employee.idp.submit-item'),['item'=>$item])->assertUnprocessable();
        // A later approval event is represented solely inside the disposable test database.
        DB::table('assessments')->where('id',$b)->update(['status'=>'approved']);
        DB::table('competency_gaps')->where('id',$gapB)->update(['status'=>'approved']);
        $this->assertCount(2,app(FacultyIdpPlanService::class)->failedGaps($employee->id,$round));
        $this->post(route('employee.idp.submit-item'),['item'=>$item])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('idp_item_competency_gaps',['idp_item_id'=>$plan->id,'competency_gap_id'=>$gapB]);
        $this->post(route('employee.idp.submit-item'),['item'=>$item])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('idp_items',['id'=>$plan->id,'submission_version'=>1,'status'=>'review_step_1','goal'=>'QA_ASTRA_goal']);
        DB::table('idp_item_competency_gaps')->where('idp_item_id',$plan->id)->where('competency_gap_id',$gapB)->delete();
        $this->postJson(route('employee.idp.draft'),['items'=>[[...$item,'goal'=>'must not replace locked plan']]])->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertDatabaseHas('idp_items',['id'=>$plan->id,'goal'=>'QA_ASTRA_goal']);
        $state=app(FacultyIdpPlanService::class)->readiness($employee->id,$round);
        $this->assertSame((int)$gapB,$state['missingGaps'][0]['gapId']);
        $this->assertDatabaseMissing('idp_item_competency_gaps',['idp_item_id'=>$plan->id,'competency_gap_id'=>$gapB]);
        DB::table('idp_items')->where('id',$plan->id)->update(['status'=>'approved']);
        $this->postJson(route('employee.idp.submit-item'),['item'=>$item])->assertUnprocessable();
        $this->assertDatabaseHas('idp_items',['id'=>$plan->id,'status'=>'approved','goal'=>'QA_ASTRA_goal']);
        $this->assertDatabaseCount('idp_items',1);
    }

    public function test_used_dictionary_preserves_ids_and_rejects_criteria_edits_and_deletion(): void
    {
        $round=$this->activeFacultyRound();
        $employee=User::factory()->create();
        $head=User::factory()->create(['role_id'=>$this->roleId('academic_department_head')]);
        [$cid,$levels]=$this->makeAssessable($employee,$round);
        $this->assignReviewer($employee,$head);
        $aid=$this->submittedAssessment($employee,$round,$cid,2);
        $key="$cid:{$levels[1]}:0";
        DB::table('assessment_indicator_results')->insert(['assessment_id'=>$aid,'competency_id'=>$cid,'indicator_key'=>$key,'is_checked'=>true,'checked_by'=>$employee->id]);
        $admin=User::factory()->create(['role_id'=>$this->roleId('admin')]);
        $c=DB::table('competencies')->where('id',$cid)->first();
        $definitions=DB::table('competency_levels')->where('competency_id',$cid)->orderBy('level')->get()->map(fn($l)=>['level'=>$l->level,'description'=>$l->description,'indicators'=>DB::table('comp_level_indicators')->where('competency_level_id',$l->id)->orderBy('id')->get(['description','weight'])->map(fn($r)=>(array)$r)->all()])->all();
        $payload=['competency_type_id'=>$c->competency_type_id,'code'=>$c->code,'name'=>$c->name,'detail'=>'QA_ASTRA metadata edit','levels'=>$definitions];
        $this->actingAs($admin)->put(route('admin.competencies.update',$cid),$payload)->assertSessionHasNoErrors();
        $this->assertSame(3,DB::table('competency_levels')->whereIn('id',array_values($levels))->count());
        $this->assertDatabaseHas('assessment_indicator_results',['assessment_id'=>$aid,'indicator_key'=>$key]);
        $payload['levels'][0]['indicators'][0]['description']='changed criteria';
        $this->putJson(route('admin.competencies.update',$cid),$payload)->assertUnprocessable()->assertJsonValidationErrors('levels');
        $this->deleteJson(route('admin.competencies.destroy',$cid))->assertUnprocessable()->assertJsonValidationErrors('competency');
        $this->actingAs($head)->postJson(route('assessments.approve'),['user_id'=>$employee->id,'competency_id'=>$cid,'checked'=>[$key]])->assertRedirect();
        $this->assertDatabaseHas('assessments',['id'=>$aid,'status'=>'approved','score'=>2]);
    }

    public function test_database_rejects_duplicate_person_round_plans(): void
    {
        $round=$this->activeFacultyRound();$employee=User::factory()->create();
        $row=['user_id'=>$employee->id,'assessment_round_id'=>$round,'year'=>2569,'status'=>'draft'];
        DB::table('idps')->insert($row);
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('idps')->insert($row);
    }

    public function test_standard_parent_still_allows_many_competency_items(): void
    {
        $round=$this->activeFacultyRound();$employee=User::factory()->create();
        DB::table('assessment_rounds')->where('id',$round)->update(['assessment_method'=>'standard']);
        $idp=DB::table('idps')->insertGetId(['user_id'=>$employee->id,'assessment_round_id'=>$round,'year'=>2569,'status'=>'draft']);
        foreach(['competency-gap:1','competency-gap:2']as$key)DB::table('idp_items')->insert(['idp_id'=>$idp,'status'=>'draft','behavior_key'=>$key]);
        $this->assertDatabaseCount('idp_items',2);
    }

    public function test_database_rejects_second_faculty_item_in_same_plan(): void
    {
        $round=$this->activeFacultyRound();$employee=User::factory()->create();
        $idp=DB::table('idps')->insertGetId(['user_id'=>$employee->id,'assessment_round_id'=>$round,'year'=>2569,'status'=>'draft']);
        $row=['idp_id'=>$idp,'status'=>'draft','behavior_key'=>'faculty-plan'];DB::table('idp_items')->insert($row);
        $this->expectException(\Illuminate\Database\QueryException::class);DB::table('idp_items')->insert($row);
    }

    public function test_migration_stops_on_existing_duplicates_without_merging_plans(): void
    {
        DB::statement('DROP INDEX idps_user_round_unique');
        $round=$this->activeFacultyRound();$employee=User::factory()->create();
        $row=['user_id'=>$employee->id,'assessment_round_id'=>$round,'year'=>2569,'status'=>'draft'];
        DB::table('idps')->insert($row);DB::table('idps')->insert($row);
        $migration=require database_path('migrations/2026_11_04_000001_add_idp_uniqueness_guards.php');
        try {$migration->up();$this->fail('Expected duplicate preflight rejection');}
        catch(\RuntimeException $e){$this->assertStringContainsString('Cannot add IDP uniqueness guards',$e->getMessage());}
        $this->assertDatabaseCount('idps',2);
    }

    public function test_active_round_assignment_protects_criteria_before_any_assessment_exists(): void
    {
        $round=$this->activeFacultyRound();$employee=User::factory()->create();[$cid,$levels]=$this->makeAssessable($employee,$round);
        $admin=User::factory()->create(['role_id'=>$this->roleId('admin')]);$c=DB::table('competencies')->where('id',$cid)->first();
        $this->actingAs($admin)->putJson(route('admin.competencies.update',$cid),['competency_type_id'=>$c->competency_type_id,'code'=>$c->code,'name'=>$c->name,'levels'=>[['level'=>1,'description'=>'new','indicators'=>[['description'=>'new']]]]])->assertUnprocessable()->assertJsonValidationErrors('levels');
        $this->assertSame(3,DB::table('competency_levels')->whereIn('id',array_values($levels))->count());
        $this->assertDatabaseCount('assessments',0);
    }

    public function test_submitted_fc_topic_protects_inactive_round_criteria_before_self_assessment(): void
    {
        $round=$this->activeFacultyRound();$employee=User::factory()->create();[$cid]=$this->makeAssessable($employee,$round);
        $type=DB::table('competency_types')->insertGetId(['code'=>'FC1','full_name'=>'FC1','description'=>'FC1']);
        DB::table('competencies')->where('id',$cid)->update(['competency_type_id'=>$type]);
        DB::table('assessment_rounds')->where('id',$round)->update(['is_active'=>false]);
        $selection=DB::table('fc_topic_selections')->insertGetId(['user_id'=>$employee->id,'assessment_round_id'=>$round,'position_id'=>$employee->position_id,'position_title_id'=>$employee->position_title_id,'status'=>'submitted']);
        DB::table('fc_topic_selection_items')->insert(['fc_topic_selection_id'=>$selection,'competency_id'=>$cid]);
        $admin=User::factory()->create(['role_id'=>$this->roleId('admin')]);$c=DB::table('competencies')->where('id',$cid)->first();
        $this->actingAs($admin)->putJson(route('admin.competencies.update',$cid),['competency_type_id'=>$type,'code'=>$c->code,'name'=>$c->name,'levels'=>[['level'=>1,'indicators'=>[['description'=>'new criterion']]]]])->assertUnprocessable()->assertJsonValidationErrors('levels');
        $this->assertDatabaseCount('assessments',0);
    }

    public function test_round_snapshot_classification_is_used_after_current_position_changes(): void
    {
        Mail::fake();$round=$this->activeFacultyRound();$employee=User::factory()->create();[$cid,$levels]=$this->makeAssessable($employee,$round);
        $head=User::factory()->create(['role_id'=>$this->roleId('academic_department_head')]);$this->assignReviewer($employee,$head);
        app(\App\Services\AssessmentRoundSnapshotService::class)->captureIfMissing($employee,$round);
        $snapshot=DB::table('user_round_profiles')->where('user_id',$employee->id)->first();
        $newTitle=DB::table('position_titles')->insertGetId(['name'=>'unclassified new title','normalized_name'=>'unclassified-new','workline_type'=>'unknown']);
        $employee->forceFill(['position_title_id'=>$newTitle,'workline'=>'ชื่อใหม่'])->save();$aid=$this->submittedAssessment($employee,$round,$cid,2);
        $this->actingAs($head)->postJson(route('assessments.approve'),['user_id'=>$employee->id,'competency_id'=>$cid,'checked'=>["$cid:{$levels[1]}:0"]])->assertRedirect();
        $this->assertDatabaseHas('assessments',['id'=>$aid,'status'=>'approved']);
        $this->assertDatabaseHas('user_round_profiles',['id'=>$snapshot->id,'position_title_id'=>$snapshot->position_title_id]);
        $this->assertDatabaseHas('users',['id'=>$employee->id,'position_title_id'=>$newTitle]);
    }

    public function test_completeness_requires_fc_selection_and_only_selected_fc_topics(): void
    {
        $round=$this->activeFacultyRound();$employee=User::factory()->create();[$cid]=$this->makeAssessable($employee,$round);
        $aid=$this->submittedAssessment($employee,$round,$cid,2);
        DB::table('assessments')->where('id',$aid)->update(['status'=>'approved']);DB::table('competency_gaps')->where('assessment_id',$aid)->update(['status'=>'approved']);
        $type=DB::table('competency_types')->insertGetId(['code'=>'FC1','full_name'=>'FC1','description'=>'FC1']);$fc=[];
        foreach([1,2]as$n){
            $fc[$n]=DB::table('competencies')->insertGetId(['competency_type_id'=>$type,'code'=>'QA_FC_'.$n,'name'=>'QA FC '.$n]);
            DB::table('position_competencies')->insert(['assessment_round_id'=>$round,'position_id'=>$employee->position_id,'position_title_id'=>$employee->position_title_id,'competency_id'=>$fc[$n]]);
        }
        DB::table('position_fc_selection_rules')->insert(['assessment_round_id'=>$round,'position_id'=>$employee->position_id,'position_title_id'=>$employee->position_title_id,'required_fc_count'=>1]);
        $service=app(FacultyIdpPlanService::class);$this->assertFalse($service->readiness($employee->id,$round)['ready']);
        $selection=DB::table('fc_topic_selections')->insertGetId(['user_id'=>$employee->id,'assessment_round_id'=>$round,'position_id'=>$employee->position_id,'position_title_id'=>$employee->position_title_id,'status'=>'approved']);
        DB::table('fc_topic_selection_items')->insert(['fc_topic_selection_id'=>$selection,'competency_id'=>$fc[1]]);
        $fid=$this->submittedAssessment($employee,$round,$fc[1],3);
        DB::table('assessments')->where('id',$fid)->update(['status'=>'approved']);DB::table('competency_gaps')->where('assessment_id',$fid)->update(['status'=>'approved']);
        $unselected=$this->submittedAssessment($employee,$round,$fc[2],0);
        DB::table('assessments')->where('id',$unselected)->update(['status'=>'draft']);
        $state=$service->readiness($employee->id,$round);$this->assertTrue($state['ready']);
        $this->assertEqualsCanonicalizing([$cid,$fc[1]],$state['requiredCompetencyIds']);
    }

    public function test_scoring_follows_the_admin_grant_per_reviewer_and_type_regardless_of_role_or_workline(): void
    {
        $employee=User::factory()->create(['workline'=>'สายสนับสนุน']);
        $granted=User::factory()->create(['role_id'=>$this->roleId('employee')]);
        $approvalOnly=User::factory()->create(['role_id'=>$this->roleId('deputy_dean')]);
        $this->assignReviewer($employee,$granted,1,'CC,FC');
        $this->assignReviewer($employee,$approvalOnly,2);
        $resolver=app(FacultyAssessmentPermissionResolver::class);
        $this->assertTrue($resolver->canScore($granted,$employee,'CC'));
        $this->assertTrue($resolver->canScore($granted,$employee,'FC1'));
        $this->assertFalse($resolver->canScore($granted,$employee,'MC'));
        $this->assertFalse($resolver->canScore($approvalOnly,$employee,'CC'));
        $outsider=User::factory()->create(['role_id'=>$this->roleId('dean')]);
        $this->assertFalse($resolver->canScore($outsider,$employee,'CC'));
    }
}

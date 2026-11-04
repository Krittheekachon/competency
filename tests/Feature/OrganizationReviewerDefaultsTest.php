<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OrganizationChartService;
use App\Services\OrganizationReviewerDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrganizationReviewerDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private function setupPath(): array
    {
        $department = DB::table('support_departments')->insertGetId(['name'=>'ฝ่ายทดสอบ']);
        $work = DB::table('support_works')->insertGetId(['name'=>'งานทดสอบ','support_department_id'=>$department]);
        $unit = DB::table('support_units')->insertGetId(['name'=>'หน่วยทดสอบ','support_work_id'=>$work]);
        $user = User::factory()->create(['workline'=>'สายสนับสนุน','department'=>'ฝ่ายทดสอบ > งานทดสอบ > หน่วยทดสอบ']);
        return [$user, ['unit:'.$unit,'work:'.$work,'department:'.$department]];
    }

    private function assign(string $key, User $head): void
    {
        DB::table('organization_chart_assignments')->updateOrInsert(['node_key'=>$key], ['head_user_id'=>$head->id,'members_overridden'=>false]);
    }

    private function preview(User $user): array
    {
        return app(OrganizationReviewerDefaults::class)->forUser($user, app(OrganizationChartService::class)->payload());
    }

    public function test_default_preset_previews_chart_heads_nearest_first_even_when_template_has_fixed_people(): void
    {
        [$user,$keys]=$this->setupPath();
        $heads=User::factory()->count(3)->create();
        foreach ($keys as $i=>$key) $this->assign($key,$heads[$i]);
        $before=DB::table('user_reviewer_steps')->get()->toJson();
        $this->assertSame($heads->pluck('id')->all(),$this->preview($user)['reviewerIds']);
        $admin=User::factory()->create(['role_id'=>DB::table('roles')->where('key','admin')->value('id')]);
        $template=DB::table('reviewer_chain_templates')->where('is_default',true)->where('chain_type','assessment')->first();
        DB::table('reviewer_chain_template_steps')->where('template_id',$template->id)->update(['resolver_type'=>'fixed_user','reviewer_id'=>$admin->id]);
        $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn($page)=>$page
            ->where('organizationReviewerDefaults.'.$user->id.'.reviewerIds',$heads->pluck('id')->all())
            ->where('reviewerChainTemplates',fn($templates)=>collect($templates)->firstWhere('id',$template->id)['structureDefault'] === true));
        $this->assertSame($before,DB::table('user_reviewer_steps')->get()->toJson());
    }

    public function test_missing_duplicate_self_and_inactive_heads_are_reported_without_guessing(): void
    {
        [$user,$keys]=$this->setupPath();
        $head=User::factory()->create();
        $this->assign($keys[0],$user);$this->assign($keys[1],$head);$this->assign($keys[2],$head);
        $preview=$this->preview($user);
        $this->assertSame([$head->id],$preview['reviewerIds']);
        $this->assertStringContainsString('ข้ามตัวผู้ถูกประเมินเอง',implode(' ',$preview['warnings']));
        $this->assertStringContainsString('หัวหน้าซ้ำ',implode(' ',$preview['warnings']));
        $head->update(['is_active'=>false]);$preview=$this->preview($user);
        $this->assertSame([],$preview['reviewerIds']);$this->assertNotNull($preview['error']);
        DB::table('organization_chart_assignments')->delete();
        $preview=$this->preview($user);$this->assertCount(3,$preview['warnings']);$this->assertSame([],$preview['reviewerIds']);
    }

    public function test_ambiguous_affiliation_is_not_resolved_by_first_match_or_chart_member_override(): void
    {
        [$user,$keys]=$this->setupPath();
        $user->update(['department'=>'หน่วยทดสอบ']);
        $workId=DB::table('support_works')->value('id');
        $departmentId=DB::table('support_departments')->insertGetId(['name'=>'อีกฝ่าย']);
        $workId=DB::table('support_works')->insertGetId(['name'=>'อีกงาน','support_department_id'=>$departmentId]);
        DB::table('support_units')->insert(['name'=>'หน่วยทดสอบ','support_work_id'=>$workId]);
        $this->assertStringContainsString('กำกวม',$this->preview($user)['error']);
        $user->update(['department'=>'ไม่มีสังกัดนี้']);$this->assertSame([],$this->preview($user)['reviewerIds']);
    }
}

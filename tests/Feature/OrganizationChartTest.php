<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OrganizationChartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrganizationChartTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => DB::table('roles')->where('key', 'admin')->value('id')]);
    }

    private function hierarchy(): array
    {
        $department = DB::table('support_departments')->insertGetId(['name' => 'ฝ่ายทดสอบ']);
        $work = DB::table('support_works')->insertGetId(['name' => 'งานทดสอบ', 'support_department_id' => $department]);
        $unit = DB::table('support_units')->insertGetId(['name' => 'หน่วยทดสอบ', 'support_work_id' => $work]);
        return [$department, $work, $unit];
    }

    public function test_chart_uses_real_hierarchy_and_never_infers_head_from_role(): void
    {
        [$department, $work, $unit] = $this->hierarchy();
        $person = User::factory()->create(['workline' => 'สายสนับสนุน', 'department' => 'ฝ่ายทดสอบ > งานทดสอบ > หน่วยทดสอบ']);
        $nodes = collect(app(OrganizationChartService::class)->payload()['nodes'])->keyBy('key');
        $this->assertSame('department:'.$department, $nodes['work:'.$work]['parentKey']);
        $this->assertSame('work:'.$work, $nodes['unit:'.$unit]['parentKey']);
        $this->assertNull($nodes['unit:'.$unit]['head']);
        $this->assertSame([$person->id], array_column($nodes['unit:'.$unit]['members'], 'id'));
    }

    public function test_branch_member_counts_include_all_descendant_units_without_double_counting(): void
    {
        [$department, $work, $unit] = $this->hierarchy();
        $otherUnit = DB::table('support_units')->insertGetId([
            'name' => 'หน่วยทดสอบ 2',
            'support_work_id' => $work,
        ]);
        $path = 'ฝ่ายทดสอบ > งานทดสอบ';
        User::factory()->count(3)->create([
            'workline' => 'สายสนับสนุน',
            'department' => $path.' > หน่วยทดสอบ',
        ]);
        User::factory()->count(4)->create([
            'workline' => 'สายสนับสนุน',
            'department' => $path.' > หน่วยทดสอบ 2',
        ]);

        $nodes = collect(app(OrganizationChartService::class)->payload()['nodes'])->keyBy('key');
        $this->assertSame(3, $nodes['unit:'.$unit]['memberCount']);
        $this->assertSame(4, $nodes['unit:'.$otherUnit]['memberCount']);
        $this->assertSame(7, $nodes['work:'.$work]['memberCount']);
        $this->assertSame(7, $nodes['department:'.$department]['memberCount']);
    }

    public function test_empty_chart_and_short_branches_do_not_create_placeholder_units(): void
    {
        $this->assertSame([], app(OrganizationChartService::class)->payload()['nodes']);
        $id = DB::table('support_departments')->insertGetId(['name' => 'ฝ่ายไม่มีงานย่อย']);
        $nodes = app(OrganizationChartService::class)->payload()['nodes'];
        $this->assertCount(1, $nodes);
        $this->assertSame('department:'.$id, $nodes[0]['key']);
        $this->assertNull($nodes[0]['parentKey']);
        $this->assertSame([], $nodes[0]['members']);
    }

    public function test_dashboard_exposes_chart_only_to_admin(): void
    {
        $this->hierarchy();
        $this->actingAs($this->admin())->get(route('dashboard'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Dashboard')->has('organizationChart.nodes', 3));
        $employee = User::factory()->create(['role_id' => DB::table('roles')->where('key', 'employee')->value('id')]);
        $this->actingAs($employee)->get(route('dashboard'))->assertOk()
            ->assertInertia(fn ($page) => $page->missing('organizationChart'));
    }

    public function test_admin_assignments_do_not_change_profiles_or_reviewer_chains(): void
    {
        [, , $unit] = $this->hierarchy();
        $person = User::factory()->create(['department' => 'สังกัดเดิม']);
        $before = DB::table('user_reviewer_steps')->get()->toJson();
        $this->actingAs($this->admin())->put(route('admin.organization-chart.people.update'), [
            'node_key' => 'unit:'.$unit, 'head_user_id' => $person->id,
            'use_profile_members' => false, 'member_ids' => [$person->id],
        ])->assertSessionHasNoErrors();
        $node = app(OrganizationChartService::class)->node('unit:'.$unit);
        $this->assertSame($person->id, $node['head']['id']);
        $this->assertSame([$person->id], array_column($node['members'], 'id'));
        $this->assertSame('สังกัดเดิม', $person->fresh()->department);
        $this->assertSame($before, DB::table('user_reviewer_steps')->get()->toJson());
        $this->actingAs($this->admin())->put(route('admin.organization-chart.people.update'), [
            'node_key' => 'unit:'.$unit, 'head_user_id' => null,
            'use_profile_members' => true, 'member_ids' => [],
        ])->assertSessionHasNoErrors();
        $this->assertSame('profile', app(OrganizationChartService::class)->node('unit:'.$unit)['membershipSource']);
    }

    public function test_changing_a_chart_head_updates_assessment_reviewers_for_descendant_members(): void
    {
        [$department, $work, $unit] = $this->hierarchy();
        $user = User::factory()->create([
            'workline' => 'สายสนับสนุน',
            'department' => 'ฝ่ายทดสอบ > งานทดสอบ > หน่วยทดสอบ',
        ]);
        $unitHead = User::factory()->create();
        $oldWorkHead = User::factory()->create();
        $newWorkHead = User::factory()->create();
        $departmentHead = User::factory()->create();
        foreach ([
            ['unit:'.$unit, $unitHead],
            ['work:'.$work, $oldWorkHead],
            ['department:'.$department, $departmentHead],
        ] as [$key, $head]) {
            DB::table('organization_chart_assignments')->insert([
                'node_key' => $key,
                'head_user_id' => $head->id,
                'members_overridden' => false,
            ]);
        }
        foreach ([$unitHead, $oldWorkHead, $departmentHead] as $index => $reviewer) {
            DB::table('user_reviewer_steps')->insert([
                'user_id' => $user->id,
                'step_order' => $index + 1,
                'reviewer_id' => $reviewer->id,
                'chain_type' => 'assessment',
            ]);
        }

        $this->actingAs($this->admin())->put(route('admin.organization-chart.people.update'), [
            'node_key' => 'work:'.$work,
            'head_user_id' => $newWorkHead->id,
            'use_profile_members' => true,
            'member_ids' => [],
        ])->assertSessionHasNoErrors()->assertSessionHas('success', fn ($message) => str_contains($message, 'อัปเดตสายการประเมิน'));

        $this->assertSame(
            [$unitHead->id, $newWorkHead->id, $departmentHead->id],
            DB::table('user_reviewer_steps')->where('user_id', $user->id)->where('chain_type', 'assessment')->orderBy('step_order')->pluck('reviewer_id')->all(),
        );
    }

    public function test_non_admin_cannot_write_chart_and_invalid_people_are_rejected(): void
    {
        [, , $unit] = $this->hierarchy();
        $employee = User::factory()->create(['role_id' => DB::table('roles')->where('key', 'employee')->value('id')]);
        $payload = ['node_key' => 'unit:'.$unit, 'head_user_id' => 999999, 'use_profile_members' => false, 'member_ids' => [999999]];
        $this->actingAs($employee)->put(route('admin.organization-chart.people.update'), $payload)->assertForbidden();
        $this->actingAs($this->admin())->put(route('admin.organization-chart.people.update'), $payload)->assertSessionHasErrors(['head_user_id', 'member_ids.0']);
        $payload['head_user_id'] = null; $payload['member_ids'] = []; $payload['node_key'] = 'unit:999999';
        $this->put(route('admin.organization-chart.people.update'), $payload)->assertSessionHasErrors('node_key');
    }

    public function test_existing_delete_routes_protect_children_and_profile_members(): void
    {
        [$department, , $unit] = $this->hierarchy();
        $this->actingAs($this->admin())->delete(route('admin.structure.support-departments.destroy'), ['name' => 'ฝ่ายทดสอบ'])->assertSessionHasErrors('name');
        $this->assertDatabaseHas('support_departments', ['id' => $department]);
        User::factory()->create(['workline' => 'สายสนับสนุน', 'department' => 'ฝ่ายทดสอบ > งานทดสอบ > หน่วยทดสอบ']);
        $this->delete(route('admin.structure.support-units.destroy'), ['division_name' => 'ฝ่ายทดสอบ', 'work_name' => 'งานทดสอบ', 'name' => 'หน่วยทดสอบ'])->assertSessionHasErrors('name');
        $this->assertDatabaseHas('support_units', ['id' => $unit]);
    }

    public function test_leaf_can_be_deleted_and_rename_preserves_legacy_membership(): void
    {
        [, , $unit] = $this->hierarchy();
        $person = User::factory()->create(['workline' => 'สายสนับสนุน', 'department' => 'ฝ่ายทดสอบ > งานทดสอบ']);
        $this->actingAs($this->admin())->put(route('admin.structure.support-works.update'), ['division_name' => 'ฝ่ายทดสอบ', 'old_name' => 'งานทดสอบ', 'name' => 'งานชื่อใหม่'])->assertSessionHasNoErrors();
        $this->assertSame('ฝ่ายทดสอบ > งานชื่อใหม่', $person->fresh()->department);
        $this->delete(route('admin.structure.support-units.destroy'), ['division_name' => 'ฝ่ายทดสอบ', 'work_name' => 'งานชื่อใหม่', 'name' => 'หน่วยทดสอบ'])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('support_units', ['id' => $unit]);
    }
}

<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizationWorklineCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => DB::table('roles')->where('key', 'admin')->value('id')]);
    }

    public function test_rename_preserves_ids_and_round_snapshot_while_syncing_current_people_and_placements(): void
    {
        $this->actingAs($this->admin());
        $wid = DB::table('worklines')->insertGetId(['name' => 'สายบริหาร']);
        $this->post(route('admin.structure.position-titles.store'), ['workline_id' => $wid, 'name' => 'ชื่อเดิม']);
        $title = DB::table('position_titles')->value('id');
        $family = DB::table('job_families')->insertGetId(['workline_id' => $wid, 'name' => 'หน่วยงานทดสอบ']);
        $position = DB::table('positions')->insertGetId(['job_family_id' => $family, 'name' => 'ชื่อเดิม', 'position_title_id' => $title]);
        $user = User::factory()->create(['position_title_id' => $title, 'position' => 'ชื่อเดิม']);
        $legacy = User::factory()->create(['position_id' => $position, 'position' => 'ชื่อเดิม']);
        $other = User::factory()->create(['position' => 'ชื่อเดิม']);
        $round = DB::table('assessment_rounds')->insertGetId(['name' => 'รอบเดิม', 'year' => 2569, 'is_active' => false]);
        DB::table('user_round_profiles')->insert(['assessment_round_id' => $round, 'user_id' => $user->id,
            'position_title_id' => $title, 'position' => 'ชื่อเดิม', 'captured_at' => now()]);
        $this->put(route('admin.structure.position-titles.update'), ['position_title_id' => $title, 'name' => 'ชื่อใหม่'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('position_titles', ['id' => $title, 'name' => 'ชื่อใหม่', 'normalized_name' => 'ชื่อใหม่', 'workline_id' => $wid]);
        $this->assertDatabaseHas('positions', ['id' => $position, 'position_title_id' => $title, 'name' => 'ชื่อใหม่']);
        foreach ([$user, $legacy] as $person) $this->assertDatabaseHas('users', ['id' => $person->id, 'position' => 'ชื่อใหม่']);
        $this->assertDatabaseHas('users', ['id' => $other->id, 'position' => 'ชื่อเดิม']);
        $this->assertDatabaseHas('user_round_profiles', ['user_id' => $user->id, 'position_title_id' => $title, 'position' => 'ชื่อเดิม']);
    }

    public function test_root_position_can_be_renamed_and_unused_title_deleted_but_duplicates_and_non_admin_are_rejected(): void
    {
        $this->actingAs($this->admin());
        $wid = DB::table('worklines')->insertGetId(['name' => 'สายบริหาร']);
        foreach (['คณะกรรมการทดสอบ', 'ชื่อซ้ำ'] as $name) $this->post(route('admin.structure.position-titles.store'), ['workline_id' => $wid, 'name' => $name]);
        $title = DB::table('position_titles')->where('name', 'คณะกรรมการทดสอบ')->value('id');
        $payload = ['position_title_id' => $title, 'name' => 'ชื่อซ้ำ'];
        $this->putJson(route('admin.structure.position-titles.update'), $payload)->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->put(route('admin.structure.position-titles.update'), [...$payload, 'name' => 'คณะกรรมการใหม่'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('position_titles', ['id' => $title, 'name' => 'คณะกรรมการใหม่']);
        $this->actingAs(User::factory()->create());
        $this->putJson(route('admin.structure.position-titles.update'), $payload)->assertForbidden();
        $this->deleteJson(route('admin.structure.position-titles.destroy'), $payload)->assertForbidden();
        $this->actingAs($this->admin());
        DB::table('position_title_aliases')->insert(['position_title_id' => $title, 'name' => 'ชื่อ SSO', 'normalized_name' => 'ชื่อ sso']);
        $this->delete(route('admin.structure.position-titles.destroy'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('position_titles', ['id' => $title]);
        $this->assertDatabaseMissing('position_title_aliases', ['position_title_id' => $title]);
        $this->deleteJson(route('admin.structure.position-titles.destroy'), $payload)->assertUnprocessable();
    }

    public function test_used_position_cannot_be_deleted_or_silently_detached(): void
    {
        $this->actingAs($this->admin());
        $wid = DB::table('worklines')->insertGetId(['name' => 'สายบริหาร']);
        $this->post(route('admin.structure.position-titles.store'), ['workline_id' => $wid, 'name' => 'ผู้ช่วยทดสอบ']);
        $title = DB::table('position_titles')->value('id');
        $payload = ['position_title_id' => $title];
        $user = User::factory()->create(['position_title_id' => $title]);
        $this->deleteJson(route('admin.structure.position-titles.destroy'), $payload)->assertUnprocessable()->assertJsonValidationErrors('position_title_id');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'position_title_id' => $title]);
        $user->delete();
        $round = DB::table('assessment_rounds')->insertGetId(['name' => 'รอบที่ใช้', 'year' => 2569, 'is_active' => false]);
        $family = DB::table('job_families')->insertGetId(['workline_id' => $wid, 'name' => 'หน่วยงานเดิม']);
        $position = DB::table('positions')->insertGetId(['job_family_id' => $family, 'name' => 'ตำแหน่งเดิม']);
        DB::table('position_fc_selection_rules')->insert(['assessment_round_id' => $round, 'position_id' => $position, 'position_title_id' => $title, 'required_fc_count' => 0]);
        $this->deleteJson(route('admin.structure.position-titles.destroy'), $payload)->assertUnprocessable()->assertJsonValidationErrors('position_title_id');
        $this->assertDatabaseHas('position_fc_selection_rules', ['position_title_id' => $title]);
        $this->assertDatabaseHas('position_titles', ['id' => $title]);
    }

    public function test_catalog_uses_any_actual_chart_workline_and_follows_rename_by_id(): void
    {
        $this->actingAs($this->admin());
        $id = DB::table('worklines')->insertGetId(['name' => 'สายวิจัยและนวัตกรรม']);
        $this->post(route('admin.structure.position-titles.store'), ['workline_id' => $id, 'name' => 'ตำแหน่งทดสอบ'])
            ->assertSessionHasNoErrors();
        $title = DB::table('position_titles')->first();
        $this->assertSame($id, $title->workline_id);
        $this->assertNull($title->workline_type); // A new organization name grants no scoring authority.
        $this->postJson(route('admin.structure.position-titles.store'), ['workline_id' => $id, 'name' => 'ตำแหน่งทดสอบ'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->put(route('admin.structure.worklines.update'), ['old_name' => 'สายวิจัยและนวัตกรรม', 'name' => 'สายงานที่ผู้ดูแลเปลี่ยนชื่อ'])
            ->assertSessionHasNoErrors();
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('organizationWorklines.0.id', $id)
            ->where('organizationWorklines.0.name', 'สายงานที่ผู้ดูแลเปลี่ยนชื่อ')
            ->where('positionTitles.0.worklineId', $id)
            ->where('positionTitles.0.worklineName', 'สายงานที่ผู้ดูแลเปลี่ยนชื่อ')
        );
        $this->assertDatabaseHas('position_titles', ['id' => $title->id, 'workline_id' => $id]);
        $this->deleteJson(route('admin.structure.worklines.destroy'), ['name' => 'สายงานที่ผู้ดูแลเปลี่ยนชื่อ'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_same_position_name_can_exist_under_distinct_chart_ids(): void
    {
        $this->actingAs($this->admin());
        foreach (['สายบริหาร', 'สายงานบริหาร'] as $name) {
            $id = DB::table('worklines')->insertGetId(['name' => $name]);
            $this->post(route('admin.structure.position-titles.store'), ['workline_id' => $id, 'name' => 'ผู้อำนวยการ'])
                ->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('position_titles', 2);
        $this->postJson(route('admin.structure.position-titles.store'), ['workline_id' => 99999, 'name' => 'ตำแหน่งผิดสาย'])
            ->assertUnprocessable()->assertJsonValidationErrors('workline_id');
    }

    public function test_only_management_can_add_workline_level_positions_without_fake_departments(): void
    {
        $this->actingAs($this->admin());
        $management = DB::table('worklines')->insertGetId(['name' => 'สายบริหาร']);
        $support = DB::table('worklines')->insertGetId(['name' => 'สายสนับสนุน']);
        $academic = DB::table('worklines')->insertGetId(['name' => 'สายวิชาการ']);
        $data = ['name' => 'คณบดี', 'workline_id' => $management, 'placement_scope' => 'workline'];
        $this->post(route('admin.structure.position-titles.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('position_titles', ['name' => $data['name'], 'workline_id' => $management]);
        $this->assertDatabaseCount('job_families', 0);
        $this->assertDatabaseCount('positions', 0);
        $this->postJson(route('admin.structure.position-titles.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('name');
        foreach ([$support, $academic] as $id) {
            $this->postJson(route('admin.structure.position-titles.store'), [...$data, 'workline_id' => $id])
                ->assertUnprocessable()->assertJsonValidationErrors('workline_id');
        }
        $this->assertDatabaseCount('position_titles', 1);
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('positionTitles.0.worklineId', $management)
            ->where('positionTitles.0.isUnassigned', false)
            ->where('positionTitles.0.placementCount', 0)
        );
    }

    public function test_workline_add_selects_existing_catalog_id_and_remove_keeps_catalog_and_allows_readd(): void
    {
        $this->actingAs($this->admin());
        $wid = DB::table('worklines')->insertGetId(['name' => 'สายบริหาร']);
        $support = DB::table('worklines')->insertGetId(['name' => 'สายสนับสนุน']);
        $this->post(route('admin.structure.position-titles.store'), ['workline_id' => $wid, 'name' => 'คณบดี']);
        $id = DB::table('position_titles')->value('id');
        $data = ['position_title_id' => $id, 'workline_id' => $wid, 'name' => 'ห้ามเปลี่ยนชื่อ'];
        $this->postJson(route('admin.structure.workline-positions.store'), ['workline_id' => $wid, 'name' => 'สร้างเองไม่ได้'])
            ->assertUnprocessable()->assertJsonValidationErrors('position_title_id');
        $this->postJson(route('admin.structure.workline-positions.store'), [...$data, 'workline_id' => $support])
            ->assertUnprocessable()->assertJsonValidationErrors('position_title_id');
        $this->post(route('admin.structure.workline-positions.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('position_titles', 1);
        $this->assertDatabaseHas('position_titles', ['id' => $id, 'name' => 'คณบดี', 'is_workline_position' => true]);
        $this->postJson(route('admin.structure.workline-positions.store'), $data)->assertUnprocessable();
        $this->delete(route('admin.structure.workline-positions.destroy'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('position_titles', ['id' => $id, 'name' => 'คณบดี', 'is_workline_position' => false]);
        $this->post(route('admin.structure.workline-positions.store'), $data)->assertSessionHasNoErrors();
        User::factory()->create(['position_title_id' => $id, 'position_id' => null]);
        $this->deleteJson(route('admin.structure.workline-positions.destroy'), $data)->assertUnprocessable();
        $this->assertDatabaseHas('position_titles', ['id' => $id, 'is_workline_position' => true]);
        $this->actingAs(User::factory()->create());
        $this->postJson(route('admin.structure.workline-positions.store'), $data)->assertForbidden();
        $this->deleteJson(route('admin.structure.workline-positions.destroy'), $data)->assertForbidden();
    }

    public function test_workline_positions_reject_other_titles_and_directors_with_department_placements(): void
    {
        $this->actingAs($this->admin());
        $wid = DB::table('worklines')->insertGetId(['name' => 'สายบริหาร']);
        foreach (['คณะกรรมการ', 'รองคณบดี', 'หัวหน้างาน'] as $name) {
            $this->postJson(route('admin.structure.position-titles.store'), [
                'workline_id' => $wid, 'name' => $name, 'placement_scope' => 'workline',
            ])->assertUnprocessable()->assertJsonValidationErrors('name');
            $this->post(route('admin.structure.position-titles.store'), ['workline_id' => $wid, 'name' => $name])->assertSessionHasNoErrors();
            $id = DB::table('position_titles')->where('name', $name)->value('id');
            $this->postJson(route('admin.structure.workline-positions.store'), [
                'workline_id' => $wid, 'position_title_id' => $id,
            ])->assertUnprocessable()->assertJsonValidationErrors('position_title_id');
            $this->assertDatabaseHas('position_titles', ['id' => $id, 'is_workline_position' => false]);
        }
        $this->post(route('admin.structure.position-titles.store'), ['workline_id' => $wid, 'name' => 'ผู้อำนวยการ'])->assertSessionHasNoErrors();
        $id = DB::table('position_titles')->where('name', 'ผู้อำนวยการ')->value('id');
        $family = DB::table('job_families')->insertGetId(['workline_id' => $wid, 'name' => 'หน่วยงาน']);
        DB::table('positions')->insert(['job_family_id' => $family, 'position_title_id' => $id, 'name' => 'ผู้อำนวยการ']);
        $this->postJson(route('admin.structure.workline-positions.store'), [
            'workline_id' => $wid, 'position_title_id' => $id,
        ])->assertUnprocessable()->assertJsonValidationErrors('position_title_id');
        $this->assertDatabaseHas('position_titles', ['id' => $id, 'is_workline_position' => false]);
    }

    public function test_department_selection_uses_canonical_name_and_removing_unused_placement_keeps_title(): void
    {
        $this->actingAs($this->admin());
        $wid = DB::table('worklines')->insertGetId(['name' => 'สายวิชาการ']);
        $family = DB::table('job_families')->insertGetId(['workline_id' => $wid, 'name' => 'ภาคทดสอบ']);
        $this->post(route('admin.structure.position-titles.store'), ['workline_id' => $wid, 'name' => 'ตำแหน่งทะเบียน']);
        $id = DB::table('position_titles')->value('id');
        $data = ['workline_name' => 'สายวิชาการ', 'job_family_name' => 'ภาคทดสอบ', 'position_title_id' => $id, 'name' => 'แอบแก้ชื่อ'];
        $this->post(route('admin.structure.positions.store'), $data)->assertSessionHasNoErrors();
        $position = DB::table('positions')->where('job_family_id', $family)->first();
        $this->assertSame('ตำแหน่งทะเบียน', $position->name);
        $user = User::factory()->create(['position_id' => $position->id, 'position_title_id' => $id]);
        $data['name'] = 'ตำแหน่งทะเบียน';
        $this->deleteJson(route('admin.structure.positions.destroy'), $data)->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'position_id' => $position->id]);
        $user->delete();
        $this->delete(route('admin.structure.positions.destroy'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('positions', ['id' => $position->id]);
        $this->assertDatabaseHas('position_titles', ['id' => $id, 'name' => 'ตำแหน่งทะเบียน']);
    }

    public function test_migration_preserves_previously_visible_management_root_titles_only(): void
    {
        $wid = DB::table('worklines')->insertGetId(['name' => 'สายบริหาร']);
        $root = DB::table('position_titles')->insertGetId(['name' => 'เดิมระดับสายงาน', 'normalized_name' => 'เดิมระดับสายงาน', 'workline_type' => 'management', 'workline_id' => $wid]);
        $placed = DB::table('position_titles')->insertGetId(['name' => 'เดิมในหน่วย', 'normalized_name' => 'เดิมในหน่วย', 'workline_type' => 'management', 'workline_id' => $wid]);
        $family = DB::table('job_families')->insertGetId(['workline_id' => $wid, 'name' => 'หน่วยเดิม']);
        DB::table('positions')->insert(['job_family_id' => $family, 'position_title_id' => $placed, 'name' => 'เดิมในหน่วย']);
        $migration = require database_path('migrations/2026_11_05_000002_track_workline_position_placements.php');
        $migration->down();
        $migration->up();
        $this->assertDatabaseHas('position_titles', ['id' => $root, 'is_workline_position' => true]);
        $this->assertDatabaseHas('position_titles', ['id' => $placed, 'is_workline_position' => false]);
    }

    public function test_admin_can_bind_an_unresolved_title_but_cannot_move_used_titles_to_another_workline(): void
    {
        $admin = $this->admin();
        $first = DB::table('worklines')->insertGetId(['name' => 'สายที่หนึ่ง']);
        $second = DB::table('worklines')->insertGetId(['name' => 'สายที่สอง']);
        $title = DB::table('position_titles')->insertGetId(['name' => 'รอจัดสาย', 'normalized_name' => 'รอจัดสาย']);
        $payload = ['position_title_id' => $title, 'workline_id' => $first];
        $this->actingAs($admin)->put(route('admin.structure.position-titles.workline'), $payload)->assertSessionHasNoErrors();
        $family = DB::table('job_families')->insertGetId(['name' => 'หน่วยงาน', 'workline_id' => $first]);
        DB::table('positions')->insert(['name' => 'รอจัดสาย', 'job_family_id' => $family, 'position_title_id' => $title]);
        $this->putJson(route('admin.structure.position-titles.workline'), [...$payload, 'workline_id' => $second])
            ->assertUnprocessable()->assertJsonValidationErrors('workline_id');
        $this->assertDatabaseHas('position_titles', ['id' => $title, 'workline_id' => $first]);
        $employee = User::factory()->create();
        $this->actingAs($employee)->putJson(route('admin.structure.position-titles.workline'), $payload)->assertForbidden();
    }
}

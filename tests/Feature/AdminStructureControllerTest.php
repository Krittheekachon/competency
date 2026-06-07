<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminStructureControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_workline(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.structure.worklines.store'), ['name' => 'สายทดสอบ'])
            ->assertRedirect();

        $this->assertDatabaseHas('worklines', ['name' => 'สายทดสอบ']);

        $this->actingAs($admin)
            ->put(route('admin.structure.worklines.update'), [
                'old_name' => 'สายทดสอบ',
                'name' => 'สายทดสอบแก้ไข',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('worklines', ['name' => 'สายทดสอบแก้ไข']);
        $this->assertDatabaseMissing('worklines', ['name' => 'สายทดสอบ']);

        $this->actingAs($admin)
            ->delete(route('admin.structure.worklines.destroy'), ['name' => 'สายทดสอบแก้ไข'])
            ->assertRedirect();

        $this->assertDatabaseMissing('worklines', ['name' => 'สายทดสอบแก้ไข']);
    }

    public function test_admin_can_create_update_and_delete_job_family(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        DB::table('worklines')->insert(['id' => 1, 'name' => 'สายสนับสนุน', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($admin)
            ->post(route('admin.structure.job-families.store'), [
                'workline_name' => 'สายสนับสนุน',
                'name' => 'กลุ่มงานทดสอบ',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('job_families', ['workline_id' => 1, 'name' => 'กลุ่มงานทดสอบ']);

        $this->actingAs($admin)
            ->put(route('admin.structure.job-families.update'), [
                'old_name' => 'กลุ่มงานทดสอบ',
                'name' => 'กลุ่มงานแก้ไข',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('job_families', ['name' => 'กลุ่มงานแก้ไข']);
        $this->assertDatabaseMissing('job_families', ['name' => 'กลุ่มงานทดสอบ']);

        $this->actingAs($admin)
            ->delete(route('admin.structure.job-families.destroy'), ['name' => 'กลุ่มงานแก้ไข'])
            ->assertRedirect();

        $this->assertDatabaseMissing('job_families', ['name' => 'กลุ่มงานแก้ไข']);
    }

    public function test_admin_can_create_update_and_delete_position_under_job_family(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายสนับสนุน',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'กลุ่มงานทดสอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.structure.positions.store'), [
                'job_family_name' => 'กลุ่มงานทดสอบ',
                'name' => 'ตำแหน่งทดสอบ',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('positions', ['job_family_id' => $jobFamilyId, 'name' => 'ตำแหน่งทดสอบ']);

        $this->actingAs($admin)
            ->put(route('admin.structure.positions.update'), [
                'job_family_name' => 'กลุ่มงานทดสอบ',
                'old_name' => 'ตำแหน่งทดสอบ',
                'name' => 'ตำแหน่งแก้ไข',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('positions', ['job_family_id' => $jobFamilyId, 'name' => 'ตำแหน่งแก้ไข']);
        $this->assertDatabaseMissing('positions', ['job_family_id' => $jobFamilyId, 'name' => 'ตำแหน่งทดสอบ']);

        $this->actingAs($admin)
            ->delete(route('admin.structure.positions.destroy'), [
                'job_family_name' => 'กลุ่มงานทดสอบ',
                'name' => 'ตำแหน่งแก้ไข',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('positions', ['job_family_id' => $jobFamilyId, 'name' => 'ตำแหน่งแก้ไข']);
    }

    public function test_admin_can_create_update_and_delete_level(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายทดสอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.structure.levels.store'), [
                'workline_name' => 'สายทดสอบ',
                'name' => 'ระดับทดสอบ',
                'expected_level' => 3,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('levels', [
            'workline_id' => $worklineId,
            'name' => 'ระดับทดสอบ',
            'expected_level' => 3,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.structure.levels.update'), [
                'workline_name' => 'สายทดสอบ',
                'old_name' => 'ระดับทดสอบ',
                'name' => 'ระดับแก้ไข',
                'expected_level' => 4,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('levels', [
            'workline_id' => $worklineId,
            'name' => 'ระดับแก้ไข',
            'expected_level' => 4,
        ]);
        $this->assertDatabaseMissing('levels', [
            'workline_id' => $worklineId,
            'name' => 'ระดับทดสอบ',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.structure.levels.destroy'), [
                'workline_name' => 'สายทดสอบ',
                'name' => 'ระดับแก้ไข',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('levels', [
            'workline_id' => $worklineId,
            'name' => 'ระดับแก้ไข',
        ]);
    }

    public function test_admin_can_scope_levels_to_a_job_family(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายวิชาการ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $teacherFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'อาจารย์',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $researcherFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'นักวิจัย',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.structure.levels.store'), [
                'workline_name' => 'สายวิชาการ',
                'job_family_name' => 'อาจารย์',
                'name' => 'ผศ',
                'expected_level' => 3,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.structure.levels.store'), [
                'workline_name' => 'สายวิชาการ',
                'job_family_name' => 'นักวิจัย',
                'name' => 'นักวิจัยระดับ 1',
                'expected_level' => 2,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('levels', [
            'workline_id' => $worklineId,
            'job_family_id' => $teacherFamilyId,
            'name' => 'ผศ',
            'expected_level' => 3,
        ]);
        $this->assertDatabaseHas('levels', [
            'workline_id' => $worklineId,
            'job_family_id' => $researcherFamilyId,
            'name' => 'นักวิจัยระดับ 1',
            'expected_level' => 2,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.structure.levels.update'), [
                'workline_name' => 'สายวิชาการ',
                'job_family_name' => 'อาจารย์',
                'old_name' => 'ผศ',
                'name' => 'ผู้ช่วยศาสตราจารย์',
                'expected_level' => 3,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('levels', [
            'workline_id' => $worklineId,
            'job_family_id' => $teacherFamilyId,
            'name' => 'ผู้ช่วยศาสตราจารย์',
        ]);
        $this->assertDatabaseMissing('levels', [
            'workline_id' => $worklineId,
            'job_family_id' => $teacherFamilyId,
            'name' => 'ผศ',
        ]);
    }

    public function test_admin_can_create_update_and_delete_learning_method(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.structure.learning-methods.store'), [
                'key' => 'test-learning',
                'label' => 'ประเภททดสอบ',
                'description' => 'รายละเอียดทดสอบ',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('learning_method_types', [
            'key' => 'test-learning',
            'label' => 'ประเภททดสอบ',
            'description' => 'รายละเอียดทดสอบ',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.structure.learning-methods.update'), [
                'old_key' => 'test-learning',
                'key' => 'test-learning',
                'label' => 'ประเภทแก้ไข',
                'description' => 'รายละเอียดแก้ไข',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('learning_method_types', [
            'key' => 'test-learning',
            'label' => 'ประเภทแก้ไข',
            'description' => 'รายละเอียดแก้ไข',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.structure.learning-methods.destroy'), ['key' => 'test-learning'])
            ->assertRedirect();

        $this->assertDatabaseMissing('learning_method_types', ['key' => 'test-learning']);
    }
}

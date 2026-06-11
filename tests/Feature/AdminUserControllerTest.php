<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_user_and_keep_supervisor_names_with_ids(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $this->createStructure('สายสนับสนุน', 'ฝ่ายบริหาร', 'นักวิชาการศึกษา', 'ปฏิบัติการ');
        $supervisor = User::factory()->create([
            'title' => 'นาย',
            'name' => 'หัวหน้า ทดสอบ',
            'role_id' => 5,
            'role_key' => 'supervisor',
        ]);
        $evaluator = User::factory()->create([
            'title' => 'นาง',
            'name' => 'ผู้บังคับ บัญชา',
            'role_id' => 3,
            'role_key' => 'manager_dept',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'sso' => 'staff-001',
                't' => 'นาย',
                'fn' => 'บุคลากร',
                'ln' => 'ใหม่',
                'fe' => 'Staff',
                'le' => 'User',
                'g' => 'ชาย',
                'em' => 'staff001@example.com',
                'ph' => null,
                'w' => 'สายสนับสนุน',
                'd' => 'ฝ่ายบริหาร',
                'p' => 'นักวิชาการศึกษา',
                'l' => 'ปฏิบัติการ',
                'r' => 'employee',
                'sup' => 'นายหัวหน้า ทดสอบ',
                'evaluator2' => 'นางผู้บังคับ บัญชา',
                'act' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'sso' => 'staff-001',
            'name' => 'บุคลากร ใหม่',
            'role_id' => 3,
            'role_key' => 'employee',
            'supervisor' => 'นายหัวหน้า ทดสอบ',
            'evaluator2' => 'นางผู้บังคับ บัญชา',
            'supervisor_id_1' => $supervisor->id,
            'supervisor_id_2' => $evaluator->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_user(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $this->createStructure('สายวิชาการ', 'สาขาวิชา', 'อาจารย์', 'อาจารย์');
        $user = User::factory()->create([
            'sso' => 'staff-002',
            'role_id' => 4,
            'role_key' => 'employee',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'sso' => 'staff-002',
                't' => 'นางสาว',
                'fn' => 'แก้ไข',
                'ln' => 'ข้อมูล',
                'fe' => 'Updated',
                'le' => 'User',
                'g' => 'หญิง',
                'em' => 'updated@example.com',
                'ph' => null,
                'w' => 'สายวิชาการ',
                'd' => 'สาขาวิชา',
                'p' => 'อาจารย์',
                'l' => 'อาจารย์',
                'r' => 'supervisor',
                'sup' => null,
                'evaluator2' => null,
                'act' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'แก้ไข ข้อมูล',
            'email' => 'updated@example.com',
            'role_id' => 1,
            'role_key' => 'supervisor',
            'workline' => 'สายวิชาการ',
        ]);
    }

    public function test_admin_can_update_three_evaluator_levels(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $this->createStructure('สายสนับสนุน', 'งานทรัพยากรบุคคล', 'นักทรัพยากรบุคคล', 'ปฏิบัติการ');
        $user = User::factory()->create([
            'sso' => 'staff-three-evaluators',
            'role_id' => 3,
            'role_key' => 'employee',
        ]);
        $supervisor = User::factory()->create([
            'title' => 'นาย',
            'name' => 'หัวหน้า หนึ่ง',
            'role_id' => 1,
            'role_key' => 'supervisor',
        ]);
        $managerDept = User::factory()->create([
            'title' => 'นาง',
            'name' => 'ผู้บังคับ สอง',
            'role_id' => 2,
            'role_key' => 'manager_dept',
        ]);
        $dean = User::factory()->create([
            'title' => 'ผศ.',
            'name' => 'คณบดี สาม',
            'role_id' => 5,
            'role_key' => 'dean',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'sso' => 'staff-three-evaluators',
                't' => 'นาย',
                'fn' => 'สาม',
                'ln' => 'ระดับ',
                'fe' => 'Three',
                'le' => 'Levels',
                'g' => 'ชาย',
                'em' => 'three-evaluators@example.com',
                'ph' => null,
                'w' => 'สายสนับสนุน',
                'd' => 'งานทรัพยากรบุคคล',
                'p' => 'นักทรัพยากรบุคคล',
                'l' => 'ปฏิบัติการ',
                'r' => 'employee',
                'sup' => 'นายหัวหน้า หนึ่ง',
                'evaluator2' => 'นางผู้บังคับ สอง',
                'evaluator3' => 'ผศ.คณบดี สาม',
                'act' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'supervisor' => 'นายหัวหน้า หนึ่ง',
            'evaluator2' => 'นางผู้บังคับ สอง',
            'evaluator3' => 'ผศ.คณบดี สาม',
            'supervisor_id_1' => $supervisor->id,
            'supervisor_id_2' => $managerDept->id,
            'supervisor_id_3' => $dean->id,
        ]);
    }

    public function test_admin_can_update_user_role_to_manager_dept(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $this->createStructure('สายสนับสนุน', 'งานทรัพยากรบุคคล', 'นักทรัพยากรบุคคล', 'ปฏิบัติการ');
        $user = User::factory()->create([
            'sso' => 'staff-manager-dept',
            'role_id' => 3,
            'role_key' => 'employee',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'sso' => 'staff-manager-dept',
                't' => 'นาง',
                'fn' => 'ผู้บังคับ',
                'ln' => 'บัญชา',
                'fe' => 'Manager',
                'le' => 'Dept',
                'g' => 'หญิง',
                'em' => 'manager-dept@example.com',
                'ph' => null,
                'w' => 'สายสนับสนุน',
                'd' => 'งานทรัพยากรบุคคล',
                'p' => 'นักทรัพยากรบุคคล',
                'l' => 'ปฏิบัติการ',
                'r' => 'manager_dept',
                'sup' => null,
                'evaluator2' => null,
                'act' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role_id' => 2,
            'role_key' => 'manager_dept',
        ]);
    }

    public function test_admin_can_update_reporting_line_with_linked_user_ids(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $this->createStructure('สายสนับสนุน', 'ฝ่ายบริหาร', 'นักวิชาการศึกษา', 'ปฏิบัติการ');
        $user = User::factory()->create([
            'sso' => 'staff-003',
            'name' => 'บุคลากร เดิม',
            'email' => 'staff003@example.com',
            'role_id' => 4,
            'role_key' => 'employee',
        ]);
        $supervisor = User::factory()->create([
            'title' => 'นาย',
            'name' => 'หัวหน้า ใหม่',
            'role_id' => 5,
            'role_key' => 'supervisor',
        ]);
        $evaluator = User::factory()->create([
            'title' => 'นาง',
            'name' => 'ผู้บังคับ ใหม่',
            'role_id' => 3,
            'role_key' => 'manager_dept',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'sso' => 'staff-003',
                't' => 'นาย',
                'fn' => 'บุคลากร',
                'ln' => 'เดิม',
                'fe' => 'Staff',
                'le' => 'User',
                'g' => 'ชาย',
                'em' => 'staff003@example.com',
                'ph' => null,
                'w' => 'สายสนับสนุน',
                'd' => 'ฝ่ายบริหาร > งานคลังและพัสดุ',
                'p' => 'นักวิชาการศึกษา',
                'l' => 'ปฏิบัติการ',
                'r' => 'employee',
                'sup' => 'นายหัวหน้า ใหม่',
                'evaluator2' => 'นางผู้บังคับ ใหม่',
                'act' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'department' => 'ฝ่ายบริหาร > งานคลังและพัสดุ',
            'supervisor' => 'นายหัวหน้า ใหม่',
            'evaluator2' => 'นางผู้บังคับ ใหม่',
            'supervisor_id_1' => $supervisor->id,
            'supervisor_id_2' => $evaluator->id,
        ]);
    }

    public function test_admin_cannot_use_job_family_name_as_position_when_family_has_no_positions(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $this->createStructure('สายงานบริหาร', 'รองคณบดีฝ่ายบริหาร', null, 'บริหาร');

        $this->actingAs($admin)
            ->from('/dashboard')
            ->post(route('admin.users.store'), [
                'sso' => 'staff-no-position',
                't' => 'นาย',
                'fn' => 'ไม่มี',
                'ln' => 'ตำแหน่ง',
                'fe' => 'No',
                'le' => 'Position',
                'g' => 'ชาย',
                'em' => 'no-position@example.com',
                'ph' => null,
                'w' => 'สายงานบริหาร',
                'd' => 'รองคณบดีฝ่ายบริหาร',
                'p' => 'รองคณบดีฝ่ายบริหาร',
                'l' => 'บริหาร',
                'r' => 'employee',
                'sup' => null,
                'evaluator2' => null,
                'act' => true,
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHasErrors('p');

        $this->assertDatabaseMissing('users', [
            'sso' => 'staff-no-position',
        ]);
    }

    public function test_admin_can_toggle_user_status(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $user), ['act' => false])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_delete_user(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $user = User::factory()->create(['role_id' => 4, 'role_key' => 'employee']);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    private function createStructure(string $workline, string $jobFamily, ?string $position, string $level): void
    {
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => $workline,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => $jobFamily,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($position) {
            DB::table('positions')->insert([
                'job_family_id' => $jobFamilyId,
                'name' => $position,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('levels')->insert([
            'workline_id' => $worklineId,
            'job_family_id' => null,
            'name' => $level,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

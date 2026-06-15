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
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        $this->createStructure('สายสนับสนุน', 'ฝ่ายบริหาร', 'นักวิชาการศึกษา', 'ปฏิบัติการ');
        $supervisor = User::factory()->create([
            'title' => 'นาย',
            'name' => 'หัวหน้า ทดสอบ',
            'role_id' => $this->roleId('supervisor'),
        ]);
        $evaluator = User::factory()->create([
            'title' => 'นาง',
            'name' => 'ผู้บังคับ บัญชา',
            'role_id' => $this->roleId('dept_head'),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'sso' => 'staff-001',
                't' => 'นาย',
                'fn' => 'บุคลากร',
                'ln' => 'ใหม่',
                'fe' => 'Staff',
                'le' => 'User',
                'em' => 'staff001@example.com',
                'ph' => null,
                'w' => 'สายสนับสนุน',
                'd' => 'ฝ่ายบริหาร',
                'p' => 'นักวิชาการศึกษา',
                'l' => 'ปฏิบัติการ',
                'r' => 'employee',
                'supervisor_id_1' => $supervisor->id,
                'supervisor_id_2' => $evaluator->id,
                'act' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'sso' => 'staff-001',
            'name' => 'บุคลากร ใหม่',
            'role_id' => $this->roleId('employee'),
            'supervisor_id_1' => $supervisor->id,
            'supervisor_id_2' => $evaluator->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_user(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        $this->createStructure('สายวิชาการ', 'สาขาวิชา', 'อาจารย์', 'อาจารย์');
        $user = User::factory()->create([
            'sso' => 'staff-002',
            'role_id' => $this->roleId('employee'),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'sso' => 'staff-002',
                't' => 'นางสาว',
                'fn' => 'แก้ไข',
                'ln' => 'ข้อมูล',
                'fe' => 'Updated',
                'le' => 'User',
                'em' => 'updated@example.com',
                'ph' => null,
                'w' => 'สายวิชาการ',
                'd' => 'สาขาวิชา',
                'p' => 'อาจารย์',
                'l' => 'อาจารย์',
                'r' => 'supervisor',
                'supervisor_id_1' => null,
                'supervisor_id_2' => null,
                'act' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'แก้ไข ข้อมูล',
            'email' => 'updated@example.com',
            'role_id' => $this->roleId('supervisor'),
            'workline' => 'สายวิชาการ',
        ]);
    }

    public function test_admin_can_update_three_evaluator_levels(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        $this->createStructure('สายสนับสนุน', 'งานทรัพยากรบุคคล', 'นักทรัพยากรบุคคล', 'ปฏิบัติการ');
        $user = User::factory()->create([
            'sso' => 'staff-three-evaluators',
            'role_id' => $this->roleId('employee'),
        ]);
        $supervisor = User::factory()->create([
            'title' => 'นาย',
            'name' => 'หัวหน้า หนึ่ง',
            'role_id' => $this->roleId('supervisor'),
        ]);
        $managerDept = User::factory()->create([
            'title' => 'นาง',
            'name' => 'ผู้บังคับ สอง',
            'role_id' => $this->roleId('dept_head'),
        ]);
        $dean = User::factory()->create([
            'title' => 'ผศ.',
            'name' => 'คณบดี สาม',
            'role_id' => $this->roleId('dean'),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'sso' => 'staff-three-evaluators',
                't' => 'นาย',
                'fn' => 'สาม',
                'ln' => 'ระดับ',
                'fe' => 'Three',
                'le' => 'Levels',
                'em' => 'three-evaluators@example.com',
                'ph' => null,
                'w' => 'สายสนับสนุน',
                'd' => 'งานทรัพยากรบุคคล',
                'p' => 'นักทรัพยากรบุคคล',
                'l' => 'ปฏิบัติการ',
                'r' => 'employee',
                'supervisor_id_1' => $supervisor->id,
                'supervisor_id_2' => $managerDept->id,
                'supervisor_id_3' => $dean->id,
                'act' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'supervisor_id_1' => $supervisor->id,
            'supervisor_id_2' => $managerDept->id,
            'supervisor_id_3' => $dean->id,
        ]);
    }

    public function test_admin_normalizes_manager_dept_alias_to_role_table_key(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        $this->createStructure('สายสนับสนุน', 'งานทรัพยากรบุคคล', 'นักทรัพยากรบุคคล', 'ปฏิบัติการ');
        $user = User::factory()->create([
            'sso' => 'staff-manager-dept',
            'role_id' => $this->roleId('employee'),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'sso' => 'staff-manager-dept',
                't' => 'นาง',
                'fn' => 'ผู้บังคับ',
                'ln' => 'บัญชา',
                'fe' => 'Manager',
                'le' => 'Dept',
                'em' => 'manager-dept@example.com',
                'ph' => null,
                'w' => 'สายสนับสนุน',
                'd' => 'งานทรัพยากรบุคคล',
                'p' => 'นักทรัพยากรบุคคล',
                'l' => 'ปฏิบัติการ',
                'r' => 'manager_dept',
                'supervisor_id_1' => null,
                'supervisor_id_2' => null,
                'act' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role_id' => $this->roleId('dept_head'),
        ]);
    }

    public function test_admin_can_update_reporting_line_with_linked_user_ids(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        $this->createStructure('สายสนับสนุน', 'ฝ่ายบริหาร', 'นักวิชาการศึกษา', 'ปฏิบัติการ');
        $user = User::factory()->create([
            'sso' => 'staff-003',
            'name' => 'บุคลากร เดิม',
            'email' => 'staff003@example.com',
            'role_id' => $this->roleId('employee'),
        ]);
        $supervisor = User::factory()->create([
            'title' => 'นาย',
            'name' => 'หัวหน้า ใหม่',
            'role_id' => $this->roleId('supervisor'),
        ]);
        $evaluator = User::factory()->create([
            'title' => 'นาง',
            'name' => 'ผู้บังคับ ใหม่',
            'role_id' => $this->roleId('dept_head'),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'sso' => 'staff-003',
                't' => 'นาย',
                'fn' => 'บุคลากร',
                'ln' => 'เดิม',
                'fe' => 'Staff',
                'le' => 'User',
                'em' => 'staff003@example.com',
                'ph' => null,
                'w' => 'สายสนับสนุน',
                'd' => 'ฝ่ายบริหาร > งานคลังและพัสดุ',
                'p' => 'นักวิชาการศึกษา',
                'l' => 'ปฏิบัติการ',
                'r' => 'employee',
                'supervisor_id_1' => $supervisor->id,
                'supervisor_id_2' => $evaluator->id,
                'act' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'department' => 'ฝ่ายบริหาร > งานคลังและพัสดุ',
            'supervisor_id_1' => $supervisor->id,
            'supervisor_id_2' => $evaluator->id,
        ]);
    }

    public function test_admin_cannot_use_job_family_name_as_position_when_family_has_no_positions(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
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
                'em' => 'no-position@example.com',
                'ph' => null,
                'w' => 'สายงานบริหาร',
                'd' => 'รองคณบดีฝ่ายบริหาร',
                'p' => 'รองคณบดีฝ่ายบริหาร',
                'l' => 'บริหาร',
                'r' => 'employee',
                'supervisor_id_1' => null,
                'supervisor_id_2' => null,
                'act' => true,
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHasErrors('p');

        $this->assertDatabaseMissing('users', [
            'sso' => 'staff-no-position',
        ]);
    }

    public function test_admin_can_create_dean_without_master_position(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $this->createStructure('สายงานบริหาร', 'คณบดี', null, 'บริหาร');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'sso' => 'dean-no-position',
                't' => 'รศ.',
                'fn' => 'คณบดี',
                'ln' => 'ทดสอบ',
                'fe' => 'Dean',
                'le' => 'User',
                'g' => 'ชาย',
                'em' => 'dean-no-position@example.com',
                'ph' => null,
                'w' => 'สายงานบริหาร',
                'd' => 'คณบดี',
                'p' => '',
                'l' => 'บริหาร',
                'r' => 'dean',
                'sup' => null,
                'evaluator2' => null,
                'act' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'sso' => 'dean-no-position',
            'position' => 'คณบดี',
            'position_id' => null,
            'role_key' => 'dean',
        ]);
    }

    public function test_admin_can_toggle_user_status(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $user), ['act' => false])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $user), ['act' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_cannot_suspend_own_active_account(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->roleId('admin'),
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $admin), ['act' => false])
            ->assertSessionHasErrors('act');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_inertia_user_update_redirects_to_login_location(): void
    {
        $user = User::factory()->create(['role_id' => $this->roleId('employee')]);

        $this->withHeader('X-Inertia', 'true')
            ->put(route('admin.users.update', $user), [])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('login'));
    }

    public function test_admin_can_delete_user(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        $user = User::factory()->create(['role_id' => $this->roleId('employee')]);

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

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }
}

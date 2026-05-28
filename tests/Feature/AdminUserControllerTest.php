<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_user_and_keep_supervisor_names_with_ids(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
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
            'role_id' => 4,
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
            'role_id' => 5,
            'role_key' => 'supervisor',
            'workline' => 'สายวิชาการ',
        ]);
    }

    public function test_admin_can_update_reporting_line_with_linked_user_ids(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
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
}

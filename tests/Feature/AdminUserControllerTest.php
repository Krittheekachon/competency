<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_user_with_reviewer_steps(): void
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
                'reviewer_ids' => [$supervisor->id, $evaluator->id],
                'act' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'sso' => 'staff-001',
            'name' => 'บุคลากร ใหม่',
            'role_id' => $this->roleId('employee'),
            'is_active' => true,
        ]);
        $userId = (int) DB::table('users')->where('sso', 'staff-001')->value('id');
        $this->assertDatabaseHas('user_reviewer_steps', [
            'user_id' => $userId,
            'chain_type' => 'assessment',
            'step_order' => 1,
            'reviewer_id' => $supervisor->id,
        ]);
        $this->assertDatabaseHas('user_reviewer_steps', [
            'user_id' => $userId,
            'chain_type' => 'assessment',
            'step_order' => 2,
            'reviewer_id' => $evaluator->id,
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
                'reviewer_ids' => [],
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

    public function test_admin_can_update_three_assessment_reviewer_steps(): void
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
                'reviewer_ids' => [$supervisor->id, $managerDept->id, $dean->id],
                'act' => true,
            ])
            ->assertRedirect();

        foreach ([$supervisor, $managerDept, $dean] as $index => $reviewer) {
            $this->assertDatabaseHas('user_reviewer_steps', [
                'user_id' => $user->id,
                'chain_type' => 'assessment',
                'step_order' => $index + 1,
                'reviewer_id' => $reviewer->id,
            ]);
        }
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
                'reviewer_ids' => [],
                'act' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role_id' => $this->roleId('dept_head'),
        ]);
    }

    public function test_admin_can_update_assessment_reviewer_steps_with_linked_user_ids(): void
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
                'reviewer_ids' => [$supervisor->id, $evaluator->id],
                'act' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'department' => 'ฝ่ายบริหาร > งานคลังและพัสดุ',
        ]);
        $this->assertDatabaseHas('user_reviewer_steps', [
            'user_id' => $user->id,
            'chain_type' => 'assessment',
            'step_order' => 1,
            'reviewer_id' => $supervisor->id,
        ]);
        $this->assertDatabaseHas('user_reviewer_steps', [
            'user_id' => $user->id,
            'chain_type' => 'assessment',
            'step_order' => 2,
            'reviewer_id' => $evaluator->id,
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

    public function test_admin_can_apply_reviewer_chain_template_when_creating_user(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        $this->createStructure('สายทดลอง', 'งานทดสอบ', 'นักทดสอบระบบ', 'ปฏิบัติการ');
        $templateId = (int) DB::table('reviewer_chain_templates')
            ->where('is_default', true)
            ->value('id');
        $supervisor = User::factory()->create([
            'name' => 'หัวหน้าหน่วย Template',
            'role_id' => $this->roleId('supervisor'),
            'workline' => 'สายทดลอง',
            'department' => 'งานทดสอบ',
            'position' => 'หัวหน้าหน่วย',
            'is_active' => true,
        ]);
        $deptHead = User::factory()->create([
            'name' => 'หัวหน้างาน Template',
            'role_id' => $this->roleId('dept_head'),
            'workline' => 'สายทดลอง',
            'department' => 'งานอื่น',
            'position' => 'หัวหน้างาน',
            'is_active' => true,
        ]);
        $dean = User::factory()->create([
            'name' => 'คณบดี Template',
            'role_id' => $this->roleId('dean'),
            'workline' => 'สายบริหาร',
            'department' => 'ผู้บริหาร',
            'position' => 'คณบดี',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'sso' => 'staff-template',
                't' => 'นาย',
                'fn' => 'ใช้',
                'ln' => 'เทมเพลต',
                'fe' => 'Template',
                'le' => 'Staff',
                'em' => 'template-staff@example.com',
                'ph' => null,
                'w' => 'สายทดลอง',
                'd' => 'งานทดสอบ',
                'p' => 'นักทดสอบระบบ',
                'l' => 'ปฏิบัติการ',
                'r' => 'employee',
                'reviewer_template_id' => $templateId,
                'reviewer_ids' => [],
                'act' => true,
            ])
            ->assertRedirect();

        $userId = (int) DB::table('users')->where('sso', 'staff-template')->value('id');

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'reviewer_template_id' => $templateId,
        ]);
        $this->assertDatabaseHas('user_reviewer_steps', [
            'user_id' => $userId,
            'step_order' => 1,
            'reviewer_id' => $supervisor->id,
        ]);
        $this->assertDatabaseHas('user_reviewer_steps', [
            'user_id' => $userId,
            'step_order' => 2,
            'reviewer_id' => $deptHead->id,
        ]);
        $this->assertDatabaseHas('user_reviewer_steps', [
            'user_id' => $userId,
            'step_order' => 3,
            'reviewer_id' => $dean->id,
        ]);
    }

    public function test_admin_can_create_assessment_reviewer_chain_for_selected_users(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        $reviewerA = User::factory()->create([
            'name' => 'นาย A',
            'role_id' => $this->roleId('supervisor'),
            'is_active' => true,
        ]);
        $reviewerB = User::factory()->create([
            'name' => 'นาย B',
            'role_id' => $this->roleId('dept_head'),
            'is_active' => true,
        ]);
        $reviewerC = User::factory()->create([
            'name' => 'นาย C',
            'role_id' => $this->roleId('dean'),
            'is_active' => true,
        ]);
        $firstUser = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'is_active' => true,
        ]);
        $secondUser = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.reviewer-chain-templates.store'), [
                'name' => 'ลำดับประเมินทดสอบ',
                'description' => 'นาย A -> นาย B -> นาย C',
                'chain_type' => 'assessment',
                'reviewer_ids' => [$reviewerA->id, $reviewerB->id, $reviewerC->id],
                'assignment_user_ids' => [$firstUser->id, $secondUser->id],
            ])
            ->assertRedirect();

        $templateId = (int) DB::table('reviewer_chain_templates')
            ->where('name', 'ลำดับประเมินทดสอบ')
            ->value('id');

        $this->assertGreaterThan(0, $templateId);
        $this->assertDatabaseHas('reviewer_chain_templates', [
            'id' => $templateId,
            'name' => 'ลำดับประเมินทดสอบ',
            'chain_type' => 'assessment',
        ]);

        foreach ([$reviewerA, $reviewerB, $reviewerC] as $index => $reviewer) {
            $this->assertDatabaseHas('reviewer_chain_template_steps', [
                'template_id' => $templateId,
                'step_order' => $index + 1,
                'resolver_type' => 'fixed_user',
                'reviewer_id' => $reviewer->id,
            ]);
        }

        foreach ([$firstUser, $secondUser] as $user) {
            $this->assertDatabaseHas('reviewer_chain_template_assignments', [
                'template_id' => $templateId,
                'scope_type' => 'user',
                'user_id' => $user->id,
            ]);
            $this->assertDatabaseHas('users', [
                'id' => $user->id,
                'reviewer_template_id' => $templateId,
            ]);

            foreach ([$reviewerA, $reviewerB, $reviewerC] as $index => $reviewer) {
                $this->assertDatabaseHas('user_reviewer_steps', [
                    'user_id' => $user->id,
                    'chain_type' => 'assessment',
                    'step_order' => $index + 1,
                    'reviewer_id' => $reviewer->id,
                ]);
            }
        }
    }

    public function test_admin_can_create_assessment_reviewer_chain_without_assigned_users(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        $reviewerA = User::factory()->create([
            'name' => 'นาย A',
            'role_id' => $this->roleId('supervisor'),
            'is_active' => true,
        ]);
        $reviewerB = User::factory()->create([
            'name' => 'นาย B',
            'role_id' => $this->roleId('dept_head'),
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.reviewer-chain-templates.store'), [
                'name' => 'ลำดับประเมินที่ยังไม่ผูกผู้ใช้',
                'description' => 'เก็บไว้เลือกผู้ใช้ภายหลัง',
                'chain_type' => 'assessment',
                'reviewer_ids' => [$reviewerA->id, $reviewerB->id],
                'assignment_user_ids' => [],
            ])
            ->assertRedirect();

        $templateId = (int) DB::table('reviewer_chain_templates')
            ->where('name', 'ลำดับประเมินที่ยังไม่ผูกผู้ใช้')
            ->value('id');

        $this->assertGreaterThan(0, $templateId);
        $this->assertDatabaseHas('reviewer_chain_template_steps', [
            'template_id' => $templateId,
            'step_order' => 1,
            'resolver_type' => 'fixed_user',
            'reviewer_id' => $reviewerA->id,
        ]);
        $this->assertDatabaseHas('reviewer_chain_template_steps', [
            'template_id' => $templateId,
            'step_order' => 2,
            'resolver_type' => 'fixed_user',
            'reviewer_id' => $reviewerB->id,
        ]);
        $this->assertSame(0, DB::table('reviewer_chain_template_assignments')
            ->where('template_id', $templateId)
            ->count());
    }

    public function test_admin_can_create_idp_reviewer_chain_for_selected_users(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        $reviewerA = User::factory()->create([
            'name' => 'นาย A',
            'role_id' => $this->roleId('supervisor'),
            'is_active' => true,
        ]);
        $reviewerB = User::factory()->create([
            'name' => 'นาย B',
            'role_id' => $this->roleId('dept_head'),
            'is_active' => true,
        ]);
        $assignedUser = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.reviewer-chain-templates.store'), [
                'name' => 'ลำดับ IDP ทดสอบ',
                'description' => 'นาย A -> นาย B',
                'chain_type' => 'idp',
                'reviewer_ids' => [$reviewerA->id, $reviewerB->id],
                'assignment_user_ids' => [$assignedUser->id],
            ])
            ->assertRedirect();

        $templateId = (int) DB::table('reviewer_chain_templates')
            ->where('name', 'ลำดับ IDP ทดสอบ')
            ->value('id');

        $this->assertGreaterThan(0, $templateId);
        $this->assertDatabaseHas('reviewer_chain_templates', [
            'id' => $templateId,
            'chain_type' => 'idp',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $assignedUser->id,
            'idp_reviewer_template_id' => $templateId,
            'reviewer_template_id' => null,
        ]);

        foreach ([$reviewerA, $reviewerB] as $index => $reviewer) {
            $this->assertDatabaseHas('user_reviewer_steps', [
                'user_id' => $assignedUser->id,
                'chain_type' => 'idp',
                'step_order' => $index + 1,
                'reviewer_id' => $reviewer->id,
            ]);
        }
    }

    public function test_admin_can_update_assessment_reviewer_chain_template(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        $reviewerA = User::factory()->create([
            'name' => 'นาย A',
            'role_id' => $this->roleId('supervisor'),
            'is_active' => true,
        ]);
        $reviewerB = User::factory()->create([
            'name' => 'นาย B',
            'role_id' => $this->roleId('dept_head'),
            'is_active' => true,
        ]);
        $reviewerC = User::factory()->create([
            'name' => 'นาย C',
            'role_id' => $this->roleId('dean'),
            'is_active' => true,
        ]);
        $assignedUser = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'is_active' => true,
        ]);

        $templateId = DB::table('reviewer_chain_templates')->insertGetId([
            'name' => 'ลำดับเดิม',
            'description' => 'นาย A -> นาย B',
            'chain_type' => 'assessment',
            'is_default' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('reviewer_chain_template_steps')->insert([
            [
                'template_id' => $templateId,
                'step_order' => 1,
                'resolver_type' => 'fixed_user',
                'role_key' => null,
                'reviewer_id' => $reviewerA->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'template_id' => $templateId,
                'step_order' => 2,
                'resolver_type' => 'fixed_user',
                'role_key' => null,
                'reviewer_id' => $reviewerB->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('reviewer_chain_template_assignments')->insert([
            'template_id' => $templateId,
            'scope_type' => 'user',
            'scope_value' => null,
            'user_id' => $assignedUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_reviewer_steps')->insert([
            [
                'user_id' => $assignedUser->id,
                'chain_type' => 'assessment',
                'step_order' => 1,
                'reviewer_id' => $reviewerA->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $assignedUser->id,
                'chain_type' => 'assessment',
                'step_order' => 2,
                'reviewer_id' => $reviewerB->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $assignedUser->forceFill([
            'reviewer_template_id' => $templateId,
        ])->save();

        $this->actingAs($admin)
            ->patch(route('admin.reviewer-chain-templates.update', $templateId), [
                'name' => 'ลำดับแก้ไขแล้ว',
                'description' => 'นาย C -> นาย A',
                'reviewer_ids' => [$reviewerC->id, $reviewerA->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reviewer_chain_templates', [
            'id' => $templateId,
            'name' => 'ลำดับแก้ไขแล้ว',
            'description' => 'นาย C -> นาย A',
        ]);
        $this->assertDatabaseHas('reviewer_chain_template_steps', [
            'template_id' => $templateId,
            'step_order' => 1,
            'reviewer_id' => $reviewerC->id,
        ]);
        $this->assertDatabaseHas('reviewer_chain_template_steps', [
            'template_id' => $templateId,
            'step_order' => 2,
            'reviewer_id' => $reviewerA->id,
        ]);
        $this->assertDatabaseMissing('reviewer_chain_template_steps', [
            'template_id' => $templateId,
            'step_order' => 2,
            'reviewer_id' => $reviewerB->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $assignedUser->id,
            'reviewer_template_id' => $templateId,
        ]);
        $this->assertDatabaseHas('user_reviewer_steps', [
            'user_id' => $assignedUser->id,
            'chain_type' => 'assessment',
            'step_order' => 1,
            'reviewer_id' => $reviewerC->id,
        ]);
        $this->assertDatabaseHas('user_reviewer_steps', [
            'user_id' => $assignedUser->id,
            'chain_type' => 'assessment',
            'step_order' => 2,
            'reviewer_id' => $reviewerA->id,
        ]);
        $this->assertDatabaseMissing('user_reviewer_steps', [
            'user_id' => $assignedUser->id,
            'chain_type' => 'assessment',
            'step_order' => 2,
            'reviewer_id' => $reviewerB->id,
        ]);
    }

    public function test_admin_can_add_users_to_existing_assessment_reviewer_chain(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        $reviewerA = User::factory()->create([
            'name' => 'นาย A',
            'role_id' => $this->roleId('supervisor'),
            'is_active' => true,
        ]);
        $reviewerB = User::factory()->create([
            'name' => 'นาย B',
            'role_id' => $this->roleId('dept_head'),
            'is_active' => true,
        ]);
        $assignedUser = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'is_active' => true,
        ]);

        $templateId = DB::table('reviewer_chain_templates')->insertGetId([
            'name' => 'ลำดับเพิ่มสมาชิก',
            'description' => 'นาย A -> นาย B',
            'chain_type' => 'assessment',
            'is_default' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('reviewer_chain_template_steps')->insert([
            [
                'template_id' => $templateId,
                'step_order' => 1,
                'resolver_type' => 'fixed_user',
                'role_key' => null,
                'reviewer_id' => $reviewerA->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'template_id' => $templateId,
                'step_order' => 2,
                'resolver_type' => 'fixed_user',
                'role_key' => null,
                'reviewer_id' => $reviewerB->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.reviewer-chain-templates.users.store', $templateId), [
                'user_ids' => [$assignedUser->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reviewer_chain_template_assignments', [
            'template_id' => $templateId,
            'scope_type' => 'user',
            'user_id' => $assignedUser->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $assignedUser->id,
            'reviewer_template_id' => $templateId,
        ]);
        $this->assertDatabaseHas('user_reviewer_steps', [
            'user_id' => $assignedUser->id,
            'chain_type' => 'assessment',
            'step_order' => 1,
            'reviewer_id' => $reviewerA->id,
        ]);
        $this->assertDatabaseHas('user_reviewer_steps', [
            'user_id' => $assignedUser->id,
            'chain_type' => 'assessment',
            'step_order' => 2,
            'reviewer_id' => $reviewerB->id,
        ]);
    }

    public function test_admin_can_remove_user_from_existing_assessment_reviewer_chain(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        $reviewerA = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
            'is_active' => true,
        ]);
        $assignedUser = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'is_active' => true,
        ]);

        $templateId = DB::table('reviewer_chain_templates')->insertGetId([
            'name' => 'ลำดับลบสมาชิก',
            'description' => null,
            'chain_type' => 'assessment',
            'is_default' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('reviewer_chain_template_assignments')->insert([
            'template_id' => $templateId,
            'scope_type' => 'user',
            'scope_value' => null,
            'user_id' => $assignedUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $assignedUser->id,
            'chain_type' => 'assessment',
            'step_order' => 1,
            'reviewer_id' => $reviewerA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $assignedUser->forceFill([
            'reviewer_template_id' => $templateId,
        ])->save();

        $this->actingAs($admin)
            ->delete(route('admin.reviewer-chain-templates.users.destroy', [$templateId, $assignedUser->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('reviewer_chain_template_assignments', [
            'template_id' => $templateId,
            'scope_type' => 'user',
            'user_id' => $assignedUser->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $assignedUser->id,
            'reviewer_template_id' => null,
        ]);
        $this->assertDatabaseMissing('user_reviewer_steps', [
            'user_id' => $assignedUser->id,
            'chain_type' => 'assessment',
        ]);
    }

    public function test_admin_can_delete_assessment_reviewer_chain(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        $reviewerA = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
            'is_active' => true,
        ]);
        $assignedUser = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'is_active' => true,
        ]);

        $templateId = DB::table('reviewer_chain_templates')->insertGetId([
            'name' => 'ลำดับที่จะลบ',
            'description' => null,
            'chain_type' => 'assessment',
            'is_default' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('reviewer_chain_template_steps')->insert([
            'template_id' => $templateId,
            'step_order' => 1,
            'resolver_type' => 'fixed_user',
            'role_key' => null,
            'reviewer_id' => $reviewerA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('reviewer_chain_template_assignments')->insert([
            'template_id' => $templateId,
            'scope_type' => 'user',
            'scope_value' => null,
            'user_id' => $assignedUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $assignedUser->id,
            'chain_type' => 'assessment',
            'step_order' => 1,
            'reviewer_id' => $reviewerA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $assignedUser->forceFill([
            'reviewer_template_id' => $templateId,
        ])->save();

        $this->actingAs($admin)
            ->delete(route('admin.reviewer-chain-templates.destroy', $templateId))
            ->assertRedirect();

        $this->assertDatabaseMissing('reviewer_chain_templates', [
            'id' => $templateId,
        ]);
        $this->assertDatabaseMissing('reviewer_chain_template_steps', [
            'template_id' => $templateId,
        ]);
        $this->assertDatabaseMissing('reviewer_chain_template_assignments', [
            'template_id' => $templateId,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $assignedUser->id,
            'reviewer_template_id' => null,
        ]);
        $this->assertDatabaseMissing('user_reviewer_steps', [
            'user_id' => $assignedUser->id,
            'chain_type' => 'assessment',
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

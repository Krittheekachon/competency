<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDashboardUserStructureSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_users_show_current_job_family_name_for_existing_assignments(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role_id' => $this->roleId('admin'),
        ]);

        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายสนับสนุน',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'กลุ่มงานใหม่',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $positionId = DB::table('positions')->insertGetId([
            'job_family_id' => $jobFamilyId,
            'name' => 'นักทรัพยากรบุคคล',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::factory()->create([
            'name' => 'ZZ Existing User',
            'role_id' => $this->roleId('employee'),
            'workline' => 'สายสนับสนุน',
            'department' => 'กลุ่มงานเก่า > หน่วยเดิม',
            'position' => 'นักทรัพยากรบุคคล',
            'position_id' => $positionId,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users.1.n', 'ZZ Existing User')
                ->where('users.1.d', 'กลุ่มงานใหม่ > หน่วยเดิม')
            );
    }

    public function test_dashboard_marks_users_with_deleted_structure_assignments(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role_id' => $this->roleId('admin'),
        ]);

        DB::table('worklines')->insert([
            'name' => 'สายสนับสนุน',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::factory()->create([
            'name' => 'ZZ Orphaned User',
            'role_id' => $this->roleId('employee'),
            'workline' => 'สายสนับสนุน',
            'department' => 'กลุ่มงานที่ถูกลบ',
            'position' => 'ตำแหน่งที่ถูกลบ',
            'level' => 'ระดับที่ถูกลบ',
            'position_id' => null,
            'level_id' => null,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users.1.n', 'ZZ Orphaned User')
                ->where('users.1.structureStatus', 'invalid')
                ->where('users.1.structureIssues.0', 'กลุ่มงานนี้ไม่มีในโครงสร้างปัจจุบัน')
                ->where('users.1.structureIssues.1', 'ตำแหน่งนี้ไม่มีในกลุ่มงานปัจจุบัน')
                ->where('users.1.structureIssues.2', 'ระดับตำแหน่งนี้ไม่มีในโครงสร้างปัจจุบัน')
            );
    }

    public function test_dashboard_marks_users_without_any_evaluator_assignment(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role_id' => $this->roleId('admin'),
        ]);

        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายสนับสนุน',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'HR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('positions')->insert([
            'job_family_id' => $jobFamilyId,
            'name' => 'นักทรัพยากรบุคคล',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('levels')->insert([
            'workline_id' => $worklineId,
            'job_family_id' => null,
            'name' => 'ปฏิบัติการ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::factory()->create([
            'name' => 'ZZ No Evaluator User',
            'role_id' => $this->roleId('employee'),
            'workline' => 'สายสนับสนุน',
            'department' => 'HR',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ปฏิบัติการ',
            'supervisor_id_1' => null,
            'supervisor_id_2' => null,
            'supervisor_id_3' => null,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users.1.n', 'ZZ No Evaluator User')
                ->where('users.1.structureStatus', 'invalid')
                ->where('users.1.structureIssues.0', 'ยังไม่ได้กำหนดผู้ประเมินหรือหัวหน้างาน')
            );
    }

    public function test_dashboard_accepts_supervisor_with_only_third_evaluator(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role_id' => $this->roleId('admin'),
        ]);
        $this->createValidStructure();
        $dean = User::factory()->create([
            'name' => 'MM Dean Evaluator',
            'role_id' => $this->roleId('dean'),
        ]);

        User::factory()->create([
            'name' => 'ZZ Supervisor Missing Evaluator',
            'role_id' => $this->roleId('supervisor'),
            'workline' => 'สายสนับสนุน',
            'department' => 'HR',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ปฏิบัติการ',
            'supervisor_id_1' => null,
            'supervisor_id_2' => null,
            'supervisor_id_3' => $dean->id,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users.2.structureStatus', 'ok')
                ->where('users.2.structureIssues', [])
            );
    }

    public function test_dashboard_accepts_manager_dept_with_only_third_evaluator(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role_id' => $this->roleId('admin'),
        ]);
        $this->createValidStructure();
        $dean = User::factory()->create([
            'name' => 'MM Dean Evaluator',
            'role_id' => $this->roleId('dean'),
        ]);

        User::factory()->create([
            'name' => 'ZZ Manager Dept Missing Evaluator',
            'role_id' => $this->roleId('dept_head'),
            'workline' => 'สายสนับสนุน',
            'department' => 'HR',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ปฏิบัติการ',
            'supervisor_id_1' => null,
            'supervisor_id_2' => null,
            'supervisor_id_3' => $dean->id,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users.2.structureStatus', 'ok')
                ->where('users.2.structureIssues', [])
            );
    }

    public function test_dashboard_marks_employee_when_assigned_supervisor_is_no_longer_supervisor(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role_id' => $this->roleId('admin'),
        ]);
        $this->createValidStructure();

        $formerSupervisor = User::factory()->create([
            'name' => 'MM Former Supervisor',
            'role_id' => $this->roleId('employee'),
            'workline' => 'สายสนับสนุน',
            'department' => 'HR',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ปฏิบัติการ',
        ]);

        User::factory()->create([
            'name' => 'ZZ Staff With Former Supervisor',
            'role_id' => $this->roleId('employee'),
            'workline' => 'สายสนับสนุน',
            'department' => 'HR',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ปฏิบัติการ',
            'supervisor_id_1' => $formerSupervisor->id,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users.2.n', 'ZZ Staff With Former Supervisor')
                ->where('users.2.structureStatus', 'invalid')
                ->where('users.2.structureIssues.0', 'ผู้ประเมินลำดับที่ 1 ไม่ใช่หัวหน้างานแล้ว')
            );
    }

    public function test_dashboard_marks_employee_when_assigned_supervisor_moves_to_department_head(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role_id' => $this->roleId('admin'),
        ]);
        $this->createValidStructure();

        $movedSupervisor = User::factory()->create([
            'name' => 'MM Moved Supervisor',
            'role_id' => $this->roleId('dept_head'),
            'workline' => 'สายสนับสนุน',
            'department' => 'HR',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ปฏิบัติการ',
        ]);

        User::factory()->create([
            'name' => 'ZZ Staff With Moved Supervisor',
            'role_id' => $this->roleId('employee'),
            'workline' => 'สายสนับสนุน',
            'department' => 'HR',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ปฏิบัติการ',
            'supervisor_id_1' => $movedSupervisor->id,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users.2.n', 'ZZ Staff With Moved Supervisor')
                ->where('users.2.structureStatus', 'invalid')
                ->where('users.2.structureIssues.0', 'ผู้ประเมินลำดับที่ 1 ไม่ใช่หัวหน้างานแล้ว')
            );
    }

    private function createValidStructure(): void
    {
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายสนับสนุน',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'HR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('positions')->insert([
            'job_family_id' => $jobFamilyId,
            'name' => 'นักทรัพยากรบุคคล',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('levels')->insert([
            'workline_id' => $worklineId,
            'job_family_id' => null,
            'name' => 'ปฏิบัติการ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }
}

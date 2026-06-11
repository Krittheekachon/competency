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
            'role_id' => 0,
            'role_key' => 'admin',
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
            'role_id' => 3,
            'role_key' => 'employee',
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
            'role_id' => 0,
            'role_key' => 'admin',
        ]);

        DB::table('worklines')->insert([
            'name' => 'สายสนับสนุน',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::factory()->create([
            'name' => 'ZZ Orphaned User',
            'role_id' => 3,
            'role_key' => 'employee',
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
            'role_id' => 0,
            'role_key' => 'admin',
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
            'role_id' => 3,
            'role_key' => 'employee',
            'workline' => 'สายสนับสนุน',
            'department' => 'HR',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ปฏิบัติการ',
            'supervisor' => null,
            'evaluator2' => null,
            'evaluator3' => null,
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

    public function test_dashboard_marks_supervisor_without_second_evaluator(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role_id' => 0,
            'role_key' => 'admin',
        ]);
        $this->createValidStructure();

        User::factory()->create([
            'name' => 'ZZ Supervisor Missing Evaluator',
            'role_id' => 1,
            'role_key' => 'supervisor',
            'workline' => 'สายสนับสนุน',
            'department' => 'HR',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ปฏิบัติการ',
            'supervisor' => null,
            'evaluator2' => null,
            'evaluator3' => 'คณบดี ทดสอบ',
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users.1.structureStatus', 'invalid')
                ->where('users.1.structureIssues.0', 'หัวหน้างานยังไม่ได้กำหนดผู้ประเมินลำดับที่ 2')
            );
    }

    public function test_dashboard_marks_manager_dept_without_third_evaluator(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role_id' => 0,
            'role_key' => 'admin',
        ]);
        $this->createValidStructure();

        User::factory()->create([
            'name' => 'ZZ Manager Dept Missing Evaluator',
            'role_id' => 2,
            'role_key' => 'manager_dept',
            'workline' => 'สายสนับสนุน',
            'department' => 'HR',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ปฏิบัติการ',
            'supervisor' => null,
            'evaluator2' => null,
            'evaluator3' => null,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users.1.structureStatus', 'invalid')
                ->where('users.1.structureIssues.0', 'ผู้บังคับบัญชายังไม่ได้กำหนดผู้ประเมินลำดับที่ 3')
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
}

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
                ->where('users', fn ($users): bool => collect($users)
                    ->firstWhere('n', 'ZZ Existing User')['d'] === 'กลุ่มงานใหม่ > หน่วยเดิม')
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
                ->where('users', function ($users): bool {
                    $user = collect($users)->firstWhere('n', 'ZZ Orphaned User');

                    return $user
                        && $user['structureStatus'] === 'invalid'
                        && ($user['structureIssues'][0] ?? null) === 'กลุ่มงานนี้ไม่มีในโครงสร้างปัจจุบัน'
                        && ($user['structureIssues'][1] ?? null) === 'ตำแหน่งนี้ไม่มีในกลุ่มงานปัจจุบัน'
                        && ($user['structureIssues'][2] ?? null) === 'ระดับตำแหน่งนี้ไม่มีในโครงสร้างปัจจุบัน';
                })
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
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users', function ($users): bool {
                    $user = collect($users)->firstWhere('n', 'ZZ No Evaluator User');

                    return $user
                        && $user['structureStatus'] === 'invalid'
                        && ($user['structureIssues'][0] ?? null) === 'ยังไม่ได้กำหนดผู้ประเมินหรือหัวหน้าหน่วย';
                })
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

        $user = User::factory()->create([
            'name' => 'ZZ Supervisor Missing Evaluator',
            'role_id' => $this->roleId('supervisor'),
            'workline' => 'สายสนับสนุน',
            'department' => 'HR',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ปฏิบัติการ',
        ]);
        $this->assignReviewer($user, $dean, 3);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users', function ($users): bool {
                    $user = collect($users)->firstWhere('n', 'ZZ Supervisor Missing Evaluator');

                    return $user
                        && $user['structureStatus'] === 'ok'
                        && $user['structureIssues'] === [];
                })
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

        $user = User::factory()->create([
            'name' => 'ZZ Manager Dept Missing Evaluator',
            'role_id' => $this->roleId('dept_head'),
            'workline' => 'สายสนับสนุน',
            'department' => 'HR',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ปฏิบัติการ',
        ]);
        $this->assignReviewer($user, $dean, 3);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users', function ($users): bool {
                    $user = collect($users)->firstWhere('n', 'ZZ Manager Dept Missing Evaluator');

                    return $user
                        && $user['structureStatus'] === 'ok'
                        && $user['structureIssues'] === [];
                })
            );
    }

    public function test_dashboard_accepts_any_active_user_as_configured_reviewer(): void
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

        $user = User::factory()->create([
            'name' => 'ZZ Staff With Former Supervisor',
            'role_id' => $this->roleId('employee'),
            'workline' => 'สายสนับสนุน',
            'department' => 'HR',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ปฏิบัติการ',
        ]);
        $this->assignReviewer($user, $formerSupervisor);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users', function ($users): bool {
                    $user = collect($users)->firstWhere('n', 'ZZ Staff With Former Supervisor');

                    return $user
                        && $user['structureStatus'] === 'ok'
                        && $user['structureIssues'] === [];
                })
            );
    }

    public function test_dashboard_accepts_reviewer_role_without_fixed_step_mapping(): void
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

        $user = User::factory()->create([
            'name' => 'ZZ Staff With Moved Supervisor',
            'role_id' => $this->roleId('employee'),
            'workline' => 'สายสนับสนุน',
            'department' => 'HR',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ปฏิบัติการ',
        ]);
        $this->assignReviewer($user, $movedSupervisor);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users', function ($users): bool {
                    $user = collect($users)->firstWhere('n', 'ZZ Staff With Moved Supervisor');

                    return $user
                        && $user['structureStatus'] === 'ok'
                        && $user['structureIssues'] === [];
                })
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

    private function assignReviewer(User $user, User $reviewer, int $step = 1): void
    {
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $user->id,
            'reviewer_id' => $reviewer->id,
            'step_order' => $step,
            'chain_type' => 'assessment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }
}

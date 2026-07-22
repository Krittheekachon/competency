<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HrDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_users_land_on_hr_dashboard_with_database_summary(): void
    {
        $hrUser = User::factory()->create([
            'email' => 'hr@example.com',
            'role_id' => $this->roleId('hr'),
        ]);

        User::factory()->create([
            'email' => 'staff@example.com',
            'role_id' => $this->roleId('employee'),
        ]);

        $this->actingAs($hrUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('HR/Dashboard')
                ->where('hrSummary.totalUsers', 2)
                ->where('hrSummary.hrUsers', 1)
                ->where('hrSummary.employeeUsers', 1)
                ->where('hrSummary.source', 'database')
                ->has('users', 2)
                ->missing('roleSwitcher')
            );
    }

    public function test_hr_position_setup_uses_positions_not_levels_for_position_choices(): void
    {
        $hrUser = User::factory()->create([
            'email' => 'hr-position@example.com',
            'role_id' => $this->roleId('hr'),
        ]);

        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายทดสอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'กลุ่มงานทดสอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('positions')->insert([
            'job_family_id' => $jobFamilyId,
            'name' => 'ตำแหน่งจากตาราง position',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('levels')->insert([
            'workline_id' => $worklineId,
            'name' => 'ระดับที่ไม่ใช่ตำแหน่ง',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($hrUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('HR/Dashboard')
                ->where('jobFamiliesByWorkline.สายทดสอบ.กลุ่มงานทดสอบ.0', 'ตำแหน่งจากตาราง position')
                ->missing('levelsByWorkline')
            );
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')
            ->where('key', $key)
            ->orWhere('role_key', $key)
            ->value('id');
    }
}

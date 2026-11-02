<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DivisionHeadRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_exposes_division_head_in_role_options(): void
    {
        $adminRoleId = (int) DB::table('roles')->where('key', 'admin')->value('id');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('roles', fn ($roles) => collect($roles)->contains(
                    fn (array $role): bool => $role['key'] === 'division_head'
                        && $role['label'] === 'หัวหน้าฝ่าย'
                ) && collect($roles)->contains(
                    fn (array $role): bool => $role['key'] === 'academic_department_head'
                        && $role['label'] === 'หัวหน้าภาควิชา'
                ) && collect($roles)->contains(
                    fn (array $role): bool => $role['key'] === 'deputy_dean'
                        && $role['label'] === 'รองคณบดี'
                ) && collect($roles)->contains(
                    fn (array $role): bool => $role['key'] === 'assistant_dean'
                        && $role['label'] === 'ผู้ช่วยคณบดี'
                ))
            );
    }

    public function test_division_head_role_exists_and_uses_the_supervisor_dashboard(): void
    {
        $role = DB::table('roles')->where('key', 'division_head')->first();

        $this->assertNotNull($role);
        $this->assertSame('หัวหน้าฝ่าย', $role->name_th);

        $divisionHead = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->actingAs($divisionHead)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Super/Dashboard')
                ->where('roleKey', 'division_head')
                ->where('currentUser.r', 'division_head')
                ->where('activeCycleName', 'รอบประเมินปัจจุบัน')
            );
    }

    public function test_academic_department_head_role_exists_and_uses_the_supervisor_dashboard(): void
    {
        $role = DB::table('roles')->where('key', 'academic_department_head')->first();

        $this->assertNotNull($role);
        $this->assertSame('หัวหน้าภาควิชา', $role->name_th);

        $departmentHead = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $this->actingAs($departmentHead)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Super/Dashboard')
                ->where('roleKey', 'academic_department_head')
                ->where('currentUser.r', 'academic_department_head')
                ->where('activeCycleName', 'รอบประเมินปัจจุบัน')
            );
    }

    public function test_deputy_and_assistant_dean_roles_use_the_supervisor_dashboard(): void
    {
        foreach ([
            'deputy_dean' => 'รองคณบดี',
            'assistant_dean' => 'ผู้ช่วยคณบดี',
        ] as $roleKey => $roleLabel) {
            $role = DB::table('roles')->where('key', $roleKey)->first();

            $this->assertNotNull($role);
            $this->assertSame($roleLabel, $role->name_th);

            $user = User::factory()->create(['role_id' => $role->id]);

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Super/Dashboard')
                    ->where('roleKey', $roleKey)
                    ->where('currentUser.r', $roleKey));
        }
    }
}

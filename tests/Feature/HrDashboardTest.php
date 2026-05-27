<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HrDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_users_land_on_hr_dashboard_with_database_summary(): void
    {
        $hrUser = User::factory()->create([
            'email' => 'hr@example.com',
            'role_id' => 1,
        ]);

        User::factory()->create([
            'email' => 'staff@example.com',
            'role_id' => 4,
        ]);

        $this->actingAs($hrUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('HR/Dashboard')
                ->where('hrSummary.totalUsers', 2)
                ->where('hrSummary.hrUsers', 1)
                ->where('hrSummary.staffUsers', 1)
                ->where('hrSummary.source', 'database')
                ->missing('roleSwitcher')
            );
    }
}

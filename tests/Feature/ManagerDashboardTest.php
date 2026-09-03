<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManagerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_role_sees_manager_dashboard_with_database_summary(): void
    {
        $manager = User::factory()->create([
            'role_id' => (int) DB::table('roles')->where('key', 'dean')->value('id'),
        ]);

        User::factory()->create([
            'role_id' => (int) DB::table('roles')->where('key', 'employee')->value('id'),
        ]);

        $response = $this->actingAs($manager)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Executive/Dashboard')
            ->where('managerSummary.totalUsers', 2)
            ->where('managerSummary.evaluatedUsers', 0)
            ->where('managerSummary.passedUsers', 0)
            ->where('managerSummary.failedUsers', 0)
            ->where('managerSummary.trainingNeeds', 0)
            ->where('managerSummary.pendingAssessmentApprovals', 0)
            ->where('managerSummary.pendingIdpApprovals', 0)
            ->where('managerSummary.source', 'database')
            ->where('activeCycleName', 'รอบประเมินปัจจุบัน')
            ->where('departmentRows', [])
            ->where('problemCompetencyRows', [])
            ->where('idpProgressRows', [])
            ->where('idpNoProgressRows', [])
            ->where('trainingNeedRows', [])
            ->where('assessmentApprovals', [])
            ->where('idpApprovals', [])
        );
    }
}

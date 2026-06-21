<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IdpActivityUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_update_activity_after_final_approval(): void
    {
        [$employee, $activityId] = $this->activityForStatus('approved');

        $this->actingAs($employee)
            ->post(route('employee.idp-activities.update-progress'), [
                'activityId' => $activityId,
                'progressNote' => 'ดำเนินการแล้วครึ่งหนึ่ง',
                'percentComplete' => 50,
                'evidenceUrl' => 'https://example.test/evidence',
                'evidenceDescription' => 'หลักฐานการดำเนินงาน',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('idp_activity_updates', [
            'activity_id' => $activityId,
            'updated_by' => $employee->id,
            'percent_complete' => 50,
        ]);
    }

    public function test_employee_cannot_update_activity_before_final_approval(): void
    {
        [$employee, $activityId] = $this->activityForStatus('review_step_2');

        $this->actingAs($employee)
            ->post(route('employee.idp-activities.update-progress'), [
                'activityId' => $activityId,
                'progressNote' => 'ยังไม่ควรบันทึกได้',
                'percentComplete' => 10,
            ])
            ->assertSessionHasErrors('activityId');
    }

    private function activityForStatus(string $status): array
    {
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $idpId = DB::table('idps')->insertGetId([
            'user_id' => $employee->id,
            'year' => 2569,
            'status' => $status === 'approved' ? 'approved' : 'partially_submitted',
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $itemId = DB::table('idp_items')->insertGetId([
            'idp_id' => $idpId,
            'goal' => 'พัฒนาสมรรถนะ',
            'success_criteria' => 'ผ่านตามเกณฑ์',
            'status' => $status,
            'submission_version' => 1,
            'current_review_step' => str_starts_with($status, 'review_step_')
                ? (int) str_replace('review_step_', '', $status)
                : null,
            'submitted_at' => now(),
            'approved_at' => $status === 'approved' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $activityId = DB::table('idp_activities')->insertGetId([
            'idp_item_id' => $itemId,
            'activity_name' => 'Project Assignment',
            'weight_percent' => 100,
            'start_date' => '2026-06-21',
            'end_date' => '2026-07-21',
            'status' => 'planned',
            'result' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$employee, $activityId];
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }
}

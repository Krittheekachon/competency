<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IdpItemApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_supervisor_can_approve_one_submitted_competency_plan(): void
    {
        [$supervisor, $itemId] = $this->submittedItem();

        $this->actingAs($supervisor)
            ->post(route('idp-items.approve'), ['idpItemId' => $itemId])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('idp_items', [
            'id' => $itemId,
            'status' => 'approved',
            'approved_by' => $supervisor->id,
        ]);
    }

    public function test_unassigned_user_cannot_approve_competency_plan(): void
    {
        [, $itemId] = $this->submittedItem();
        $otherUser = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);

        $this->actingAs($otherUser)
            ->post(route('idp-items.approve'), ['idpItemId' => $itemId])
            ->assertSessionHasErrors('idpItemId');

        $this->assertDatabaseHas('idp_items', [
            'id' => $itemId,
            'status' => 'submitted',
        ]);
    }

    public function test_assigned_supervisor_must_include_comment_when_rejecting(): void
    {
        [$supervisor, $itemId] = $this->submittedItem();

        $this->actingAs($supervisor)
            ->post(route('idp-items.reject'), [
                'idpItemId' => $itemId,
                'comment' => '',
            ])
            ->assertSessionHasErrors('comment');
    }

    private function submittedItem(): array
    {
        $supervisor = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'supervisor_id_1' => $supervisor->id,
        ]);
        $idpId = DB::table('idps')->insertGetId([
            'user_id' => $employee->id,
            'year' => 2569,
            'status' => 'partially_submitted',
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $itemId = DB::table('idp_items')->insertGetId([
            'idp_id' => $idpId,
            'goal' => 'พัฒนาสมรรถนะ',
            'success_criteria' => 'ผ่านตามเกณฑ์',
            'status' => 'submitted',
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$supervisor, $itemId];
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }
}

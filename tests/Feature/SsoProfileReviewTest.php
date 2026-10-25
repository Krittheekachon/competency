<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SsoProfileReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_keep_existing_structure_without_changing_operational_fields(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        $user = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'workline' => 'สายสนับสนุน',
            'position' => 'ตำแหน่งเดิม',
            'sso_workline' => 'สายสนับสนุน',
            'sso_workline_type' => 'support',
            'sso_position_name' => 'ตำแหน่งใหม่',
        ]);
        $changeId = $this->pendingChange($user, 'ตำแหน่งใหม่');

        $this->actingAs($admin)
            ->patch(route('admin.users.sso-profile-review', $user), ['decision' => 'keep'])
            ->assertSessionHasNoErrors();

        $this->assertSame('ตำแหน่งเดิม', $user->fresh()->position);
        $this->assertDatabaseHas('sso_profile_changes', [
            'id' => $changeId,
            'status' => 'kept',
            'resolved_by' => $admin->id,
        ]);
    }

    public function test_admin_can_rename_the_same_internal_position_and_keep_reviewer_chain(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        $reviewer = User::factory()->create(['role_id' => $this->roleId('supervisor')]);
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายสนับสนุน', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $familyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId, 'name' => 'งานทดสอบ', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $positionId = DB::table('positions')->insertGetId([
            'job_family_id' => $familyId, 'name' => 'ตำแหน่งเดิม', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'workline' => 'สายสนับสนุน',
            'position_id' => $positionId,
            'position' => 'ตำแหน่งเดิม',
            'sso_workline_type' => 'support',
            'sso_position_name' => 'ตำแหน่งใหม่',
            'sso_position_type_name' => 'พนักงานมหาวิทยาลัย',
        ]);
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $user->id,
            'chain_type' => 'assessment',
            'step_order' => 1,
            'reviewer_id' => $reviewer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $changeId = $this->pendingChange($user, 'ตำแหน่งใหม่');

        $this->actingAs($admin)
            ->patch(route('admin.users.sso-profile-review', $user), ['decision' => 'rename'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('positions', ['id' => $positionId, 'name' => 'ตำแหน่งใหม่']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'position_id' => $positionId, 'position' => 'ตำแหน่งใหม่']);
        $this->assertDatabaseHas('user_reviewer_steps', [
            'user_id' => $user->id,
            'reviewer_id' => $reviewer->id,
            'chain_type' => 'assessment',
        ]);
        $this->assertDatabaseHas('position_title_aliases', ['name' => 'ตำแหน่งเดิม']);
        $this->assertDatabaseHas('sso_profile_changes', ['id' => $changeId, 'status' => 'renamed']);
    }

    private function pendingChange(User $user, string $positionName): int
    {
        return (int) DB::table('sso_profile_changes')->insertGetId([
            'user_id' => $user->id,
            'fingerprint' => hash('sha256', $user->id.':'.$positionName),
            'incoming_profile' => json_encode(['positionName' => $positionName], JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }
}

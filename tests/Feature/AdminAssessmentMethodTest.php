<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AssessmentMethodResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAssessmentMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_and_new_rounds_default_to_standard_method(): void
    {
        $roundId = $this->round('รอบวิธีเดิม');

        $this->assertDatabaseHas('assessment_rounds', [
            'id' => $roundId,
            'assessment_method' => AssessmentMethodResolver::STANDARD,
            'assessment_method_version' => 1,
        ]);
    }

    public function test_admin_can_select_faculty_method_before_any_assessment_is_submitted(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        $roundId = $this->round('รอบวิธีคณะ');

        $this->actingAs($admin)
            ->patch(route('admin.assessment-rounds.method.update', $roundId), [
                'assessment_method' => AssessmentMethodResolver::FACULTY,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessment_rounds', [
            'id' => $roundId,
            'assessment_method' => AssessmentMethodResolver::FACULTY,
            'assessment_method_version' => 1,
        ]);
    }

    public function test_admin_can_change_method_while_only_unsubmitted_drafts_exist(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $roundId = $this->round('รอบที่มีฉบับร่าง');
        $competencyId = $this->competency();

        DB::table('assessments')->insert([
            'assessment_round_id' => $roundId,
            'user_id' => $employee->id,
            'competency_id' => $competencyId,
            'status' => 'draft',
            'score' => 0,
            'self_submitted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.assessment-rounds.method.update', $roundId), [
                'assessment_method' => AssessmentMethodResolver::FACULTY,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessment_rounds', [
            'id' => $roundId,
            'assessment_method' => AssessmentMethodResolver::FACULTY,
        ]);
    }

    public function test_method_is_locked_after_the_first_self_assessment_submission(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $roundId = $this->round('รอบที่เริ่มส่งแล้ว');
        $competencyId = $this->competency();

        DB::table('assessments')->insert([
            'assessment_round_id' => $roundId,
            'user_id' => $employee->id,
            'competency_id' => $competencyId,
            'status' => 'self_submitted',
            'score' => 0,
            'self_submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.assessment-rounds.method.update', $roundId), [
                'assessment_method' => AssessmentMethodResolver::FACULTY,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('assessment_method');

        $this->assertDatabaseHas('assessment_rounds', [
            'id' => $roundId,
            'assessment_method' => AssessmentMethodResolver::STANDARD,
        ]);
    }

    public function test_non_admin_cannot_change_assessment_method(): void
    {
        $hr = User::factory()->create(['role_id' => $this->roleId('hr')]);
        $roundId = $this->round('รอบห้าม HR เปลี่ยนวิธี');

        $this->actingAs($hr)
            ->patch(route('admin.assessment-rounds.method.update', $roundId), [
                'assessment_method' => AssessmentMethodResolver::FACULTY,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('assessment_rounds', [
            'id' => $roundId,
            'assessment_method' => AssessmentMethodResolver::STANDARD,
        ]);
    }

    public function test_admin_dashboard_receives_method_and_lock_state(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        $roundId = $this->round('รอบแสดงบนหน้า Admin');

        $this->actingAs($admin)
            ->get('/dashboard?page=admin-assessment-methods')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('assessmentRounds.0.id', $roundId)
                ->where('assessmentRounds.0.assessmentMethod', AssessmentMethodResolver::STANDARD)
                ->where('assessmentRounds.0.assessmentMethodVersion', 1)
                ->where('assessmentRounds.0.assessmentMethodLocked', false));
    }

    private function round(string $name): int
    {
        return (int) DB::table('assessment_rounds')->insertGetId([
            'name' => $name,
            'year' => 2569,
            'self_assess_start' => now()->subDay()->toDateString(),
            'self_assess_end' => now()->addMonth()->toDateString(),
            'supervisor_assess_end' => now()->addMonths(2)->toDateString(),
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->orWhere('role_key', $key)->value('id');
    }

    private function competency(): int
    {
        $typeId = DB::table('competency_types')->insertGetId([
            'code' => 'CC-METHOD',
            'full_name' => 'Method test',
            'description' => 'Method test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId,
            'code' => 'CC-METHOD-01',
            'name' => 'สมรรถนะทดสอบวิธี',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AssessmentMethodResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssessmentRoundMethodTest extends TestCase
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

    public function test_hr_can_create_a_round_with_the_faculty_method(): void
    {
        $this->actingAs($this->hr())
            ->post(route('hr.assessment-rounds.store'), [
                'name' => 'รอบใหม่วิธีคณะ',
                'is_active' => false,
                'assessment_method' => AssessmentMethodResolver::FACULTY,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessment_rounds', [
            'name' => 'รอบใหม่วิธีคณะ',
            'assessment_method' => AssessmentMethodResolver::FACULTY,
            'assessment_method_version' => 1,
        ]);
    }

    public function test_hr_can_change_method_while_only_unsubmitted_drafts_exist(): void
    {
        $roundId = $this->round('รอบที่มีฉบับร่าง');
        $this->assessment($roundId, submitted: false);

        $this->actingAs($this->hr())
            ->put(route('hr.assessment-rounds.update', $roundId), $this->roundPayload('รอบที่มีฉบับร่าง', AssessmentMethodResolver::FACULTY))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessment_rounds', [
            'id' => $roundId,
            'assessment_method' => AssessmentMethodResolver::FACULTY,
        ]);
    }

    public function test_method_is_locked_after_the_first_self_assessment_submission(): void
    {
        $roundId = $this->round('รอบที่เริ่มส่งแล้ว');
        $this->assessment($roundId, submitted: true);

        $this->actingAs($this->hr())
            ->put(route('hr.assessment-rounds.update', $roundId), $this->roundPayload('รอบที่เริ่มส่งแล้ว', AssessmentMethodResolver::FACULTY))
            ->assertSessionHasErrors('assessment_method');

        // Saving other round fields with the unchanged method still works.
        $this->actingAs($this->hr())
            ->put(route('hr.assessment-rounds.update', $roundId), $this->roundPayload('รอบที่เริ่มส่งแล้ว (แก้ชื่อ)', AssessmentMethodResolver::STANDARD))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessment_rounds', [
            'id' => $roundId,
            'name' => 'รอบที่เริ่มส่งแล้ว (แก้ชื่อ)',
            'assessment_method' => AssessmentMethodResolver::STANDARD,
        ]);
    }

    public function test_hr_round_page_receives_method_and_lock_state(): void
    {
        $roundId = $this->round('รอบแสดงบนหน้า HR');

        $this->actingAs($this->hr())
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('HR/Dashboard')
                ->where('assessmentRounds.0.id', $roundId)
                ->where('assessmentRounds.0.assessmentMethod', AssessmentMethodResolver::STANDARD)
                ->where('assessmentRounds.0.assessmentMethodLocked', false));
    }

    private function hr(): User
    {
        return User::factory()->create(['role_id' => $this->roleId('hr')]);
    }

    private function roundPayload(string $name, string $method): array
    {
        return [
            'name' => $name,
            'self_assess_start' => now()->subDay()->toDateString(),
            'self_assess_end' => now()->addMonth()->toDateString(),
            'supervisor_assess_end' => now()->addMonths(2)->toDateString(),
            'is_active' => false,
            'assessment_method' => $method,
        ];
    }

    private function assessment(int $roundId, bool $submitted): void
    {
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        DB::table('assessments')->insert([
            'assessment_round_id' => $roundId,
            'user_id' => $employee->id,
            'competency_id' => $this->competency(),
            'status' => $submitted ? 'self_submitted' : 'draft',
            'score' => 0,
            'self_submitted_at' => $submitted ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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

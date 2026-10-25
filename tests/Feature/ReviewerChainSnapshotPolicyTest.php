<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ReviewerChainResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReviewerChainSnapshotPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // snapshot_required is introduced by the source branch migration, which is not part of this pending merge.
        if (! Schema::hasColumn('assessment_rounds', 'snapshot_required')) {
            Schema::table('assessment_rounds', function ($table): void {
                $table->boolean('snapshot_required')->default(true);
            });
        }
    }

    public function test_snapshot_required_round_without_reviewer_snapshot_fails_closed_without_mutating_assessment(): void
    {
        $roundId = $this->round(snapshotRequired: true);
        $employee = User::factory()->create();
        $currentReviewer = User::factory()->create();
        $this->assignCurrentReviewer($employee, $currentReviewer);
        $draftedAt = now()->subMinute()->startOfSecond();
        $assessmentId = DB::table('assessments')->insertGetId([
            'assessment_round_id' => $roundId,
            'user_id' => $employee->id,
            'status' => 'draft',
            'last_draft_saved_at' => $draftedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $steps = app(ReviewerChainResolver::class)->stepsForUser((object) [
            'id' => $employee->id,
            'assessment_round_id' => $roundId,
        ]);

        $this->assertSame([], $steps);
        $this->assertDatabaseMissing('user_round_profiles', [
            'assessment_round_id' => $roundId,
            'user_id' => $employee->id,
        ]);
        $this->assertDatabaseMissing('user_round_reviewer_steps', [
            'assessment_round_id' => $roundId,
            'user_id' => $employee->id,
        ]);
        $this->assertDatabaseHas('user_reviewer_steps', [
            'user_id' => $employee->id,
            'reviewer_id' => $currentReviewer->id,
            'chain_type' => 'assessment',
        ]);
        $this->assertSame('draft', DB::table('assessments')->where('id', $assessmentId)->value('status'));
        $this->assertSame($draftedAt->toDateTimeString(), DB::table('assessments')->where('id', $assessmentId)->value('last_draft_saved_at'));
    }

    public function test_snapshot_required_round_uses_its_persisted_reviewer_snapshot(): void
    {
        $roundId = $this->round(snapshotRequired: true);
        $employee = User::factory()->create();
        $snapshotReviewer = User::factory()->create();
        $currentReviewer = User::factory()->create();
        $this->assignCurrentReviewer($employee, $currentReviewer);
        $this->insertProfileSnapshot($roundId, $employee);
        $this->insertReviewerSnapshot($roundId, $employee, $snapshotReviewer);

        $steps = app(ReviewerChainResolver::class)->stepsForUser((object) [
            'id' => $employee->id,
            'assessment_round_id' => $roundId,
        ]);

        $this->assertSame([['step' => 1, 'reviewer_id' => $snapshotReviewer->id]], $steps);
        $this->assertDatabaseHas('user_round_reviewer_steps', [
            'assessment_round_id' => $roundId,
            'user_id' => $employee->id,
            'reviewer_id' => $snapshotReviewer->id,
        ]);
    }

    public function test_explicit_legacy_round_without_snapshots_can_fall_back_to_current_reviewer_chain(): void
    {
        $roundId = $this->round(snapshotRequired: false);
        $employee = User::factory()->create();
        $reviewer = User::factory()->create();
        $this->assignCurrentReviewer($employee, $reviewer);

        $steps = app(ReviewerChainResolver::class)->stepsForUser((object) [
            'id' => $employee->id,
            'assessment_round_id' => $roundId,
        ]);

        $this->assertSame([['step' => 1, 'reviewer_id' => $reviewer->id]], $steps);
        $this->assertDatabaseMissing('user_round_profiles', [
            'assessment_round_id' => $roundId,
            'user_id' => $employee->id,
        ]);
    }

    private function round(bool $snapshotRequired): int
    {
        return (int) DB::table('assessment_rounds')->insertGetId([
            'name' => $snapshotRequired ? 'Snapshot required test round' : 'Explicit legacy test round',
            'year' => $snapshotRequired ? 2570 : 2569,
            'snapshot_required' => $snapshotRequired,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assignCurrentReviewer(User $employee, User $reviewer): void
    {
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employee->id,
            'reviewer_id' => $reviewer->id,
            'step_order' => 1,
            'chain_type' => 'assessment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertProfileSnapshot(int $roundId, User $employee): void
    {
        DB::table('user_round_profiles')->insert([
            'assessment_round_id' => $roundId,
            'user_id' => $employee->id,
            'captured_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertReviewerSnapshot(int $roundId, User $employee, User $reviewer): void
    {
        DB::table('user_round_reviewer_steps')->insert([
            'assessment_round_id' => $roundId,
            'user_id' => $employee->id,
            'chain_type' => 'assessment',
            'step_order' => 1,
            'reviewer_id' => $reviewer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AssessmentRoundSnapshotService;
use App\Services\ReviewerChainResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsFacultyAssessment;
use Tests\TestCase;

class ReviewerScoringGrantSnapshotTest extends TestCase
{
    use BuildsFacultyAssessment;
    use RefreshDatabase;

    public function test_scoring_grants_are_frozen_in_the_round_snapshot(): void
    {
        $roundId = $this->activeFacultyRound();
        $employee = User::factory()->create();
        $reviewer = User::factory()->create();
        $this->assignReviewer($employee, $reviewer, 1, 'CC,FC');

        app(AssessmentRoundSnapshotService::class)->captureIfMissing($employee, $roundId);
        $this->assertSame('CC,FC', DB::table('user_round_reviewer_steps')->where('user_id', $employee->id)->value('scoring_types'));

        // Admin edits the live chain afterwards; the running round keeps its own grant.
        DB::table('user_reviewer_steps')->where('user_id', $employee->id)->update(['scoring_types' => 'MC']);
        $target = (object) ['id' => $employee->id, 'assessment_round_id' => $roundId];

        $this->assertSame(['CC', 'FC'], app(ReviewerChainResolver::class)->scoringTypesForReviewer($target, $reviewer->id));
    }
}

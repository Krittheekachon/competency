<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsFacultyAssessment;
use Tests\TestCase;

/** The reviewer screen enables score editing only for the types the admin granted on that reviewer's step. */
class ReviewerScoringPermissionUiTest extends TestCase
{
    use BuildsFacultyAssessment;
    use RefreshDatabase;

    public function test_can_edit_score_flag_follows_the_grant_per_reviewer_and_type(): void
    {
        $roundId = $this->activeFacultyRound();
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $scorer = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $approver = User::factory()->create(['role_id' => $this->roleId('employee')]);
        [$ccId] = $this->makeAssessable($employee, $roundId);
        $this->assignReviewer($employee, $scorer, 1, 'CC');
        $this->assignReviewer($employee, $approver, 2);
        $this->submittedAssessment($employee, $roundId, $ccId, 2);

        $flag = function (User $reviewer) use ($ccId): ?bool {
            $this->actingAs($reviewer);
            $items = null;
            $this->get('/dashboard')->assertOk()->assertInertia(function ($page) use (&$items): void {
                $items = $page->toArray()['props']['assessmentApprovalModule']['items'] ?? [];
            });
            foreach ($items as $item) {
                foreach ($item['allCompetencies'] as $gap) {
                    if ((int) ($gap['id'] ?? $gap['competencyId'] ?? 0) === $ccId || ($gap['t'] ?? '') === 'CC') {
                        return $gap['canEditScore'];
                    }
                }
            }

            return null;
        };

        $this->assertTrue($flag($scorer), 'granted CC reviewer may edit CC scores');
        $this->assertFalse($flag($approver), 'reviewer without a grant is approval only');
    }
}

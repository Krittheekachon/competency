<?php

namespace App\Services;

use App\Models\User;

class FacultyAssessmentPermissionResolver
{
    public function __construct(private ReviewerChainResolver $chains) {}

    /**
     * Every reviewer in the chain who may score this competency type scores it; the latest one
     * before $beforeStep is where a returned result goes back to.
     *
     * @param  array<int, array{step: int|string, reviewer_id: int|string}>  $steps
     */
    public function latestScorerStepBefore(User $employee, string $competencyType, array $steps, int $beforeStep): ?int
    {
        $reviewers = User::query()
            ->with('role')
            ->whereIn('id', collect($steps)->pluck('reviewer_id')->map(fn ($id): int => (int) $id)->unique())
            ->get()
            ->keyBy('id');

        return collect($steps)
            ->filter(fn (array $step): bool => (int) $step['step'] < $beforeStep)
            ->sortBy(fn (array $step): int => (int) $step['step'])
            ->filter(function (array $step) use ($reviewers, $employee, $competencyType): bool {
                $reviewer = $reviewers->get((int) $step['reviewer_id']);

                return $reviewer && $this->canScore($reviewer, $employee, $competencyType);
            })
            ->map(fn (array $step): int => (int) $step['step'])
            ->last();
    }

    /**
     * Whether $reviewer may score competencies of this type for $employee. Admin grants this per reviewer
     * step (user_reviewer_steps.scoring_types, frozen in the round snapshot); roles play no part. A reviewer
     * without a grant can only approve or return.
     */
    public function canScore(User $reviewer, User $employee, string $competencyType): bool
    {
        $type = strtoupper(trim($competencyType));
        // FC is stored as FC1/FC2 sub-types; grants apply to the whole FC group.
        $type = str_starts_with($type, 'FC') ? 'FC' : $type;

        return in_array($type, $this->chains->scoringTypesForReviewer($employee, (int) $reviewer->id), true);
    }
}

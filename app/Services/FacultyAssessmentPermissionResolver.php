<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class FacultyAssessmentPermissionResolver
{
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

    public function canScore(User $reviewer, User $employee, string $competencyType): bool
    {
        $type = strtoupper(trim($competencyType));
        // FC is stored as FC1/FC2 sub-types; permissions apply to the whole FC group.
        $type = str_starts_with($type, 'FC') ? 'FC' : $type;
        // Use the persisted canonical position classification, never a display name or SSO guess.
        $titleId = $employee->position_title_id;
        if (! $titleId && $employee->position_id) {
            $titleId = DB::table('positions')->where('id', $employee->position_id)->value('position_title_id');
        }
        $worklineType = $titleId
            ? DB::table('position_titles')->where('id', $titleId)->value('workline_type')
            : null;
        $isSupport = $worklineType === 'support';
        $isAcademic = $worklineType === 'academic';
        $isExecutive = $employee->hasRole(['deputy_dean', 'assistant_dean']);

        if ($isSupport) {
            return $reviewer->isDeputyDean() && in_array($type, ['CC', 'FC'], true);
        }

        if (! $isAcademic) {
            return false;
        }

        if ($isExecutive && $type === 'MC') {
            return $reviewer->isDean();
        }

        if ($type === 'MC') {
            return false;
        }

        return $reviewer->isAcademicDepartmentHead() && in_array($type, ['CC', 'FC'], true);
    }
}

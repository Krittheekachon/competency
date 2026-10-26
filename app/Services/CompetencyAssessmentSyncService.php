<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CompetencyAssessmentSyncService
{
    public function __construct(
        private NotificationService $notifications,
        private PositionTitleResolver $positionTitles,
    )
    {
    }

    public function syncUser(User $user): void
    {
        $competencyIds = $this->competencyIdsForUser($user);
        $this->syncUserAssessments($user->id, $competencyIds);
    }

    public function syncAllActiveUsers(): void
    {
        User::query()
            ->where('is_active', true)
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $this->syncUser($user);
                }
            });
    }

    private function syncUserAssessments(int $userId, Collection $competencyIds): void
    {
        $competencyIds = $competencyIds->filter()->unique()->values();
        $now = now();
        $assessmentRoundId = Schema::hasColumn('assessments', 'assessment_round_id')
            ? $this->activeAssessmentRoundId()
            : null;

        if (Schema::hasColumn('assessments', 'assessment_round_id') && ! $assessmentRoundId) {
            return;
        }

        foreach ($competencyIds as $competencyId) {
            $attributes = [
                    'user_id' => $userId,
                    'competency_id' => $competencyId,
                    'assessment_round_id' => $assessmentRoundId,
                    'status' => 'draft',
                    'score' => 0,
                    'note' => '',
                    'created_at' => $now,
                    'updated_at' => $now,
            ];

            DB::table('assessments')->insertOrIgnore($attributes);
        }

        DB::table('assessments')
            ->where('user_id', $userId)
            ->when($assessmentRoundId, fn ($query) => $query->where('assessment_round_id', $assessmentRoundId))
            ->when($competencyIds->isNotEmpty(), fn ($query) => $query->whereNotIn('competency_id', $competencyIds))
            ->where('status', 'draft')
            ->where(function ($query) {
                $query->whereNull('score')->orWhere('score', 0);
            })
            ->where(function ($query) {
                $query->whereNull('note')->orWhere('note', '');
            })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('assessment_indicator_results')
                    ->whereColumn('assessment_indicator_results.assessment_id', 'assessments.id');
            })
            ->delete();
    }

    private function activeAssessmentRoundId(): ?int
    {
        $roundId = DB::table('assessment_rounds')
            ->where('is_active', true)
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->value('id');

        return $roundId ? (int) $roundId : null;
    }

    private function competencyIdsForUser(User $user): Collection
    {
        $roundId = $this->activeAssessmentRoundId();
        if (! $roundId) {
            return collect();
        }

        $positionTitleId = $this->positionTitles->forUser($user);
        $positionIds = $positionTitleId
            ? collect($this->positionTitles->positionIds($positionTitleId))
            : $this->positionIdsForUser($user);

        if (! $positionTitleId && $positionIds->isEmpty()) {
            return collect();
        }

        $mappingQuery = DB::table('position_competencies')
            ->where('assessment_round_id', $roundId)
            ->when(
                $positionTitleId,
                fn ($query) => $query->where('position_title_id', $positionTitleId),
                fn ($query) => $query->whereIn('position_id', $positionIds),
            );

        if (! (clone $mappingQuery)->exists()) {
            $this->notifyUnmappedPositions($user, $positionIds);
            $this->notifications->notifyHrUserWithUnmappedPosition($user);
        }

        return $mappingQuery
            ->pluck('competency_id')
            ->unique()
            ->values();
    }

    private function notifyUnmappedPositions(User $user, Collection $positionIds): void
    {
        $names = DB::table('positions')
            ->whereIn('id', $positionIds)
            ->pluck('name')
            ->filter()
            ->unique()
            ->values();

        if ($names->isEmpty() && $user->position) {
            $names->push($user->position);
        }

        $names->each(fn (string $name) => $this->notifications->notifyHrUnmappedPosition($name));
    }

    private function positionIdsForUser(User $user): Collection
    {
        $positionIds = collect();

        if ($user->position_id) {
            $positionIds->push($user->position_id);
        }

        if (! $user->position) {
            return $positionIds->filter()->unique()->values();
        }

        $worklineId = $user->workline
            ? DB::table('worklines')->where('name', $user->workline)->value('id')
            : null;

        $matchedIds = DB::table('positions')
            ->join('job_families', 'positions.job_family_id', '=', 'job_families.id')
            ->where('positions.name', $user->position)
            ->when($worklineId, fn ($query) => $query->where('job_families.workline_id', $worklineId))
            ->when($user->department, function ($query) use ($user) {
                $departmentRoot = trim(explode(' > ', $user->department)[0] ?? $user->department);

                $query->where(function ($nested) use ($user, $departmentRoot) {
                    $nested->where('job_families.name', $user->department)
                        ->orWhere('job_families.name', $departmentRoot);
                });
            })
            ->pluck('positions.id');

        return $positionIds->merge($matchedIds)->filter()->unique()->values();
    }
}

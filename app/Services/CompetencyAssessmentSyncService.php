<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CompetencyAssessmentSyncService
{
    public function syncUser(User $user): void
    {
        $competencyIds = $this->competencyIdsForUser($user);
        $this->syncUserAssessments($user->id, $competencyIds);
    }

    public function syncScope(int $roundId, int $jobFamilyId, int $levelId): void
    {
        $competencyIds = DB::table('hr_expectations')
            ->where('assessment_round_id', $roundId)
            ->where('job_family_id', $jobFamilyId)
            ->where('level_id', $levelId)
            ->whereNull('position_id')
            ->pluck('competency_id')
            ->unique()
            ->values();

        $jobFamily = DB::table('job_families')
            ->leftJoin('worklines', 'job_families.workline_id', '=', 'worklines.id')
            ->where('job_families.id', $jobFamilyId)
            ->select('job_families.name', 'worklines.name as workline_name')
            ->first();

        $levelName = DB::table('levels')->where('id', $levelId)->value('name');

        if (! $jobFamily || ! $levelName) {
            return;
        }

        $users = User::query()
            ->where('is_active', true)
            ->where('workline', $jobFamily->workline_name)
            ->where(function ($query) use ($jobFamily) {
                $query->where('department', $jobFamily->name)
                    ->orWhere('department', 'like', $jobFamily->name.' > %')
                    ->orWhere('position', $jobFamily->name)
                    ->orWhere('level', $jobFamily->name);
            })
            ->where(function ($query) use ($levelId, $levelName) {
                $query->where('level_id', $levelId)
                    ->orWhere('level', $levelName)
                    ->orWhere('position', $levelName);
            })
            ->get();

        foreach ($users as $user) {
            $this->syncUserAssessments($user->id, $competencyIds);
        }
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

        foreach ($competencyIds as $competencyId) {
            $attributes = [
                    'user_id' => $userId,
                    'competency_id' => $competencyId,
                    'status' => 'draft',
                    'score' => 0,
                    'note' => '',
                    'created_at' => $now,
                    'updated_at' => $now,
            ];

            if ($assessmentRoundId) {
                $attributes['assessment_round_id'] = $assessmentRoundId;
            }

            DB::table('assessments')->insertOrIgnore($attributes);
        }

        DB::table('assessments')
            ->where('user_id', $userId)
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

    private function activeAssessmentRoundId(): int
    {
        $roundId = DB::table('assessment_rounds')
            ->where('is_active', true)
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->value('id');

        if ($roundId) {
            return (int) $roundId;
        }

        return (int) DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบประเมินปัจจุบัน',
            'year' => (int) now()->format('Y') + 543,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function competencyIdsForUser(User $user): Collection
    {
        $levelIds = $this->levelIdsForUser($user);
        $positionIds = $this->positionIdsForUser($user);

        if ($levelIds->isEmpty() && $positionIds->isEmpty()) {
            return collect();
        }

        $jobFamilyIds = $this->jobFamilyIdsForUser($user);

        $expectationIds = ($levelIds->isEmpty() || $jobFamilyIds->isEmpty())
            ? collect()
            : DB::table('hr_expectations')
                ->whereIn('level_id', $levelIds)
                ->whereIn('job_family_id', $jobFamilyIds)
                ->pluck('competency_id');

        $positionCompetencyIds = $positionIds->isEmpty()
            ? collect()
            : DB::table('position_competencies')
                ->whereIn('position_id', $positionIds)
                ->pluck('competency_id');

        return $expectationIds
            ->merge($positionCompetencyIds)
            ->unique()
            ->values();
    }

    private function levelIdsForUser(User $user): Collection
    {
        $levelIds = collect();

        if ($user->level_id) {
            $levelIds->push($user->level_id);
        }

        foreach ([$user->level, $user->position] as $levelName) {
            if (! $levelName) {
                continue;
            }

            $levelIds = $levelIds->merge(
                DB::table('levels')
                    ->where('name', $levelName)
                    ->pluck('id')
            );
        }

        return $levelIds->filter()->unique()->values();
    }

    private function jobFamilyIdsForUser(User $user): Collection
    {
        $jobFamilyIds = collect();

        if ($user->position_id) {
            $jobFamilyIds->push(DB::table('positions')->where('id', $user->position_id)->value('job_family_id'));
        }

        if ($user->workline) {
            $worklineId = DB::table('worklines')->where('name', $user->workline)->value('id');

            if ($worklineId) {
                $names = collect([$user->department, $user->position, $user->level])
                    ->filter()
                    ->flatMap(fn (string $value) => collect(explode(' > ', $value))->map(fn (string $part) => trim($part)))
                    ->filter()
                    ->unique()
                    ->values();

                if ($names->isNotEmpty()) {
                    $jobFamilyIds = $jobFamilyIds->merge(
                        DB::table('job_families')
                            ->where('workline_id', $worklineId)
                            ->whereIn('name', $names)
                            ->pluck('id')
                    );
                }
            }
        }

        return $jobFamilyIds->filter()->unique()->values();
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

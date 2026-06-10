<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExpectedLevelResolver
{
    public function forUserCompetency(User $user, int $competencyId): ?int
    {
        $levelIds = $this->levelIdsForUser($user);
        $jobFamilyIds = $this->jobFamilyIdsForUser($user);

        if ($levelIds->isEmpty() || $jobFamilyIds->isEmpty()) {
            return null;
        }

        $expectedLevels = DB::table('hr_expectations')
            ->leftJoin('levels', 'hr_expectations.level_id', '=', 'levels.id')
            ->where('hr_expectations.competency_id', $competencyId)
            ->whereIn('hr_expectations.level_id', $levelIds)
            ->whereIn('hr_expectations.job_family_id', $jobFamilyIds)
            ->selectRaw('COALESCE(hr_expectations.expected_level, levels.expected_level) as expected_level')
            ->pluck('expected_level')
            ->filter(fn ($level) => $level !== null)
            ->map(fn ($level): int => (int) $level);

        return $expectedLevels->isEmpty() ? null : $expectedLevels->max();
    }

    private function levelIdsForUser(User $user): Collection
    {
        $levelIds = collect();
        $worklineId = $this->worklineIdFromUser($user);

        if ($user->level_id) {
            $levelIds->push($user->level_id);
        }

        foreach ([$user->level, $user->position] as $levelName) {
            if (! $levelName) {
                continue;
            }

            $matchingLevels = DB::table('levels')
                ->where('name', $levelName)
                ->where(function ($query) use ($worklineId) {
                    $query->whereNull('workline_id');

                    if ($worklineId) {
                        $query->orWhere('workline_id', $worklineId);
                    }
                })
                ->pluck('id');

            $levelIds = $levelIds->merge($matchingLevels);
        }

        return $levelIds->filter()->unique()->values();
    }

    private function jobFamilyIdsForUser(User $user): Collection
    {
        $jobFamilyIds = collect();

        if ($user->position_id) {
            $jobFamilyIds->push(DB::table('positions')->where('id', $user->position_id)->value('job_family_id'));
        }

        $worklineId = $this->worklineIdFromUser($user);

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

            $jobFamilyIds = $jobFamilyIds->merge(
                DB::table('job_families')->where('workline_id', $worklineId)->pluck('id')
            );
        }

        return $jobFamilyIds->filter()->unique()->values();
    }

    private function worklineIdFromUser(User $user): ?int
    {
        if (! $user->workline) {
            return null;
        }

        $workline = trim($user->workline);
        $withoutPrefix = preg_replace('/^สาย/u', '', $workline) ?: $workline;
        $candidates = collect([
            $workline,
            $withoutPrefix,
            'สาย'.$withoutPrefix,
            'สายงาน'.$withoutPrefix,
        ])->filter()->unique()->values();

        $id = DB::table('worklines')
            ->whereIn('name', $candidates)
            ->value('id');

        return $id ? (int) $id : null;
    }
}

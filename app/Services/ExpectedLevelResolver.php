<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExpectedLevelResolver
{
    private array $expectedLevelCache = [];

    private array $levelIdsCache = [];

    private array $jobFamilyIdsCache = [];

    private array $positionIdsCache = [];

    private array $worklineIdCache = [];

    private ?int $activeRoundIdCache = null;

    private bool $activeRoundIdResolved = false;

    public function resetRequestCache(): void
    {
        $this->expectedLevelCache = [];
        $this->levelIdsCache = [];
        $this->jobFamilyIdsCache = [];
        $this->positionIdsCache = [];
        $this->worklineIdCache = [];
        $this->activeRoundIdCache = null;
        $this->activeRoundIdResolved = false;
    }

    public function forUserCompetency(User $user, int $competencyId): ?int
    {
        return $this->forUserCompetencies($user, [$competencyId])[$competencyId] ?? null;
    }

    public function forUserCompetencies(User $user, iterable $competencyIds): array
    {
        $competencyIds = collect($competencyIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        $userCacheKey = $this->userCacheKey($user);
        $resolved = [];
        $unresolvedIds = $competencyIds->filter(function (int $competencyId) use ($userCacheKey, &$resolved): bool {
            $cacheKey = $userCacheKey.':'.$competencyId;

            if (! array_key_exists($cacheKey, $this->expectedLevelCache)) {
                return true;
            }

            $resolved[$competencyId] = $this->expectedLevelCache[$cacheKey];

            return false;
        })->values();

        if ($unresolvedIds->isEmpty()) {
            return $resolved;
        }

        $levelIds = $this->levelIdsForUser($user);
        $jobFamilyIds = $this->jobFamilyIdsForUser($user);
        $roundId = $this->activeRoundId();

        if (! $roundId || $levelIds->isEmpty()) {
            foreach ($unresolvedIds as $competencyId) {
                $resolved[$competencyId] = $this->expectedLevelCache[$userCacheKey.':'.$competencyId] = null;
            }

            return $resolved;
        }

        $expectedLevels = $jobFamilyIds->isEmpty()
            ? collect()
            : DB::table('hr_expectations')
                ->leftJoin('levels', 'hr_expectations.level_id', '=', 'levels.id')
                ->whereIn('hr_expectations.competency_id', $unresolvedIds)
                ->where('hr_expectations.assessment_round_id', $roundId)
                ->whereIn('hr_expectations.level_id', $levelIds)
                ->whereIn('hr_expectations.job_family_id', $jobFamilyIds)
                ->selectRaw('hr_expectations.competency_id, MAX(COALESCE(hr_expectations.expected_level, levels.expected_level)) as expected_level')
                ->groupBy('hr_expectations.competency_id')
                ->pluck('expected_level', 'hr_expectations.competency_id');

        foreach ($expectedLevels as $competencyId => $expectedLevel) {
            $competencyId = (int) $competencyId;
            $resolved[$competencyId] = $this->expectedLevelCache[$userCacheKey.':'.$competencyId]
                = $expectedLevel === null ? null : (int) $expectedLevel;
        }

        $fallbackIds = $unresolvedIds
            ->reject(fn (int $competencyId): bool => array_key_exists($competencyId, $resolved))
            ->values();
        if ($fallbackIds->isEmpty()) {
            return $resolved;
        }

        $positionIds = $this->positionIdsForUser($user);
        $positionCompetencyIds = $positionIds->isEmpty()
            ? collect()
            : DB::table('position_competencies')
                ->whereIn('position_id', $positionIds)
                ->whereIn('competency_id', $fallbackIds)
                ->where('assessment_round_id', $roundId)
                ->pluck('competency_id')
                ->map(fn ($id): int => (int) $id)
                ->flip();

        $levelExpectedLevels = DB::table('levels')
            ->whereIn('id', $levelIds)
            ->pluck('expected_level')
            ->filter(fn ($level) => $level !== null)
            ->map(fn ($level): int => (int) $level);
        $fallbackExpectedLevel = $levelExpectedLevels->isEmpty()
            ? null
            : $levelExpectedLevels->max();

        foreach ($fallbackIds as $competencyId) {
            $resolved[$competencyId] = $this->expectedLevelCache[$userCacheKey.':'.$competencyId]
                = $positionCompetencyIds->has($competencyId) ? $fallbackExpectedLevel : null;
        }

        return $resolved;
    }

    private function levelIdsForUser(User $user): Collection
    {
        $cacheKey = $this->userCacheKey($user);

        if (isset($this->levelIdsCache[$cacheKey])) {
            return $this->levelIdsCache[$cacheKey];
        }

        $levelIds = collect();
        $worklineId = $this->worklineIdFromUser($user);

        if (! $worklineId) {
            return $this->levelIdsCache[$cacheKey] = $levelIds;
        }

        if ($user->level_id) {
            $levelIds = $levelIds->merge(DB::table('levels')
                ->where('id', $user->level_id)
                ->where('workline_id', $worklineId)
                ->whereNull('job_family_id')
                ->pluck('id'));
        }

        if ($user->level) {
            $levelIds = $levelIds->merge(DB::table('levels')
                ->where('workline_id', $worklineId)
                ->whereNull('job_family_id')
                ->where('name', $user->level)
                ->pluck('id'));
        }

        return $this->levelIdsCache[$cacheKey] = $levelIds->filter()->unique()->values();
    }

    private function jobFamilyIdsForUser(User $user): Collection
    {
        $cacheKey = $this->userCacheKey($user);

        if (isset($this->jobFamilyIdsCache[$cacheKey])) {
            return $this->jobFamilyIdsCache[$cacheKey];
        }

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

        }

        return $this->jobFamilyIdsCache[$cacheKey] = $jobFamilyIds->filter()->unique()->values();
    }

    private function positionIdsForUser(User $user): Collection
    {
        $cacheKey = $this->userCacheKey($user);

        if (isset($this->positionIdsCache[$cacheKey])) {
            return $this->positionIdsCache[$cacheKey];
        }

        $positionIds = collect();

        if ($user->position_id) {
            $positionIds->push($user->position_id);
        }

        if (! $user->position) {
            return $this->positionIdsCache[$cacheKey] = $positionIds->filter()->unique()->values();
        }

        $worklineId = $this->worklineIdFromUser($user);

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

        return $this->positionIdsCache[$cacheKey] = $positionIds
            ->merge($matchedIds)
            ->filter()
            ->unique()
            ->values();
    }

    private function worklineIdFromUser(User $user): ?int
    {
        if (! $user->workline) {
            return null;
        }

        $workline = trim($user->workline);

        if (array_key_exists($workline, $this->worklineIdCache)) {
            return $this->worklineIdCache[$workline];
        }

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

        return $this->worklineIdCache[$workline] = $id ? (int) $id : null;
    }

    private function userCacheKey(User $user): string
    {
        return (string) ($user->getKey() ?? spl_object_id($user));
    }

    private function activeRoundId(): ?int
    {
        if ($this->activeRoundIdResolved) {
            return $this->activeRoundIdCache;
        }

        $id = DB::table('assessment_rounds')->where('is_active', true)->orderByDesc('id')->value('id');

        $this->activeRoundIdResolved = true;

        return $this->activeRoundIdCache = $id ? (int) $id : null;
    }
}

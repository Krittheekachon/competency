<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FacultyAssessmentScorer
{
    public function scoreForCompetency(int $competencyId, array $checkedIndicatorKeys): int
    {
        $levels = DB::table('competency_levels')
            ->leftJoin('comp_level_indicators', 'competency_levels.id', '=', 'comp_level_indicators.competency_level_id')
            ->where('competency_levels.competency_id', $competencyId)
            ->whereIn('competency_levels.level', [1, 3, 5])
            ->groupBy('competency_levels.id', 'competency_levels.level')
            ->orderBy('competency_levels.level')
            ->get([
                'competency_levels.id',
                'competency_levels.level',
                DB::raw('COUNT(comp_level_indicators.id) AS indicator_count'),
            ])
            ->map(fn (object $level): array => [
                'id' => (int) $level->id,
                'level' => (int) $level->level,
                'indicatorCount' => (int) $level->indicator_count,
            ])
            ->all();

        return $this->score($competencyId, $levels, $checkedIndicatorKeys);
    }

    public function score(int $competencyId, array $levels, array $checkedIndicatorKeys): int
    {
        $definitions = collect($levels)->keyBy(fn (array $level): int => (int) $level['level']);
        $levelOne = $definitions->get(1);
        $levelThree = $definitions->get(3);
        $levelFive = $definitions->get(5);

        if (! $levelOne || ! $levelThree || ! $levelFive
            || (int) $levelOne['indicatorCount'] < 1
            || (int) $levelThree['indicatorCount'] < 1
            || (int) $levelFive['indicatorCount'] < 2) {
            throw ValidationException::withMessages([
                'assessment' => 'เกณฑ์พฤติกรรมของสมรรถนะนี้ยังไม่ครบสำหรับวิธีประเมินของคณะ',
            ]);
        }

        $allowedByLevelId = $definitions->mapWithKeys(fn (array $level): array => [
            (int) $level['id'] => [
                'level' => (int) $level['level'],
                'indicatorCount' => (int) $level['indicatorCount'],
            ],
        ]);
        $selectedByLevel = [1 => [], 3 => [], 5 => []];

        foreach (array_values(array_unique($checkedIndicatorKeys)) as $key) {
            if (! preg_match('/^(\d+):(\d+):(\d+)$/', (string) $key, $matches)
                || (int) $matches[1] !== $competencyId) {
                throw $this->invalidIndicator();
            }

            $levelId = (int) $matches[2];
            $indicatorIndex = (int) $matches[3];
            $definition = $allowedByLevelId->get($levelId);

            if (! $definition || $indicatorIndex < 0 || $indicatorIndex >= $definition['indicatorCount']) {
                throw $this->invalidIndicator();
            }

            $selectedByLevel[$definition['level']][$indicatorIndex] = true;
        }

        $selectedOne = count($selectedByLevel[1]);
        if ($selectedOne === 0) {
            return 0;
        }

        if ($selectedOne < (int) $levelOne['indicatorCount']) {
            return 1;
        }

        $selectedThree = count($selectedByLevel[3]);
        if ($selectedThree === 0) {
            return 2;
        }

        if ($selectedThree < (int) $levelThree['indicatorCount']) {
            return 3;
        }

        return count($selectedByLevel[5]) >= 2 ? 5 : 4;
    }

    private function invalidIndicator(): ValidationException
    {
        return ValidationException::withMessages([
            'checked_indicators' => 'รายการพฤติกรรมที่เลือกไม่ตรงกับเกณฑ์ของสมรรถนะนี้',
        ]);
    }
}

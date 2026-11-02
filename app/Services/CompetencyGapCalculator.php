<?php

namespace App\Services;

class CompetencyGapCalculator
{
    public function calculate(string $assessmentMethod, float|int $actualLevel, float|int|null $expectedLevel): array
    {
        $actual = round((float) $actualLevel, 2);
        $expected = $expectedLevel === null ? null : round((float) $expectedLevel, 2);
        $gap = $expected === null ? null : round($actual - $expected, 2);
        $percentage = $expected !== null && $expected > 0
            ? round(($actual / $expected) * 100, 2)
            : null;

        $requiresIdp = match ($assessmentMethod) {
            AssessmentMethodResolver::FACULTY => $percentage !== null && $percentage < 80,
            default => $gap !== null && $gap < 0,
        };

        return [
            'actualLevel' => $actual,
            'expectedLevel' => $expected,
            'gap' => $gap,
            'percentage' => $percentage,
            'requiresIdp' => $requiresIdp,
        ];
    }
}

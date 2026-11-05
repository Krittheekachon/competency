<?php

namespace App\Services;

class CompetencyGapCalculator
{
    public function calculate(string $assessmentMethod, float|int $actualLevel, float|int|null $expectedLevel): array
    {
        $rawActual = (float) $actualLevel;
        $rawExpected = $expectedLevel !== null && is_finite((float) $expectedLevel) && $expectedLevel > 0
            ? (float) $expectedLevel
            : null;
        $rawPercentage = $rawExpected !== null ? ($rawActual / $rawExpected) * 100 : null;

        $actual = round($rawActual, 2);
        $expected = $rawExpected === null ? null : round($rawExpected, 2);
        $gap = $expected === null ? null : round($actual - $expected, 2);
        $percentage = $rawPercentage === null ? null : round($rawPercentage, 2);

        $requiresIdp = match ($assessmentMethod) {
            AssessmentMethodResolver::FACULTY => $rawPercentage !== null && $rawPercentage < 80,
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

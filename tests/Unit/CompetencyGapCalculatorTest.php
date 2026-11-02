<?php

namespace Tests\Unit;

use App\Services\AssessmentMethodResolver;
use App\Services\CompetencyGapCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CompetencyGapCalculatorTest extends TestCase
{
    #[DataProvider('facultyCases')]
    public function test_faculty_method_uses_eighty_percent_threshold(float $actual, float $expected, bool $requiresIdp): void
    {
        $result = (new CompetencyGapCalculator())->calculate(
            AssessmentMethodResolver::FACULTY,
            $actual,
            $expected,
        );

        $this->assertSame($requiresIdp, $result['requiresIdp']);
        $this->assertSame(round($actual - $expected, 2), $result['gap']);
    }

    public static function facultyCases(): array
    {
        return [
            'zero of three' => [0, 3, true],
            'three of four is seventy five percent' => [3, 4, true],
            'four of five is exactly eighty percent' => [4, 5, false],
            'three of three is one hundred percent' => [3, 3, false],
        ];
    }

    public function test_standard_method_keeps_negative_gap_rule(): void
    {
        $result = (new CompetencyGapCalculator())->calculate(
            AssessmentMethodResolver::STANDARD,
            4,
            5,
        );

        $this->assertTrue($result['requiresIdp']);
        $this->assertSame(-1.0, $result['gap']);
    }

    public function test_missing_or_zero_expected_level_does_not_create_idp_work(): void
    {
        $calculator = new CompetencyGapCalculator();

        $this->assertFalse($calculator->calculate(AssessmentMethodResolver::FACULTY, 0, null)['requiresIdp']);
        $this->assertFalse($calculator->calculate(AssessmentMethodResolver::FACULTY, 0, 0)['requiresIdp']);
    }
}

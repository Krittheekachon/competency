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
            'below before actual rounding' => [3.999, 5, true],
            'below but display rounds to eighty' => [3.9999, 5, true],
            'above before rounding' => [4.0001, 5, false],
            'expected must not be rounded before comparison' => [1.6, 2.0001, true],
            'above with fractional expected' => [1.6, 1.9999, false],
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

    #[DataProvider('invalidExpectedLevels')]
    public function test_invalid_expected_level_has_no_ratio_or_classification(?float $expected): void
    {
        foreach ([AssessmentMethodResolver::FACULTY, AssessmentMethodResolver::STANDARD] as $method) {
            $result = (new CompetencyGapCalculator())->calculate($method, 3, $expected);
            $this->assertNull($result['expectedLevel']);
            $this->assertNull($result['gap']);
            $this->assertNull($result['percentage']);
            $this->assertFalse($result['requiresIdp']);
        }
    }

    public static function invalidExpectedLevels(): array
    {
        return ['missing' => [null], 'zero' => [0.0], 'negative' => [-1.0],
            'not a number' => [NAN], 'positive infinity' => [INF], 'negative infinity' => [-INF]];
    }

    public function test_display_rounding_does_not_change_the_faculty_decision(): void
    {
        $result = (new CompetencyGapCalculator())->calculate('faculty', 3.9999, 5);
        $this->assertSame(4.0, $result['actualLevel']);
        $this->assertSame(80.0, $result['percentage']);
        $this->assertTrue($result['requiresIdp']);
    }
}

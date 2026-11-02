<?php

namespace Tests\Unit;

use App\Services\FacultyAssessmentScorer;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FacultyAssessmentScorerTest extends TestCase
{
    private FacultyAssessmentScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = new FacultyAssessmentScorer();
    }

    #[DataProvider('scoreCases')]
    public function test_it_calculates_the_highest_completed_faculty_level(array $keys, int $expected): void
    {
        $this->assertSame($expected, $this->scorer->score(10, $this->levels(), $keys));
    }

    public static function scoreCases(): array
    {
        return [
            'nothing checked is level zero' => [[], 0],
            'one level-one behavior is level one' => [['10:101:0'], 1],
            'all level-one behaviors is level two' => [['10:101:0', '10:101:1', '10:101:2'], 2],
            'one level-three behavior after level two is level three' => [['10:101:0', '10:101:1', '10:101:2', '10:103:0'], 3],
            'all level-three behaviors is level four' => [['10:101:0', '10:101:1', '10:101:2', '10:103:0', '10:103:1', '10:103:2'], 4],
            'one level-five behavior remains level four' => [['10:101:0', '10:101:1', '10:101:2', '10:103:0', '10:103:1', '10:103:2', '10:105:0'], 4],
            'two level-five behaviors after level four is level five' => [['10:101:0', '10:101:1', '10:101:2', '10:103:0', '10:103:1', '10:103:2', '10:105:0', '10:105:2'], 5],
            'higher behaviors do not replace level-one requirement' => [['10:103:0', '10:103:1', '10:103:2', '10:105:0', '10:105:1'], 0],
            'higher behaviors do not replace complete level three' => [['10:101:0', '10:101:1', '10:101:2', '10:103:0', '10:105:0', '10:105:1'], 3],
        ];
    }

    public function test_it_rejects_an_indicator_that_is_not_in_the_competency_definition(): void
    {
        $this->expectException(ValidationException::class);

        $this->scorer->score(10, $this->levels(), ['10:103:99']);
    }

    public function test_it_rejects_an_incomplete_faculty_definition(): void
    {
        $this->expectException(ValidationException::class);

        $this->scorer->score(10, [
            ['id' => 101, 'level' => 1, 'indicatorCount' => 3],
            ['id' => 103, 'level' => 3, 'indicatorCount' => 3],
            ['id' => 105, 'level' => 5, 'indicatorCount' => 1],
        ], []);
    }

    private function levels(): array
    {
        return [
            ['id' => 101, 'level' => 1, 'indicatorCount' => 3],
            ['id' => 103, 'level' => 3, 'indicatorCount' => 3],
            ['id' => 105, 'level' => 5, 'indicatorCount' => 3],
        ];
    }
}

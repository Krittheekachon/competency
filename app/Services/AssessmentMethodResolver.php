<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AssessmentMethodResolver
{
    public const STANDARD = 'standard';

    public const FACULTY = 'faculty';

    public const CURRENT_VERSIONS = [
        self::STANDARD => 1,
        self::FACULTY => 1,
    ];

    public static function allowed(): array
    {
        return array_keys(self::CURRENT_VERSIONS);
    }

    public static function label(string $method): string
    {
        return match ($method) {
            self::FACULTY => 'วิธีคณะวิศวกรรมศาสตร์',
            default => 'วิธีประเมินเดิม',
        };
    }

    /** Name of the competency dictionary format that belongs to a method. */
    public static function formatLabel(string $method): string
    {
        return match ($method) {
            self::FACULTY => 'รูปแบบคณะวิศวกรรมศาสตร์',
            default => 'รูปแบบมหาวิทยาลัย',
        };
    }

    public function forRoundId(int $roundId): array
    {
        $round = DB::table('assessment_rounds')
            ->where('id', $roundId)
            ->first(['assessment_method', 'assessment_method_version']);

        $method = in_array($round?->assessment_method, self::allowed(), true)
            ? $round->assessment_method
            : self::STANDARD;

        return [
            'method' => $method,
            'version' => (int) ($round?->assessment_method_version ?: self::CURRENT_VERSIONS[$method]),
            'label' => self::label($method),
        ];
    }

    public function forActiveRound(): array
    {
        $roundId = (int) DB::table('assessment_rounds')
            ->where('is_active', true)
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->value('id');

        return $roundId > 0
            ? $this->forRoundId($roundId)
            : [
                'method' => self::STANDARD,
                'version' => self::CURRENT_VERSIONS[self::STANDARD],
                'label' => self::label(self::STANDARD),
            ];
    }

    /**
     * Codes of the given competencies whose dictionary format differs from the round's method.
     *
     * @param  iterable<int>  $competencyIds
     * @return list<string>
     */
    public function mismatchedCompetencyCodes(int $roundId, iterable $competencyIds): array
    {
        return DB::table('competencies')
            ->whereIn('id', collect($competencyIds)->map(fn ($id): int => (int) $id)->all())
            ->where('assessment_method', '!=', $this->forRoundId($roundId)['method'])
            ->orderBy('code')
            ->pluck('code')
            ->all();
    }

    public function isFaculty(?int $roundId): bool
    {
        if (! $roundId) {
            return false;
        }

        return $this->forRoundId($roundId)['method'] === self::FACULTY;
    }
}

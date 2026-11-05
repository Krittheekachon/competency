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

    public function isFaculty(?int $roundId): bool
    {
        if (! $roundId) {
            return false;
        }

        return $this->forRoundId($roundId)['method'] === self::FACULTY;
    }
}

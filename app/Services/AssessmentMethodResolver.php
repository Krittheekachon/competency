<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AssessmentMethodResolver
{
    public const STANDARD = 'standard';

    public const FACULTY = 'faculty';

    /**
     * A faculty round assesses these competency types in the university format (levels 1-5, 0.25 per behaviour).
     * Every other type follows the round's method.
     */
    public const UNIVERSITY_FORMAT_TYPES_IN_FACULTY_ROUND = ['MC'];

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
            default => 'รูปแบบตามกำหนดมหาวิทยาลัย',
        };
    }

    /** Name of the competency dictionary format that belongs to a method. */
    public static function formatLabel(string $method): string
    {
        return match ($method) {
            self::FACULTY => 'รูปแบบคณะวิศวกรรมศาสตร์',
            default => 'รูปแบบตามกำหนดมหาวิทยาลัย',
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
        $query = DB::table('competencies')
            ->whereIn('id', collect($competencyIds)->map(fn ($id): int => (int) $id)->all());
        $this->whereIncompatibleWithMethod($query, $this->forRoundId($roundId)['method']);

        return $query->orderBy('code')->pluck('code')->all();
    }

    /** Ids of competency types assessed in the university format inside a faculty round. */
    private function universityFormatTypeIds(): \Illuminate\Support\Collection
    {
        return DB::table('competency_types')
            ->whereIn(DB::raw('UPPER(code)'), self::UNIVERSITY_FORMAT_TYPES_IN_FACULTY_ROUND)
            ->pluck('id');
    }

    /** Restrict a query on `competencies` to those a round of $method may bind. */
    public function whereCompatibleWithMethod(Builder $query, string $method): Builder
    {
        if ($method !== self::FACULTY) {
            return $query->where('competencies.assessment_method', $method);
        }

        $mixedTypes = $this->universityFormatTypeIds();

        return $query->where(fn (Builder $inner) => $inner
            ->where('competencies.assessment_method', self::FACULTY)
            ->orWhere(fn (Builder $mixed) => $mixed
                ->where('competencies.assessment_method', self::STANDARD)
                ->whereIn('competencies.competency_type_id', $mixedTypes)));
    }

    /** Restrict a query on `competencies` to those a round of $method may NOT bind. */
    public function whereIncompatibleWithMethod(Builder $query, string $method): Builder
    {
        $query->where('competencies.assessment_method', '!=', $method);

        return $method === self::FACULTY
            ? $query->whereNotIn('competencies.competency_type_id', $this->universityFormatTypeIds())
            : $query;
    }

    /**
     * How one competency is assessed: the round's method, except university-format types in a faculty round.
     *
     * @param  iterable<int>  $competencyIds
     * @return array<int, string> method keyed by competency id
     */
    public function methodsForCompetencies(?int $roundId, iterable $competencyIds): array
    {
        $ids = collect($competencyIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        $roundMethod = $roundId ? $this->forRoundId($roundId)['method'] : self::STANDARD;
        if ($ids->isEmpty() || $roundMethod !== self::FACULTY) {
            return $ids->mapWithKeys(fn (int $id): array => [$id => $roundMethod])->all();
        }

        $mixedTypes = $this->universityFormatTypeIds()->map(fn ($id): int => (int) $id)->all();
        $rows = DB::table('competencies')->whereIn('id', $ids)->get(['id', 'assessment_method', 'competency_type_id'])->keyBy('id');

        return $ids->mapWithKeys(function (int $id) use ($rows, $mixedTypes): array {
            $row = $rows->get($id);
            $universityFormat = $row
                && $row->assessment_method === self::STANDARD
                && in_array((int) $row->competency_type_id, $mixedTypes, true);

            return [$id => $universityFormat ? self::STANDARD : self::FACULTY];
        })->all();
    }

    public function methodForCompetency(?int $roundId, int $competencyId): string
    {
        return $this->methodsForCompetencies($roundId, [$competencyId])[$competencyId]
            ?? ($roundId ? $this->forRoundId($roundId)['method'] : self::STANDARD);
    }

    /** True when this competency is assessed with the faculty rules (levels 1/3/5, 80% rule). */
    public function isFacultyCompetency(?int $roundId, int $competencyId): bool
    {
        return $this->methodForCompetency($roundId, $competencyId) === self::FACULTY;
    }

    public function isFaculty(?int $roundId): bool
    {
        if (! $roundId) {
            return false;
        }

        return $this->forRoundId($roundId)['method'] === self::FACULTY;
    }
}

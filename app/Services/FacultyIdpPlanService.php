<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Faculty-method IDP: one plan item per person and round covering every failed competency.
 *
 * The plan is an idp_items row (behavior_key = faculty-plan) anchored on one failed gap so
 * the existing item workflow, weights and progress tracking apply; idp_item_competency_gaps
 * lists every competency the plan covers.
 */
class FacultyIdpPlanService
{
    public const BEHAVIOR_KEY = 'faculty-plan';

    public function __construct(private AssessmentMethodResolver $assessmentMethods) {}

    public function isFacultyRound(int $roundId): bool
    {
        return $this->assessmentMethods->isFaculty($roundId);
    }

    /** Approved gaps of the user in the round that require IDP, ordered by competency code. */
    public function failedGaps(int $userId, int $roundId): Collection
    {
        return DB::table('competency_gaps')
            ->join('assessments', 'competency_gaps.assessment_id', '=', 'assessments.id')
            ->join('competencies', 'competency_gaps.competency_id', '=', 'competencies.id')
            ->where('assessments.user_id', $userId)
            ->where('assessments.assessment_round_id', $roundId)
            ->where('competency_gaps.requires_idp', true)
            ->where('competency_gaps.gap', '<', 0)
            ->whereIn('competency_gaps.status', ['approved', 'dean_approved'])
            ->orderBy('competencies.code')
            ->get([
                'competency_gaps.id',
                'competency_gaps.competency_id',
                'competency_gaps.expected_level',
                'assessments.assessment_round_id',
            ])
            ->keyBy('id');
    }

    public function syncCompetencyGaps(int $idpItemId, Collection $gapIds): void
    {
        DB::table('idp_item_competency_gaps')
            ->where('idp_item_id', $idpItemId)
            ->whereNotIn('competency_gap_id', $gapIds)
            ->delete();

        $now = now();
        DB::table('idp_item_competency_gaps')->insertOrIgnore($gapIds->map(fn ($gapId): array => [
            'idp_item_id' => $idpItemId,
            'competency_gap_id' => (int) $gapId,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all());
    }

    /**
     * Display labels for plan items, keyed by idp_item_id; items that are not faculty plans are absent.
     *
     * @return Collection<int, array{code: string, name: string, competencies: array<int, array{code: string, name: string}>}>
     */
    public function labelsForItems(Collection $itemIds): Collection
    {
        if ($itemIds->isEmpty()) {
            return collect();
        }

        return DB::table('idp_item_competency_gaps')
            ->join('competency_gaps', 'idp_item_competency_gaps.competency_gap_id', '=', 'competency_gaps.id')
            ->join('competencies', 'competency_gaps.competency_id', '=', 'competencies.id')
            ->whereIn('idp_item_competency_gaps.idp_item_id', $itemIds)
            ->orderBy('competencies.code')
            ->get(['idp_item_competency_gaps.idp_item_id', 'competencies.code', 'competencies.name'])
            ->groupBy('idp_item_id')
            ->map(fn (Collection $rows): array => [
                'code' => 'IDP',
                'name' => 'แผนพัฒนารายบุคคล · '.$rows->pluck('code')->implode(', '),
                'competencies' => $rows->map(fn (object $row): array => [
                    'code' => $row->code,
                    'name' => $row->name,
                ])->values()->all(),
            ]);
    }
}

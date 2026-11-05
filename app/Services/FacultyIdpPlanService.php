<?php

namespace App\Services;

use App\Models\User;
use App\Models\Competency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    /** Required topics come from the round/position assignment, not merely existing approved rows. */
    public function readiness(int $userId, int $roundId): array
    {
        $user = app(AssessmentRoundSnapshotService::class)
            ->userForRound(User::findOrFail($userId), $roundId);
        $titleId = $user->position_title_id ?: ($user->position_id
            ? DB::table('positions')->where('id', $user->position_id)->value('position_title_id')
            : null);
        $required = DB::table('position_competencies as pc')
            ->join('competencies as c', 'c.id', '=', 'pc.competency_id')
            ->join('competency_types as t', 't.id', '=', 'c.competency_type_id')
            ->where('pc.assessment_round_id', $roundId)
            ->when(
                $titleId,
                fn ($q) => $q->where('pc.position_title_id', $titleId),
                fn ($q) => $q->where('pc.position_id', $user->position_id ?: 0),
            )
            ->get(['c.id', 'c.code', 'c.name', 't.code as type'])
            ->unique('id');
        $pending = [];
        if ($required->isEmpty()) {
            $pending[] = 'ยังไม่ได้กำหนดสมรรถนะประจำตำแหน่งในรอบนี้';
        }
        $requiresSnapshot = (bool) DB::table('assessment_rounds')
            ->where('id', $roundId)->value('snapshot_required');
        $hasSnapshot = ! $requiresSnapshot || DB::table('user_round_profiles')
            ->where('user_id', $userId)->where('assessment_round_id', $roundId)->exists();
        if (! $hasSnapshot) {
            $pending[] = 'ยังไม่มีข้อมูลตำแหน่งและสายผู้ประเมินประจำรอบ';
        }
        $fcCount = app(FcSelectionRuleResolver::class)->requiredCount((int) $titleId, $user->position_id, $roundId);
        if ($fcCount > 0) {
            $selection = DB::table('fc_topic_selections')
                ->where('user_id', $userId)
                ->where('assessment_round_id', $roundId)
                ->where('position_title_id', $titleId)
                ->orderByDesc('id')->first();
            $selected = $selection && $selection->status === 'approved'
                ? DB::table('fc_topic_selection_items')
                    ->where('fc_topic_selection_id', $selection->id)->pluck('competency_id')
                : collect();
            $availableFc = $required->filter(fn ($r) => str_starts_with($r->type, 'FC'))->pluck('id');
            if ($selected->count() !== $fcCount || $selected->diff($availableFc)->isNotEmpty()) {
                $pending[] = 'หัวข้อ FC ที่ต้องเลือกยังไม่ได้รับอนุมัติครบ';
            }
            $required = $required->filter(fn ($r) => ! str_starts_with($r->type, 'FC')
                || $selected->contains($r->id));
        }
        $results = DB::table('assessments as a')
            ->leftJoin('competency_gaps as g', function ($q) {
                $q->on('g.assessment_id', '=', 'a.id')->on('g.competency_id', '=', 'a.competency_id');
            })
            ->where('a.user_id', $userId)->where('a.assessment_round_id', $roundId)
            ->get(['a.competency_id', 'a.status', 'g.status as gap_status', 'g.expected_level', 'g.actual_level'])
            ->keyBy('competency_id');
        foreach ($required as $topic) {
            $result = $results->get($topic->id);
            if (! $result || ! in_array($result->status, ['approved', 'dean_approved'], true)
                || ! in_array($result->gap_status, ['approved', 'dean_approved'], true)
                || ! ($result->expected_level > 0) || $result->actual_level === null) {
                $pending[] = $topic->name;
            }
        }
        $lockedItems = DB::table('idp_items as i')
            ->join('idps as p', 'p.id', '=', 'i.idp_id')
            ->where('p.user_id', $userId)->where('p.assessment_round_id', $roundId)
            ->where('i.behavior_key', self::BEHAVIOR_KEY)
            ->whereNotIn('i.status', ['draft', 'revision_required'])->pluck('i.id');
        $missing = collect();
        if ($lockedItems->isNotEmpty()) {
            // Load coverage and failed gaps once, rather than querying each gap's code.
            $coverage = DB::table('idp_item_competency_gaps')
                ->whereIn('idp_item_id', $lockedItems)
                ->get(['idp_item_id', 'competency_gap_id'])->groupBy('idp_item_id');
            $failedGaps = $this->failedGaps($userId, $roundId);
            foreach ($lockedItems as $itemId) {
                $covered = ($coverage[$itemId] ?? collect())->pluck('competency_gap_id');
                $missing = $missing->merge($failedGaps->whereNotIn('id', $covered)->map(fn ($gap) => [
                    'itemId' => (int) $itemId,
                    'gapId' => (int) $gap->id,
                    'competencyId' => (int) $gap->competency_id,
                    'code' => $gap->code,
                    'name' => $gap->name,
                ]));
            }
        }
        $message = $missing->isNotEmpty()
            ? 'แผนที่ส่งแล้วมีสมรรถนะตกหล่น: '.$missing->pluck('name')->unique()->implode(', ').' กรุณาติดต่อ HR ตรวจสอบแผนเดิม ระบบจะไม่แก้แผนที่ส่งแล้วอัตโนมัติ'
            : ($pending !== [] ? 'ยังส่งแผนรวมไม่ได้ ผลประเมินหรือข้อมูลยังไม่ครบ: '.implode(', ', $pending) : '');
        return [
            'ready' => $pending === [] && $missing->isEmpty(),
            'message' => $message,
            'pending' => $pending,
            'missingGaps' => $missing->values()->all(),
            'requiredCompetencyIds' => $required->pluck('id')->values()->all(),
        ];
    }

    public function assertReadyToSubmit(int $userId, int $roundId): void
    {
        $state = $this->readiness($userId, $roundId);
        if (! $state['ready']) {
            throw ValidationException::withMessages(['items' => $state['message']]);
        }
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
            ->whereIn('assessments.status', ['approved', 'dean_approved'])
            ->orderBy('competencies.code')
            ->get([
                'competency_gaps.id',
                'competency_gaps.competency_id',
                'competencies.code',
                'competencies.name',
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
            ->get(['idp_item_competency_gaps.idp_item_id', DB::raw(Competency::DISPLAY_CODE_SQL.' as code'), 'competencies.name'])
            ->groupBy('idp_item_id')
            // The plan is named by its failed competencies' names; faculty competencies carry no code.
            ->map(fn (Collection $rows): array => [
                'code' => '',
                'name' => 'แผนพัฒนารายบุคคล · '.$rows->pluck('name')->implode(', '),
                'competencies' => $rows->map(fn (object $row): array => [
                    'code' => $row->code,
                    'name' => $row->name,
                ])->values()->all(),
            ]);
    }
}

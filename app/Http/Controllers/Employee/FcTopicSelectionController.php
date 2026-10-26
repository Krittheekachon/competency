<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\AssessmentRoundWindow;
use App\Services\AssessmentRoundSnapshotService;
use App\Services\PositionTitleResolver;
use App\Services\ReviewerChainResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FcTopicSelectionController extends Controller
{
    public function __construct(
        private ReviewerChainResolver $reviewerChainResolver,
        private AssessmentRoundWindow $assessmentRoundWindow,
        private AssessmentRoundSnapshotService $roundSnapshot,
        private PositionTitleResolver $positionTitles,
    )
    {
    }

    public function submit(Request $request): RedirectResponse
    {
        $user = $request->user();
        $roundId = $this->activeRoundId();
        $this->roundSnapshot->captureIfMissing($user, $roundId);
        $user = $this->roundSnapshot->userForRound($user, $roundId);
        $positionId = (int) ($user->position_id ?? 0);
        $positionTitleId = $this->positionTitles->forUser($user);

        if (! $positionTitleId) {
            throw ValidationException::withMessages([
                'position' => 'ยังไม่พบตำแหน่งของคุณ กรุณาให้ Admin ตรวจสอบข้อมูลก่อนเลือกหัวข้อ FC',
            ]);
        }

        $firstReviewerId = $this->firstReviewerId($user);

        if (! $firstReviewerId) {
            throw ValidationException::withMessages([
                'supervisor' => 'ยังไม่ได้กำหนดหัวหน้า 1 จึงไม่สามารถส่งอนุมัติหัวข้อ FC ได้',
            ]);
        }

        $data = $request->validate([
            'competency_ids' => ['required', 'array'],
            'competency_ids.*' => ['integer', 'exists:competencies,id'],
        ]);

        $requiredCount = $this->requiredFcCount($positionTitleId, $roundId);
        if ($requiredCount <= 0) {
            throw ValidationException::withMessages([
                'competency_ids' => 'ตำแหน่งนี้ยังไม่ได้กำหนดจำนวน FC ที่ต้องเลือก',
            ]);
        }

        $selectedIds = collect($data['competency_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($selectedIds->count() !== $requiredCount) {
            throw ValidationException::withMessages([
                'competency_ids' => "กรุณาเลือก FC ให้ครบ {$requiredCount} ข้อ",
            ]);
        }

        $availableIds = $this->availableFcCompetencyIds($positionTitleId, $roundId);
        $invalidIds = $selectedIds->diff($availableIds);

        if ($invalidIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'competency_ids' => 'เลือกได้เฉพาะ FC ที่ HR กำหนดไว้ให้ตำแหน่งนี้เท่านั้น',
            ]);
        }

        DB::transaction(function () use ($user, $positionId, $positionTitleId, $roundId, $selectedIds, $firstReviewerId): void {
            $now = now();
            $selectionId = DB::table('fc_topic_selections')->updateOrInsert(
                [
                    'user_id' => $user->id,
                    'position_title_id' => $positionTitleId,
                    'assessment_round_id' => $roundId,
                ],
                [
                    'position_id' => $positionId ?: null,
                    'status' => 'submitted',
                    'submitted_to' => $firstReviewerId,
                    'submitted_at' => $now,
                    'reviewed_by' => null,
                    'review_comment' => null,
                    'reviewed_at' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $selectionId = DB::table('fc_topic_selections')
                ->where('user_id', $user->id)
                ->where('position_title_id', $positionTitleId)
                ->where('assessment_round_id', $roundId)
                ->value('id');

            DB::table('fc_topic_selection_items')
                ->where('fc_topic_selection_id', $selectionId)
                ->delete();

            DB::table('fc_topic_selection_items')->insert($selectedIds->map(fn (int $competencyId) => [
                'fc_topic_selection_id' => $selectionId,
                'competency_id' => $competencyId,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });

        return back()->with('success', 'ส่งหัวข้อ FC ให้หัวหน้า 1 อนุมัติแล้ว');
    }

    private function requiredFcCount(int $positionTitleId, int $roundId): int
    {
        return (int) DB::table('position_fc_selection_rules')
            ->where('position_title_id', $positionTitleId)
            ->where('assessment_round_id', $roundId)
            ->value('required_fc_count');
    }

    private function firstReviewerId($user): ?int
    {
        return $this->reviewerChainResolver->firstReviewerId($user);
    }

    private function availableFcCompetencyIds(int $positionTitleId, int $roundId): \Illuminate\Support\Collection
    {
        return DB::table('position_competencies')
            ->join('competencies', 'position_competencies.competency_id', '=', 'competencies.id')
            ->join('competency_types', 'competencies.competency_type_id', '=', 'competency_types.id')
            ->where('position_competencies.position_title_id', $positionTitleId)
            ->where('position_competencies.assessment_round_id', $roundId)
            ->whereIn('competency_types.code', ['FC', 'FC1', 'FC2'])
            ->pluck('competencies.id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    private function activeRoundId(): int
    {
        return (int) $this->assessmentRoundWindow->assertSelfAssessmentOpen()->id;
    }
}

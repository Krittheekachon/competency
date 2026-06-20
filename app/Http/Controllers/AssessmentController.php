<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Services\ExpectedLevelResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AssessmentController extends Controller
{
    public function __construct(private ExpectedLevelResolver $expectedLevelResolver)
    {
    }

    public function save(Request $request)
    {
        $request->validate([
            'competency_id' => ['required', 'integer', 'exists:competencies,id'],
            'checked_indicators' => ['present', 'array'],
            'note' => ['nullable', 'string', 'max:2000'],
            'score' => ['required', 'numeric'],
        ]);

        $this->assertCanSelfAssess($request->user());

        $userId = auth()->id();

        $submittedStatus = $this->initialSubmittedStatusForUser($request->user());

        DB::transaction(function () use ($request, $userId, $submittedStatus): void {
            $competencyId = (int) $request->competency_id;
            $checkedIndicators = collect($request->checked_indicators)
                ->filter()
                ->filter(fn ($checked, string $key): bool => str_starts_with($key, $competencyId.':'))
                ->all();
            $existingAssessment = Assessment::where('user_id', $userId)
                ->where('competency_id', $competencyId)
                ->first();

            if ($existingAssessment && ! in_array($existingAssessment->status, ['draft', 'revision_required'], true)) {
                throw ValidationException::withMessages([
                    'assessment' => 'ผลการประเมินนี้ถูกส่งให้ผู้บังคับบัญชาแล้ว ไม่สามารถแก้ไขได้จนกว่าจะถูกส่งกลับมาแก้ไข',
                ]);
            }

            $assessmentAttributes = [
                'user_id' => $userId,
                'competency_id' => $competencyId,
            ];
            $assessmentValues = [
                'score' => $request->score,
                'note' => $request->note ?? '',
                'status' => $submittedStatus,
                'last_draft_saved_at' => now(),
                'self_submitted_at' => now(),
            ];

            if (Schema::hasColumn('assessments', 'assessment_round_id')) {
                $assessmentValues['assessment_round_id'] = $this->activeAssessmentRoundId();
            }

            $assessment = Assessment::updateOrCreate($assessmentAttributes, $assessmentValues);

            DB::table('assessment_indicator_results')
                ->where('assessment_id', $assessment->id)
                ->where('competency_id', $competencyId)
                ->delete();

            foreach (array_keys($checkedIndicators) as $key) {
                DB::table('assessment_indicator_results')->insert([
                    'assessment_id' => $assessment->id,
                    'competency_id' => $competencyId,
                    'indicator_key' => $key,
                    'is_checked' => true,
                    'checked_by' => $userId,
                    'checked_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $expectedLevel = $this->expectedLevelResolver->forUserCompetency(
                $request->user(),
                $competencyId
            );
            $actualLevel = round((float) $request->score, 2);
            $gap = $expectedLevel === null ? null : round($actualLevel - $expectedLevel, 2);

            DB::table('competency_gaps')->updateOrInsert(
                [
                    'assessment_id' => $assessment->id,
                    'competency_id' => $competencyId,
                ],
                [
                    'expected_level' => $expectedLevel,
                    'actual_level' => $actualLevel,
                    'gap' => $gap,
                    'requires_idp' => $gap !== null && $gap < 0,
                    'status' => $submittedStatus,
                    'updated_at' => now(),
                ]
            );
        });

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'บันทึกการประเมินเรียบร้อยแล้ว',
        ]);
    }

    public function load(Request $request)
    {
        $request->validate([
            'competency_id' => ['required', 'integer', 'exists:competencies,id'],
        ]);

        $assessment = Assessment::where('user_id', auth()->id())
            ->where('competency_id', $request->query('competency_id'))
            ->first();

        if (! $assessment) {
            return response()->json([
                'checked' => [],
                'note' => '',
                'score' => 0,
                'status' => 'draft',
                'locked' => false,
                'reject_comment' => '',
            ]);
        }

        $indicators = DB::table('assessment_indicator_results')
            ->where('assessment_id', $assessment->id)
            ->where('competency_id', $assessment->competency_id)
            ->where('is_checked', true)
            ->pluck('indicator_key')
            ->mapWithKeys(fn ($key) => [$key => true]);

        $gap = DB::table('competency_gaps')
            ->where('assessment_id', $assessment->id)
            ->where('competency_id', $assessment->competency_id)
            ->first();

        return response()->json([
            'checked' => $indicators,
            'note' => $assessment->note ?? '',
            'score' => $assessment->score ?? 0,
            'status' => $assessment->status ?? 'draft',
            'locked' => ! in_array($assessment->status ?? 'draft', ['draft', 'revision_required'], true),
            'reject_comment' => $gap?->reject_comment ?? '',
        ]);
    }

    public function approve(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'competency_id' => ['required', 'integer', 'exists:competencies,id'],
            'comment' => ['nullable', 'string'],
        ]);
        $comment = trim((string) ($data['comment'] ?? ''));

        $decision = $this->decisionContextForUser($request->user(), (int) $data['user_id']);

        DB::transaction(function () use ($data, $decision, $request, $comment): void {
            $assessmentIds = Assessment::where('user_id', $data['user_id'])
                ->where('competency_id', $data['competency_id'])
                ->pluck('id');
            $reviewerScoreId = $this->upsertReviewerScore(
                $assessmentIds,
                (int) $data['competency_id'],
                (int) $request->user()->id,
                $decision['review_step'],
                $comment,
                'approved'
            );

            Assessment::whereIn('id', $assessmentIds)
                ->where('competency_id', $data['competency_id'])
                ->where('status', $decision['expected_status'])
                ->update([
                    'status' => $decision['approved_status'],
                    $decision['submitted_at_column'] => now(),
                    'updated_at' => now(),
                ]);

            DB::table('competency_gaps')
                ->whereIn('assessment_id', $assessmentIds)
                ->where('competency_id', $data['competency_id'])
                ->where('status', $decision['expected_status'])
                ->update([
                    'status' => $decision['approved_status'],
                    'supervisor_2_score_id' => $reviewerScoreId,
                    'rejected_by' => null,
                    'reject_comment' => null,
                    'decided_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        return back();
    }

    public function reject(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'competency_id' => ['required', 'integer', 'exists:competencies,id'],
            'comment' => ['required', 'string', 'min:1'],
        ]);
        $comment = trim((string) $data['comment']);
        if ($comment === '') {
            throw ValidationException::withMessages([
                'comment' => 'กรุณากรอก Comment ก่อนส่งกลับแก้ไข',
            ]);
        }

        $decision = $this->decisionContextForUser($request->user(), (int) $data['user_id']);

        DB::transaction(function () use ($data, $decision, $request, $comment): void {
            $assessmentIds = Assessment::where('user_id', $data['user_id'])
                ->where('competency_id', $data['competency_id'])
                ->pluck('id');
            $reviewerScoreId = $this->upsertReviewerScore(
                $assessmentIds,
                (int) $data['competency_id'],
                (int) $request->user()->id,
                $decision['review_step'],
                $comment,
                'rejected'
            );

            Assessment::whereIn('id', $assessmentIds)
                ->where('competency_id', $data['competency_id'])
                ->where('status', $decision['expected_status'])
                ->update([
                    'status' => 'revision_required',
                    'updated_at' => now(),
                ]);

            DB::table('competency_gaps')
                ->whereIn('assessment_id', $assessmentIds)
                ->where('competency_id', $data['competency_id'])
                ->where('status', $decision['expected_status'])
                ->update([
                    'status' => 'revision_required',
                    'supervisor_2_score_id' => $reviewerScoreId,
                    'rejected_by' => $request->user()->id,
                    'reject_comment' => $comment,
                    'decided_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        return back();
    }

    private function assertCanSelfAssess($user): void
    {
        $roleKey = $this->normalizeRoleKey(
            $user->relationLoaded('role')
                ? ($user->role?->key ?: '')
                : (DB::table('roles')->where('id', $user->role_id)->value('key') ?: '')
        );

        if (in_array($roleKey, ['admin', 'dean'], true)) {
            return;
        }

        $hasAssignedEvaluator = collect([
            $user->supervisor_id_1,
            $user->supervisor_id_2,
            $user->supervisor_id_3,
        ])->contains(fn ($id) => filled($id));

        if (! $hasAssignedEvaluator) {
            throw ValidationException::withMessages([
                'assessment' => 'ยังไม่สามารถประเมินตนเองได้ กรุณาให้ Admin กำหนดผู้ประเมินก่อน',
            ]);
        }
    }

    private function activeAssessmentRoundId(): int
    {
        $existingId = DB::table('assessment_rounds')
            ->where('is_active', true)
            ->orderByDesc('id')
            ->value('id')
            ?: DB::table('assessment_rounds')
                ->orderByDesc('year')
                ->orderByDesc('id')
                ->value('id');

        if ($existingId) {
            return (int) $existingId;
        }

        return DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบประเมิน',
            'year' => (int) now()->format('Y'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function normalizeRoleKey(string $roleKey): string
    {
        return match ($roleKey) {
            'manager' => 'dean',
            'manager_dept' => 'dept_head',
            default => $roleKey,
        };
    }

    private function decisionContextForUser($reviewer, int $userId): array
    {
        $target = DB::table('users')->where('id', $userId)->first();

        if (! $target) {
            throw ValidationException::withMessages([
                'assessment' => 'คุณไม่มีสิทธิ์อนุมัติผลการประเมินของบุคลากรคนนี้',
            ]);
        }

        $reviewStep = $this->reviewStepForReviewer($target, (int) $reviewer->id);

        if (! $reviewStep) {
            throw ValidationException::withMessages([
                'assessment' => 'คุณไม่มีสิทธิ์อนุมัติผลการประเมินของบุคลากรคนนี้',
            ]);
        }

        return [
            'expected_status' => $this->pendingStatusForStep($reviewStep),
            'approved_status' => $this->nextStatusAfterStep($target, $reviewStep),
            'submitted_at_column' => $this->submittedAtColumnForStep($reviewStep),
            'review_step' => $reviewStep,
        ];
    }

    private function upsertReviewerScore($assessmentIds, int $competencyId, int $reviewerId, int $reviewStep, string $comment, string $status): ?int
    {
        $scoreId = null;
        $now = now();

        foreach ($assessmentIds as $assessmentId) {
            DB::table('scores')->updateOrInsert(
                [
                    'assessment_id' => $assessmentId,
                    'competency_id' => $competencyId,
                    'assessor_id' => $reviewerId,
                ],
                [
                    'assessor_role' => 'supervisor_'.$reviewStep,
                    'comment' => $comment === '' ? null : $comment,
                    'status' => $status,
                    'submitted_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $scoreId ??= (int) DB::table('scores')
                ->where('assessment_id', $assessmentId)
                ->where('competency_id', $competencyId)
                ->where('assessor_id', $reviewerId)
                ->value('id');
        }

        return $scoreId;
    }

    private function initialSubmittedStatusForUser($user): string
    {
        foreach ([1, 2, 3] as $step) {
            if (filled($user->{'supervisor_id_'.$step})) {
                return $this->pendingStatusForStep($step);
            }
        }

        throw ValidationException::withMessages([
            'assessment' => 'ยังไม่สามารถประเมินตนเองได้ กรุณาให้ Admin กำหนดผู้ประเมินก่อน',
        ]);
    }

    private function reviewStepForReviewer($target, int $reviewerId): ?int
    {
        foreach ([1, 2, 3] as $step) {
            if ((int) ($target->{'supervisor_id_'.$step} ?? 0) === $reviewerId) {
                return $step;
            }
        }

        return null;
    }

    private function pendingStatusForStep(int $step): string
    {
        return match ($step) {
            1 => 'self_submitted',
            2 => 'unit_evaluated',
            3 => 'dept_evaluated',
            default => throw ValidationException::withMessages([
                'assessment' => 'ลำดับผู้ประเมินไม่ถูกต้อง',
            ]),
        };
    }

    private function nextStatusAfterStep($target, int $currentStep): string
    {
        for ($step = $currentStep + 1; $step <= 3; $step++) {
            if (filled($target->{'supervisor_id_'.$step} ?? null)) {
                return $this->pendingStatusForStep($step);
            }
        }

        return 'approved';
    }

    private function submittedAtColumnForStep(int $step): string
    {
        return match ($step) {
            1 => 'supervisor_1_submitted_at',
            2 => 'supervisor_2_submitted_at',
            3 => 'dean_approved_at',
            default => 'updated_at',
        };
    }
}

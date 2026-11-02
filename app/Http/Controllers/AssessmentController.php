<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\User;
use App\Services\AssessmentRoundWindow;
use App\Services\AssessmentRoundSnapshotService;
use App\Services\AssessmentLevelEvidenceService;
use App\Services\AssessmentMethodResolver;
use App\Services\CompetencyGapCalculator;
use App\Services\ExpectedLevelResolver;
use App\Services\FacultyAssessmentScorer;
use App\Services\FacultyAssessmentPermissionResolver;
use App\Services\FcSelectionRuleResolver;
use App\Services\NotificationService;
use App\Services\PositionTitleResolver;
use App\Services\ReviewerChainResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AssessmentController extends Controller
{
    public function __construct(
        private ExpectedLevelResolver $expectedLevelResolver,
        private NotificationService $notifications,
        private ReviewerChainResolver $reviewerChainResolver,
        private AssessmentRoundWindow $assessmentRoundWindow,
        private AssessmentRoundSnapshotService $roundSnapshot,
        private \App\Services\AssessmentReadinessService $readiness,
        private PositionTitleResolver $positionTitles,
        private AssessmentMethodResolver $assessmentMethods,
        private FacultyAssessmentScorer $facultyScorer,
        private FacultyAssessmentPermissionResolver $facultyPermissions,
        private CompetencyGapCalculator $gapCalculator,
        private AssessmentLevelEvidenceService $levelEvidences,
        private FcSelectionRuleResolver $fcSelectionRules,
    )
    {
    }

    public function draft(Request $request)
    {
        $data = $this->validatedAssessmentPayload($request);
        $round = $this->assessmentRoundWindow->assertSelfAssessmentOpen();
        $this->assertCanSelfAssess($request->user());
        $this->prepareUserRoundSnapshot($request->user(), (int) $round->id);
        $this->assertFcTopicsApprovedForAssessment($request->user(), (int) $data['competency_id']);

        $savedAt = null;
        $roundId = $this->activeAssessmentRoundId();
        DB::transaction(function () use ($request, $data, &$savedAt): void {
            $savedAt = $this->persistSelfAssessment($request, $data, false);
        });

        $status = DB::table('assessments')
            ->leftJoin('competency_gaps', function ($join) {
                $join->on('competency_gaps.assessment_id', '=', 'assessments.id')
                    ->on('competency_gaps.competency_id', '=', 'assessments.competency_id');
            })
            ->where('assessments.user_id', $request->user()->id)
            ->where('assessments.competency_id', $data['competency_id'])
            ->where('assessments.assessment_round_id', $roundId)
            ->value(DB::raw('COALESCE(competency_gaps.status, assessments.status)'));

        return response()->json([
            'status' => $status ?: 'draft',
            'lastDraftSavedAt' => $savedAt?->toISOString(),
            'message' => 'บันทึกฉบับร่างแล้ว',
        ]);
    }

    public function save(Request $request)
    {
        $data = $this->validatedAssessmentPayload($request);
        $round = $this->assessmentRoundWindow->assertSelfAssessmentOpen();
        $this->assertCanSelfAssess($request->user());
        $this->prepareUserRoundSnapshot($request->user(), (int) $round->id);
        $this->assertFcTopicsApprovedForAssessment($request->user(), (int) $data['competency_id']);

        DB::transaction(function () use ($request, $data): void {
            $this->persistSelfAssessment($request, $data, true);
        });

        $this->notifications->notifyFirstReviewerOnSubmit(
            $request->user(),
            $this->competencyName((int) $data['competency_id']),
        );

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

        $roundId = $this->activeAssessmentRoundId();
        $assessment = Assessment::where('user_id', auth()->id())
            ->where('competency_id', $request->query('competency_id'))
            ->where('assessment_round_id', $roundId)
            ->first();

        if (! $assessment) {
            return response()->json([
                'evidences' => [],
                'checked' => [],
                'note' => '',
                'score' => 0,
                'status' => 'draft',
                'gapStatus' => null,
                'lastDraftSavedAt' => null,
                'locked' => false,
                'reject_comment' => '',
                'reject_reviewer_name' => '',
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
        $gapStatus = $gap?->status;
        $status = $gapStatus ?? $assessment->status ?? 'draft';
        $rejectReviewer = $gap?->rejected_by
            ? User::query()->find($gap->rejected_by)
            : null;

        return response()->json([
            'evidences' => $this->levelEvidences->payloadsFor((int) $assessment->id, (int) $assessment->competency_id),
            'checked' => $indicators,
            'note' => $assessment->note ?? '',
            'score' => $assessment->score ?? 0,
            'status' => $status,
            'gapStatus' => $gapStatus,
            'lastDraftSavedAt' => $this->isoTimestamp($assessment->last_draft_saved_at),
            'locked' => ! in_array($status, ['draft', 'revision_required'], true),
            'reject_comment' => $gap?->reject_comment ?? '',
            'reject_reviewer_name' => $rejectReviewer
                ? trim(($rejectReviewer->title ?: '').($rejectReviewer->name ?: ''))
                : '',
        ]);
    }

    public function approve(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'competency_id' => ['required', 'integer', 'exists:competencies,id'],
            'comment' => ['nullable', 'string'],
            'checked' => ['nullable', 'array'],
            'checked.*' => ['string'],
        ]);
        $this->assessmentRoundWindow->assertSupervisorAssessmentOpen();
        $comment = trim((string) ($data['comment'] ?? ''));

        $decision = $this->decisionContextForUser($request->user(), (int) $data['user_id']);
        $employee = User::findOrFail((int) $data['user_id']);
        $employee->setAttribute('assessment_round_id', $this->activeAssessmentRoundId());
        $competencyName = $this->competencyName((int) ($data['competency_id'] ?? 0));
        $competencyType = $this->competencyTypeCode((int) $data['competency_id']);
        $reviewerCanScore = $this->assessmentMethods->isFaculty($this->activeAssessmentRoundId())
            && $this->facultyPermissions->canScore($request->user(), $employee, $competencyType);
        $evidenceRequested = false;

        if ($reviewerCanScore && ! array_key_exists('checked', $data)) {
            throw ValidationException::withMessages([
                'checked' => 'กรุณาตรวจสอบพฤติกรรมบ่งชี้ก่อนอนุมัติคะแนน',
            ]);
        }

        DB::transaction(function () use ($data, $decision, $request, $comment, $employee, $reviewerCanScore, &$evidenceRequested): void {
            $assessmentIds = Assessment::where('user_id', $data['user_id'])
                ->where('competency_id', $data['competency_id'])
                ->where('assessment_round_id', $this->activeAssessmentRoundId())
                ->where('status', $decision['expected_status'])
                ->pluck('id');

            if ($assessmentIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'assessment' => 'ผลการประเมินยังไม่อยู่ในขั้นตอนที่คุณสามารถอนุมัติได้',
                ]);
            }

            $reviewerScore = null;
            if ($reviewerCanScore) {
                $checked = array_values(array_unique($data['checked'] ?? []));
                $reviewerScore = $this->facultyScorer->scoreForCompetency(
                    (int) $data['competency_id'],
                    $checked,
                );

                foreach ($assessmentIds as $assessmentId) {
                    $this->replaceReviewerIndicatorResults(
                        (int) $assessmentId,
                        (int) $data['competency_id'],
                        (int) $request->user()->id,
                        (int) $decision['review_step'],
                        $checked,
                    );
                }

                $evidenceRequested = $reviewerScore >= AssessmentLevelEvidenceService::FACULTY_EVIDENCE_LEVEL
                    && $assessmentIds->contains(fn ($assessmentId): bool => ! $this->levelEvidences->existsFor(
                        (int) $assessmentId,
                        (int) $data['competency_id'],
                    ));
            }

            if ($evidenceRequested) {
                $this->requestLevelEvidence($assessmentIds, $data, $decision, $request->user()->id, $comment, $reviewerScore);

                return;
            }

            $reviewerScoreId = $this->upsertReviewerScore(
                $assessmentIds,
                (int) $data['competency_id'],
                (int) $request->user()->id,
                $decision['review_step'],
                $comment,
                'approved',
                $reviewerScore,
            );

            $this->ensureCompetencyGapsForAssessments(
                $assessmentIds,
                $employee,
                (int) $data['competency_id'],
                $decision['expected_status']
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

        if ($evidenceRequested) {
            $this->notifications->notifyEmployeeStatusUpdate($employee, 'evidence_required', $comment);

            return back();
        }

        $this->notifications->notifyEmployeeStatusUpdate($employee, $decision['approved_status']);

        if ($decision['approved_status'] !== 'approved') {
            $this->notifications->notifyNextReviewerForAssessment(
                $employee,
                $competencyName,
                $decision['approved_status'],
            );
        }

        return back();
    }

    public function reject(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'competency_id' => ['required', 'integer', 'exists:competencies,id'],
            'comment' => ['required', 'string', 'min:1'],
        ]);
        $this->assessmentRoundWindow->assertSupervisorAssessmentOpen();
        $comment = trim((string) $data['comment']);
        if ($comment === '') {
            throw ValidationException::withMessages([
                'comment' => 'กรุณากรอก Comment ก่อนส่งกลับแก้ไข',
            ]);
        }

        $decision = $this->decisionContextForUser($request->user(), (int) $data['user_id']);
        $employee = User::findOrFail((int) $data['user_id']);
        $employee->setAttribute('assessment_round_id', $this->activeAssessmentRoundId());
        $returnStatus = $this->returnStatusForRejection($employee, (int) $data['competency_id'], (int) $decision['review_step']);

        DB::transaction(function () use ($data, $decision, $request, $comment, $returnStatus): void {
            $assessmentIds = Assessment::where('user_id', $data['user_id'])
                ->where('competency_id', $data['competency_id'])
                ->where('assessment_round_id', $this->activeAssessmentRoundId())
                ->where('status', $decision['expected_status'])
                ->pluck('id');

            if ($assessmentIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'assessment' => 'ผลการประเมินยังไม่อยู่ในขั้นตอนที่คุณสามารถปฏิเสธได้',
                ]);
            }

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
                    'status' => $returnStatus,
                    'updated_at' => now(),
                ]);

            DB::table('competency_gaps')
                ->whereIn('assessment_id', $assessmentIds)
                ->where('competency_id', $data['competency_id'])
                ->where('status', $decision['expected_status'])
                ->update([
                    'status' => $returnStatus,
                    'supervisor_2_score_id' => $reviewerScoreId,
                    'rejected_by' => $request->user()->id,
                    'reject_comment' => $comment,
                    'decided_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        if ($returnStatus !== 'revision_required') {
            $this->notifications->notifyNextReviewerForAssessment(
                $employee,
                $this->competencyName((int) $data['competency_id']),
                $returnStatus,
            );

            return back();
        }

        $this->notifications->notifyEmployeeStatusUpdate($employee, 'revision_required', $comment);

        return back();
    }

    /**
     * Faculty rounds return a rejected result to the latest scorer before the rejecting step;
     * without one, the employee revises it as in the standard method.
     */
    private function returnStatusForRejection(User $employee, int $competencyId, int $reviewStep): string
    {
        if (! $this->assessmentMethods->isFaculty($this->activeAssessmentRoundId())) {
            return 'revision_required';
        }

        $scorerStep = $this->facultyPermissions->latestScorerStepBefore(
            $employee,
            $this->competencyTypeCode($competencyId),
            $this->reviewerChainResolver->stepsForUser($employee),
            $reviewStep,
        );

        return $scorerStep !== null
            ? $this->reviewerChainResolver->pendingStatusForStep($scorerStep)
            : 'revision_required';
    }

    private function requestLevelEvidence($assessmentIds, array $data, array $decision, int $reviewerId, string $comment, int $reviewerScore): void
    {
        $now = now();
        $competencyId = (int) $data['competency_id'];
        $scoreId = $this->upsertReviewerScore(
            $assessmentIds,
            $competencyId,
            $reviewerId,
            $decision['review_step'],
            $comment,
            'evidence_requested',
            $reviewerScore,
        );

        Assessment::whereIn('id', $assessmentIds)
            ->where('competency_id', $competencyId)
            ->where('status', $decision['expected_status'])
            ->update(['status' => 'evidence_required', 'updated_at' => $now]);

        DB::table('competency_gaps')
            ->whereIn('assessment_id', $assessmentIds)
            ->where('competency_id', $competencyId)
            ->where('status', $decision['expected_status'])
            ->update([
                'status' => 'evidence_required',
                'supervisor_2_score_id' => $scoreId,
                'rejected_by' => $reviewerId,
                'reject_comment' => $comment === '' ? null : $comment,
                'decided_at' => $now,
                'updated_at' => $now,
            ]);
    }

    private function assertCanSelfAssess($user): void
    {
        $roleKey = $this->normalizeRoleKey(
            $user->relationLoaded('role')
                ? ($user->role?->key ?: '')
                : (DB::table('roles')->where('id', $user->role_id)->value('key') ?: '')
        );

        $ssoPending = Schema::hasTable('sso_profile_changes') && DB::table('sso_profile_changes')
            ->where('user_id', $user->id)->whereIn('status', ['pending', 'restructure'])->exists();
        $roundId = $this->activeAssessmentRoundId();
        $positionId = (int) ($user->position_id ?? 0);
        $competencyIds = $roundId && $positionId
            ? DB::table('position_competencies')->where('assessment_round_id', $roundId)
                ->where('position_id', $positionId)->pluck('competency_id')->map(fn ($id): int => (int) $id)->all()
            : [];
        $expectedLevels = $competencyIds === [] ? [] : $this->expectedLevelResolver->forUserCompetencies($user, $competencyIds);
        $competencies = collect($competencyIds)->map(fn (int $id): array => ['expectedLevel' => $expectedLevels[$id] ?? null])->all();
        $roundUser = clone $user;
        $roundUser->setAttribute('assessment_round_id', $roundId);

        $roundRequiresSnapshot = (bool) DB::table('assessment_rounds')->where('id', $roundId)->value('snapshot_required');
        $hasRoundProfile = Schema::hasTable('user_round_profiles') && DB::table('user_round_profiles')
            ->where('assessment_round_id', $roundId)->where('user_id', $user->id)->exists();
        $hasExistingRoundWork = (Schema::hasTable('assessments') && DB::table('assessments')
            ->where('assessment_round_id', $roundId)->where('user_id', $user->id)->exists())
            || (Schema::hasTable('idps') && DB::table('idps')
                ->where('assessment_round_id', $roundId)->where('user_id', $user->id)->exists());

        if ($roundRequiresSnapshot && ! $hasRoundProfile && $hasExistingRoundWork) {
            throw ValidationException::withMessages([
                'assessment' => 'รอบการประเมินนี้มีข้อมูลเดิมแต่ไม่มี reviewer snapshot กรุณาติดต่อ Admin ก่อนดำเนินการต่อ',
            ]);
        }

        $reviewerSteps = $this->reviewerChainResolver->stepsForUser($roundUser, 'assessment', true);
        $status = $this->readiness->status(
            $this->readiness->organizationReady($user), $ssoPending, $competencies, $reviewerSteps,
            $roleKey !== 'admin',
        );

        if ($status['key'] !== 'ready') {
            throw ValidationException::withMessages(['assessment' => 'ข้อมูลโครงสร้าง สมรรถนะ Expected Level หรือผู้ประเมินยังไม่พร้อมสำหรับการประเมิน']);
        }
    }

    private function prepareUserRoundSnapshot(User $user, int $roundId): void
    {
        $this->roundSnapshot->captureIfMissing($user, $roundId);
        $snapshot = $this->roundSnapshot->userForRound($user, $roundId);
        $user->forceFill([
            'position_id' => $snapshot->position_id,
            'position_title_id' => $snapshot->position_title_id,
            'level_id' => $snapshot->level_id,
            'workline' => $snapshot->workline,
            'department' => $snapshot->department,
            'position' => $snapshot->position,
            'level' => $snapshot->level,
            'assessment_round_id' => $roundId,
        ]);
    }

    private function assertFcTopicsApprovedForAssessment($user, int $competencyId): void
    {
        $positionTitleId = $this->positionTitles->forUser($user);
        $roundId = $this->activeAssessmentRoundId();
        if (! $positionTitleId || ! Schema::hasTable('position_fc_selection_rules')) {
            return;
        }

        $requiredCount = $this->fcSelectionRules->requiredCount(
            (int) $positionTitleId,
            $user->position_id ? (int) $user->position_id : null,
            $roundId,
        );

        if ($requiredCount <= 0) {
            return;
        }

        $selection = Schema::hasTable('fc_topic_selections')
            ? DB::table('fc_topic_selections')
                ->where('user_id', $user->id)
                ->where('position_title_id', $positionTitleId)
                ->where('assessment_round_id', $roundId)
                ->first()
            : null;

        if (! $selection || $selection->status !== 'approved') {
            throw ValidationException::withMessages([
                'assessment' => 'ยังไม่สามารถประเมินได้ กรุณาเลือกหัวข้อ FC และรอหัวหน้า 1 อนุมัติก่อน',
            ]);
        }

        $isFcCompetency = DB::table('competencies')
            ->join('competency_types', 'competencies.competency_type_id', '=', 'competency_types.id')
            ->where('competencies.id', $competencyId)
            ->whereIn('competency_types.code', ['FC', 'FC1', 'FC2'])
            ->exists();

        if (! $isFcCompetency) {
            return;
        }

        $isSelectedFc = Schema::hasTable('fc_topic_selection_items') && DB::table('fc_topic_selection_items')
            ->where('fc_topic_selection_id', $selection->id)
            ->where('competency_id', $competencyId)
            ->exists();

        if (! $isSelectedFc) {
            throw ValidationException::withMessages([
                'assessment' => 'ประเมินได้เฉพาะหัวข้อ FC ที่หัวหน้า 1 อนุมัติแล้วเท่านั้น',
            ]);
        }
    }

    private function validatedAssessmentPayload(Request $request): array
    {
        return $request->validate([
            'competency_id' => ['required', 'integer', 'exists:competencies,id'],
            'checked_indicators' => ['present', 'array'],
            'note' => ['nullable', 'string', 'max:2000'],
            'score' => ['required', 'numeric'],
        ]);
    }

    private function persistSelfAssessment(Request $request, array $data, bool $submit): Carbon
    {
        $userId = auth()->id();
        $competencyId = (int) $data['competency_id'];
        $roundId = $this->activeAssessmentRoundId();
        $checkedIndicators = collect($data['checked_indicators'])
            ->filter()
            ->filter(fn ($checked, string $key): bool => str_starts_with($key, $competencyId.':'))
            ->all();
        $existingAssessment = Assessment::where('user_id', $userId)
            ->where('competency_id', $competencyId)
            ->where('assessment_round_id', $roundId)
            ->first();
        $existingGapStatus = $existingAssessment
            ? DB::table('competency_gaps')
                ->where('assessment_id', $existingAssessment->id)
                ->where('competency_id', $competencyId)
                ->value('status')
            : null;
        $existingStatus = $existingGapStatus ?? $existingAssessment?->status;

        if ($existingAssessment && ! in_array($existingStatus, ['draft', 'revision_required'], true)) {
            throw ValidationException::withMessages([
                'assessment' => 'ผลการประเมินนี้ถูกส่งให้หัวหน้างานแล้ว ไม่สามารถแก้ไขได้จนกว่าจะถูกส่งกลับมาแก้ไข',
            ]);
        }

        $savedAt = now();
        $submittedStatus = $submit
            ? $this->initialSubmittedStatusForUser($request->user())
            : ($existingStatus === 'revision_required' ? 'revision_required' : 'draft');
        $actualLevel = $this->assessmentMethods->isFaculty($roundId)
            ? $this->facultyScorer->scoreForCompetency($competencyId, array_keys($checkedIndicators))
            : round((float) $data['score'], 2);

        if ($submit
            && $this->assessmentMethods->isFaculty($roundId)
            && $actualLevel >= AssessmentLevelEvidenceService::FACULTY_EVIDENCE_LEVEL
            && ! ($existingAssessment && $this->levelEvidences->existsFor((int) $existingAssessment->id, $competencyId))) {
            throw ValidationException::withMessages([
                'evidence' => 'ผลประเมินระดับ 5 ต้องแนบหลักฐานอย่างน้อย 1 รายการก่อนส่ง',
            ]);
        }
        $assessmentAttributes = [
            'user_id' => $userId,
            'competency_id' => $competencyId,
            'assessment_round_id' => $roundId,
        ];
        $assessmentValues = [
            'score' => $actualLevel,
            'note' => $data['note'] ?? '',
            'status' => $submittedStatus,
            'last_draft_saved_at' => $savedAt,
            'self_submitted_at' => $submit ? $savedAt : $existingAssessment?->self_submitted_at,
        ];

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
                'checked_at' => $savedAt,
                'created_at' => $savedAt,
                'updated_at' => $savedAt,
            ]);
        }

        $expectedLevel = $this->expectedLevelResolver->forUserCompetency(
            $request->user(),
            $competencyId
        );
        $gapResult = $this->gapCalculator->calculate(
            $this->assessmentMethods->forRoundId($roundId)['method'],
            $actualLevel,
            $expectedLevel,
        );

        DB::table('competency_gaps')->updateOrInsert(
            [
                'assessment_id' => $assessment->id,
                'competency_id' => $competencyId,
            ],
            [
                'expected_level' => $expectedLevel,
                'actual_level' => $gapResult['actualLevel'],
                'gap' => $gapResult['gap'],
                'requires_idp' => $gapResult['requiresIdp'],
                'status' => $submittedStatus,
                'updated_at' => $savedAt,
            ]
        );

        return $savedAt;
    }

    private function ensureCompetencyGapsForAssessments($assessmentIds, User $employee, int $competencyId, string $status): void
    {
        $now = now();
        $expectedLevel = $this->expectedLevelResolver->forUserCompetency($employee, $competencyId);
        $roundId = $this->activeAssessmentRoundId();
        $assessmentMethod = $this->assessmentMethods->forRoundId($roundId)['method'];

        Assessment::query()
            ->whereIn('id', $assessmentIds)
            ->where('competency_id', $competencyId)
            ->where('status', $status)
            ->get(['id', 'score'])
            ->each(function (Assessment $assessment) use ($competencyId, $expectedLevel, $status, $now, $assessmentMethod): void {
                // assessments.score keeps the self score; faculty rounds use the latest authorized reviewer score.
                $reviewerScore = $assessmentMethod === AssessmentMethodResolver::FACULTY
                    ? $this->latestFacultyReviewerScore((int) $assessment->id, $competencyId)
                    : null;
                $actualLevel = round((float) ($reviewerScore ?? $assessment->score ?? 0), 2);
                $gapResult = $this->gapCalculator->calculate($assessmentMethod, $actualLevel, $expectedLevel);
                $attributes = [
                    'assessment_id' => $assessment->id,
                    'competency_id' => $competencyId,
                ];
                $values = [
                    'expected_level' => $expectedLevel,
                    'actual_level' => $gapResult['actualLevel'],
                    'gap' => $gapResult['gap'],
                    'requires_idp' => $gapResult['requiresIdp'],
                    'status' => $status,
                    'updated_at' => $now,
                ];

                if (DB::table('competency_gaps')->where($attributes)->exists()) {
                    DB::table('competency_gaps')->where($attributes)->update($values);

                    return;
                }

                DB::table('competency_gaps')->insert($attributes + $values + [
                    'created_at' => $now,
                ]);
            });
    }

    private function activeAssessmentRoundId(): int
    {
        return (int) $this->assessmentRoundWindow->activeRound()->id;
    }

    private function normalizeRoleKey(string $roleKey): string
    {
        return match ($roleKey) {
            'manager' => 'dean',
            'manager_dept' => 'dept_head',
            default => $roleKey,
        };
    }

    private function competencyName(int $competencyId): string
    {
        if ($competencyId <= 0) {
            return 'หลายสมรรถนะ';
        }

        return DB::table('competencies')
            ->where('id', $competencyId)
            ->value('name') ?? 'ไม่ระบุสมรรถนะ';
    }

    private function decisionContextForUser($reviewer, int $userId): array
    {
        $target = DB::table('users')->where('id', $userId)->first();

        if (! $target) {
            throw ValidationException::withMessages([
                'assessment' => 'คุณไม่มีสิทธิ์อนุมัติผลการประเมินของบุคลากรคนนี้',
            ]);
        }

        $target->assessment_round_id = $this->activeAssessmentRoundId();

        $reviewStep = $this->reviewStepForReviewer($target, (int) $reviewer->id);

        if (! $reviewStep) {
            throw ValidationException::withMessages([
                'assessment' => 'คุณไม่มีสิทธิ์อนุมัติผลการประเมินของบุคลากรคนนี้',
            ]);
        }

        return [
            'expected_status' => $this->reviewerChainResolver->pendingStatusForStep($reviewStep),
            'approved_status' => $this->nextStatusAfterStep($target, $reviewStep),
            'submitted_at_column' => $this->submittedAtColumnForStep($reviewStep),
            'review_step' => $reviewStep,
        ];
    }

    private function upsertReviewerScore($assessmentIds, int $competencyId, int $reviewerId, int $reviewStep, string $comment, string $status, ?int $score = null): ?int
    {
        $scoreId = null;
        $now = now();

        foreach ($assessmentIds as $assessmentId) {
            $values = [
                'assessor_role' => 'supervisor_'.$reviewStep,
                'comment' => $comment === '' ? null : $comment,
                'status' => $status,
                'submitted_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ];
            if ($score !== null) {
                $values['score'] = $score;
            }

            DB::table('scores')->updateOrInsert(
                [
                    'assessment_id' => $assessmentId,
                    'competency_id' => $competencyId,
                    'assessor_id' => $reviewerId,
                ],
                $values
            );

            $scoreId ??= (int) DB::table('scores')
                ->where('assessment_id', $assessmentId)
                ->where('competency_id', $competencyId)
                ->where('assessor_id', $reviewerId)
                ->value('id');
        }

        return $scoreId;
    }

    private function replaceReviewerIndicatorResults(
        int $assessmentId,
        int $competencyId,
        int $reviewerId,
        int $reviewStep,
        array $checked,
    ): void {
        DB::table('assessment_reviewer_indicator_results')
            ->where('assessment_id', $assessmentId)
            ->where('competency_id', $competencyId)
            ->where('reviewer_id', $reviewerId)
            ->delete();

        if ($checked === []) {
            return;
        }

        $now = now();
        DB::table('assessment_reviewer_indicator_results')->insert(array_map(
            fn (string $indicatorKey): array => [
                'assessment_id' => $assessmentId,
                'competency_id' => $competencyId,
                'reviewer_id' => $reviewerId,
                'review_step' => $reviewStep,
                'indicator_key' => $indicatorKey,
                'is_checked' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $checked,
        ));
    }

    private function latestFacultyReviewerScore(int $assessmentId, int $competencyId): ?float
    {
        $score = DB::table('scores')
            ->where('assessment_id', $assessmentId)
            ->where('competency_id', $competencyId)
            ->where('status', 'approved')
            ->whereNotNull('score')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->value('score');

        return $score === null ? null : (float) $score;
    }

    private function competencyTypeCode(int $competencyId): string
    {
        return (string) DB::table('competencies')
            ->join('competency_types', 'competencies.competency_type_id', '=', 'competency_types.id')
            ->where('competencies.id', $competencyId)
            ->value('competency_types.code');
    }

    private function initialSubmittedStatusForUser($user): string
    {
        $steps = $this->reviewerChainResolver->stepsForUser($user);

        if ($steps !== []) {
            return $this->reviewerChainResolver->pendingStatusForStep((int) $steps[0]['step']);
        }

        throw ValidationException::withMessages([
            'assessment' => 'ยังไม่สามารถประเมินตนเองได้ กรุณาให้ Admin กำหนดผู้ประเมินก่อน',
        ]);
    }

    private function reviewStepForReviewer($target, int $reviewerId): ?int
    {
        return $this->reviewerChainResolver->stepForReviewer($target, $reviewerId);
    }

    private function nextStatusAfterStep($target, int $currentStep): string
    {
        return $this->reviewerChainResolver->nextStatusAfterStep($target, $currentStep);
    }

    private function submittedAtColumnForStep(int $step): string
    {
        return $this->reviewerChainResolver->submittedAtColumnForStep($step);
    }

    private function isoTimestamp($value): ?string
    {
        if (! $value) {
            return null;
        }

        return $value instanceof Carbon
            ? $value->toISOString()
            : Carbon::parse($value)->toISOString();
    }
}

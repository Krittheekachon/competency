<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Services\ExpectedLevelResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentController extends Controller
{
    public function __construct(private ExpectedLevelResolver $expectedLevelResolver)
    {
    }

    public function draft(Request $request)
    {
        $data = $this->validatedAssessmentPayload($request);

        $this->assertCanSelfAssess($request->user());

        $savedAt = null;
        DB::transaction(function () use ($request, $data, &$savedAt): void {
            $savedAt = $this->persistSelfAssessment($request, $data, false);
        });

        return response()->json([
            'status' => 'draft',
            'lastDraftSavedAt' => $savedAt?->toISOString(),
            'message' => 'บันทึกฉบับร่างแล้ว',
        ]);
    }

    public function save(Request $request)
    {
        $data = $this->validatedAssessmentPayload($request);

        $this->assertCanSelfAssess($request->user());

        DB::transaction(function () use ($request, $data): void {
            $this->persistSelfAssessment($request, $data, true);
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
            return response()->json(['checked' => [], 'note' => '', 'score' => 0, 'status' => 'draft', 'locked' => false]);
        }

        $indicators = DB::table('assessment_evidences')
            ->where('assessment_id', $assessment->id)
            ->pluck('indicator_key')
            ->mapWithKeys(fn ($key) => [$key => true]);
        $gapStatus = DB::table('competency_gaps')
            ->where('assessment_id', $assessment->id)
            ->where('competency_id', $assessment->competency_id)
            ->value('status');
        $status = $gapStatus ?? $assessment->status ?? 'draft';

        return response()->json([
            'checked' => $indicators,
            'note' => $assessment->note ?? '',
            'score' => $assessment->score ?? 0,
            'status' => $status,
            'lastDraftSavedAt' => $assessment->last_draft_saved_at?->toISOString(),
            'locked' => ! in_array($status, ['draft', 'revision_required'], true),
        ]);
    }

    public function approve(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'competency_id' => ['nullable', 'integer', 'exists:competencies,id'],
        ]);

        $decision = $this->decisionContextForUser($request->user(), (int) $data['user_id']);

        DB::transaction(function () use ($data, $decision): void {
            $assessmentIds = Assessment::where('user_id', $data['user_id'])
                ->when($data['competency_id'] ?? null, fn ($query, $competencyId) => $query->where('competency_id', $competencyId))
                ->pluck('id');

            Assessment::whereIn('id', $assessmentIds)
                ->where('status', $decision['expected_status'])
                ->update([
                    'status' => $decision['approved_status'],
                    $decision['submitted_at_column'] => now(),
                    'updated_at' => now(),
                ]);

            DB::table('competency_gaps')
                ->whereIn('assessment_id', $assessmentIds)
                ->where('status', $decision['expected_status'])
                ->update([
                    'status' => $decision['approved_status'],
                    'updated_at' => now(),
                ]);
        });

        return back();
    }

    public function reject(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'competency_id' => ['nullable', 'integer', 'exists:competencies,id'],
        ]);

        $decision = $this->decisionContextForUser($request->user(), (int) $data['user_id']);

        DB::transaction(function () use ($data, $decision): void {
            $assessmentIds = Assessment::where('user_id', $data['user_id'])
                ->when($data['competency_id'] ?? null, fn ($query, $competencyId) => $query->where('competency_id', $competencyId))
                ->pluck('id');

            Assessment::whereIn('id', $assessmentIds)
                ->where('status', $decision['expected_status'])
                ->update([
                    'status' => 'revision_required',
                    'updated_at' => now(),
                ]);

            DB::table('competency_gaps')
                ->whereIn('assessment_id', $assessmentIds)
                ->where('status', $decision['expected_status'])
                ->update([
                    'status' => 'revision_required',
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

        if ($roleKey === 'admin') {
            return;
        }

        $hasAssignedEvaluator = collect([
            $user->supervisor_id_1,
            $user->supervisor_id_2,
            $user->supervisor_id_3,
        ])->contains(fn ($id) => filled($id));

        if (! $hasAssignedEvaluator) {
            throw ValidationException::withMessages([
                'assessment' => 'ยังไม่สามารถประเมินตนเองได้ กรุณาให้ Admin กำหนดผู้ประเมินอย่างน้อย 1 ลำดับก่อน',
            ]);
        }
    }

    private function validatedAssessmentPayload(Request $request): array
    {
        return $request->validate([
            'competency_id' => ['required', 'integer', 'exists:competencies,id'],
            'checked_indicators' => ['required', 'array'],
            'note' => ['nullable', 'string', 'max:2000'],
            'score' => ['required', 'numeric'],
        ]);
    }

    private function persistSelfAssessment(Request $request, array $data, bool $submit)
    {
        $userId = auth()->id();
        $competencyId = (int) $data['competency_id'];
        $checkedIndicators = collect($data['checked_indicators'])
            ->filter()
            ->filter(fn ($checked, string $key): bool => str_starts_with($key, $competencyId.':'))
            ->all();
        $existingAssessment = Assessment::where('user_id', $userId)
            ->where('competency_id', $competencyId)
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
                'assessment' => 'ผลการประเมินนี้ถูกส่งให้ผู้บังคับบัญชาแล้ว ไม่สามารถแก้ไขได้จนกว่าจะถูกส่งกลับมาแก้ไข',
            ]);
        }

        $savedAt = now();
        $assessment = Assessment::updateOrCreate(
            [
                'user_id' => $userId,
                'competency_id' => $competencyId,
            ],
            [
                'score' => $data['score'],
                'note' => $data['note'] ?? '',
                'status' => $submit ? 'self_submitted' : 'draft',
                'last_draft_saved_at' => $savedAt,
                'self_submitted_at' => $submit ? $savedAt : $existingAssessment?->self_submitted_at,
            ]
        );

        DB::table('assessment_evidences')
            ->where('assessment_id', $assessment->id)
            ->delete();

        foreach (array_keys($checkedIndicators) as $key) {
            DB::table('assessment_evidences')->insert([
                'assessment_id' => $assessment->id,
                'competency_id' => $competencyId,
                'uploaded_by' => $userId,
                'indicator_key' => $key,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $expectedLevel = $this->expectedLevelResolver->forUserCompetency(
            $request->user(),
            $competencyId
        );
        $actualLevel = round((float) $data['score'], 2);
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
                'status' => $submit ? 'self_submitted' : 'draft',
                'updated_at' => now(),
            ]
        );

        return $savedAt;
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
        $roleKey = $this->normalizeRoleKey(
            $reviewer->relationLoaded('role')
                ? ($reviewer->role?->key ?: '')
                : (DB::table('roles')->where('id', $reviewer->role_id)->value('key') ?: '')
        );

        if (! $target) {
            throw ValidationException::withMessages([
                'assessment' => 'คุณไม่มีสิทธิ์อนุมัติผลการประเมินของบุคลากรคนนี้',
            ]);
        }

        if ($roleKey === 'dean' && (int) $target->supervisor_id_1 === (int) $reviewer->id) {
            return [
                'expected_status' => 'self_submitted',
                'approved_status' => 'unit_evaluated',
                'submitted_at_column' => 'supervisor_1_submitted_at',
            ];
        }

        if ($roleKey === 'dean' && (int) $target->supervisor_id_2 === (int) $reviewer->id) {
            return [
                'expected_status' => $target->supervisor_id_1 ? 'unit_evaluated' : 'self_submitted',
                'approved_status' => 'dept_evaluated',
                'submitted_at_column' => 'supervisor_2_submitted_at',
            ];
        }

        if ($roleKey === 'supervisor' && (int) $target->supervisor_id_1 === (int) $reviewer->id) {
            return [
                'expected_status' => 'self_submitted',
                'approved_status' => 'unit_evaluated',
                'submitted_at_column' => 'supervisor_1_submitted_at',
            ];
        }

        if ($roleKey === 'dept_head' && (int) $target->supervisor_id_2 === (int) $reviewer->id) {
            return [
                'expected_status' => $target->supervisor_id_1 ? 'unit_evaluated' : 'self_submitted',
                'approved_status' => 'dept_evaluated',
                'submitted_at_column' => 'supervisor_2_submitted_at',
            ];
        }

        if ($roleKey === 'dean' && (int) $target->supervisor_id_3 === (int) $reviewer->id) {
            return [
                'expected_status' => $target->supervisor_id_2
                    ? 'dept_evaluated'
                    : ($target->supervisor_id_1 ? 'unit_evaluated' : 'self_submitted'),
                'approved_status' => 'dean_approved',
                'submitted_at_column' => 'dean_approved_at',
            ];
        }

        throw ValidationException::withMessages([
            'assessment' => 'คุณไม่มีสิทธิ์อนุมัติผลการประเมินของบุคลากรคนนี้',
        ]);
    }
}

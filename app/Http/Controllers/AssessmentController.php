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

    public function save(Request $request)
    {
        $request->validate([
            'competency_id' => ['required', 'integer', 'exists:competencies,id'],
            'checked_indicators' => ['required', 'array'],
            'note' => ['nullable', 'string', 'max:2000'],
            'score' => ['required', 'numeric'],
        ]);

        $this->assertCanSelfAssess($request->user());

        $userId = auth()->id();

        DB::transaction(function () use ($request, $userId): void {
            $competencyId = (int) $request->competency_id;
            $checkedIndicators = collect($request->checked_indicators)
                ->filter()
                ->filter(fn ($checked, string $key): bool => str_starts_with($key, $competencyId.':'))
                ->all();

            $assessment = Assessment::updateOrCreate(
                [
                    'user_id' => $userId,
                    'competency_id' => $competencyId,
                ],
                [
                    'score' => $request->score,
                    'note' => $request->note ?? '',
                    'status' => 'draft',
                    'last_draft_saved_at' => now(),
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
                    'status' => 'draft',
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
            return response()->json(['checked' => [], 'note' => '', 'score' => 0]);
        }

        $indicators = DB::table('assessment_evidences')
            ->where('assessment_id', $assessment->id)
            ->pluck('indicator_key')
            ->mapWithKeys(fn ($key) => [$key => true]);

        return response()->json([
            'checked' => $indicators,
            'note' => $assessment->note ?? '',
            'score' => $assessment->score ?? 0,
        ]);
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

        if ($roleKey === 'dept_head' && ! $user->supervisor_id_2) {
            throw ValidationException::withMessages([
                'assessment' => 'ยังไม่สามารถประเมินตนเองได้ กรุณาให้ Admin กำหนดผู้บังคับบัญชาก่อน',
            ]);
        }

        if ($roleKey === 'supervisor' && ! $user->supervisor_id_3) {
            throw ValidationException::withMessages([
                'assessment' => 'ยังไม่สามารถประเมินตนเองได้ กรุณาให้ Admin กำหนดผู้ประเมินลำดับที่ 3 ก่อน',
            ]);
        }

        $hasAssignedHeadOrSupervisor = collect([
            $user->supervisor_id_1,
            $user->supervisor_id_2,
        ])->contains(fn ($id) => filled($id));

        if (! $hasAssignedHeadOrSupervisor) {
            throw ValidationException::withMessages([
                'assessment' => 'ยังไม่สามารถประเมินตนเองได้ กรุณาให้ Admin กำหนดหัวหน้างานหรือผู้บังคับบัญชาก่อน',
            ]);
        }
    }

    private function normalizeRoleKey(string $roleKey): string
    {
        return match ($roleKey) {
            'manager' => 'dean',
            'manager_dept' => 'dept_head',
            default => $roleKey,
        };
    }
}

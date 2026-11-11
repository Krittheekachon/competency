<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\AssessmentMethodResolver;
use App\Services\AssessmentRoundReadinessService;
use App\Services\CompetencyAssessmentSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssessmentRoundController extends Controller
{
    public function __construct(
        private CompetencyAssessmentSyncService $assessmentSync,
        private AssessmentRoundReadinessService $readiness,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data): void {
            $method = $data['assessment_method'] ?? AssessmentMethodResolver::STANDARD;
            $roundId = DB::table('assessment_rounds')->insertGetId([
                ...$this->roundValues($data, false),
                'assessment_method' => $method,
                'assessment_method_version' => AssessmentMethodResolver::CURRENT_VERSIONS[$method],
                ...$this->expectedLevelValues($data),
                'snapshot_required' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (! empty($data['copy_from_round_id'])) {
                $this->copyConfiguration((int) $data['copy_from_round_id'], (int) $roundId, $method);
            }

            if ($data['is_active']) {
                $this->readiness->assertReady((int) $roundId);
                DB::table('assessment_rounds')->lockForUpdate()->get(['id']);
                DB::table('assessment_rounds')->where('is_active', true)->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
                DB::table('assessment_rounds')->where('id', $roundId)->update([
                    'is_active' => true,
                    'closed_at' => null,
                    'closed_by' => null,
                    'updated_at' => now(),
                ]);
            }
        });

        if ($data['is_active']) {
            $this->assessmentSync->syncAllActiveUsers();
        }

        return back()->with('success', 'สร้างรอบการประเมินเรียบร้อยแล้ว');
    }

    public function update(Request $request, int $round): RedirectResponse
    {
        abort_unless(DB::table('assessment_rounds')->where('id', $round)->exists(), 404);

        $data = $this->validated($request, $round);

        DB::transaction(function () use ($data, $round): void {
            DB::table('assessment_rounds')->lockForUpdate()->get(['id']);
            DB::table('assessment_rounds')->where('id', $round)->update([
                ...$this->roundValues($data, false),
                ...$this->methodValues($round, $data['assessment_method'] ?? null),
                ...$this->expectedLevelPolicyValues($round, $data),
                'updated_at' => now(),
            ]);

            if ($data['is_active']) {
                $this->readiness->assertReady($round);
                DB::table('assessment_rounds')
                    ->where('id', '!=', $round)
                    ->where('is_active', true)
                    ->update(['is_active' => false, 'updated_at' => now()]);
                DB::table('assessment_rounds')->where('id', $round)->update([
                    'is_active' => true,
                    'closed_at' => null,
                    'closed_by' => null,
                    'updated_at' => now(),
                ]);
            }
        });

        if ($data['is_active']) {
            $this->assessmentSync->syncAllActiveUsers();
        }

        return back()->with('success', 'บันทึกรอบการประเมินเรียบร้อยแล้ว');
    }

    public function activate(int $round): RedirectResponse
    {
        abort_unless(DB::table('assessment_rounds')->where('id', $round)->exists(), 404);
        $this->readiness->assertReady($round);

        DB::transaction(function () use ($round): void {
            DB::table('assessment_rounds')->lockForUpdate()->get(['id']);
            DB::table('assessment_rounds')->where('is_active', true)->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
            DB::table('assessment_rounds')->where('id', $round)->update([
                'is_active' => true,
                'closed_at' => null,
                'closed_by' => null,
                'updated_at' => now(),
            ]);
        });

        $this->assessmentSync->syncAllActiveUsers();

        return back()->with('success', 'เปลี่ยนรอบการประเมินที่ใช้งานแล้ว');
    }

    public function close(Request $request, int $round): RedirectResponse
    {
        $assessmentRound = DB::table('assessment_rounds')->where('id', $round)->first();
        abort_unless($assessmentRound, 404);

        if (! $assessmentRound->is_active) {
            return back()->with('success', 'รอบการประเมินนี้ปิดอยู่แล้ว');
        }

        DB::table('assessment_rounds')->where('id', $round)->update([
            'is_active' => false,
            'closed_at' => now(),
            'closed_by' => $request->user()->id,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'สิ้นสุดรอบการประเมินแล้ว งาน Assessment และ IDP ที่เริ่มไว้ยังดำเนินการต่อด้วยข้อมูล snapshot เดิม');
    }

    private function validated(Request $request, ?int $round = null): array
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('assessment_rounds', 'name')->ignore($round),
            ],
            'self_assess_start' => [Rule::requiredIf(fn (): bool => $request->boolean('is_active')), 'nullable', 'date'],
            'self_assess_end' => [Rule::requiredIf(fn (): bool => $request->boolean('is_active')), 'nullable', 'date', 'after_or_equal:self_assess_start'],
            'supervisor_assess_end' => [Rule::requiredIf(fn (): bool => $request->boolean('is_active')), 'nullable', 'date', 'after_or_equal:self_assess_end'],
            'is_active' => ['required', 'boolean'],
            'copy_from_round_id' => ['nullable', 'integer', 'exists:assessment_rounds,id'],
            'assessment_method' => ['nullable', 'string', Rule::in(AssessmentMethodResolver::allowed())],
            'expected_level_mode' => ['nullable', 'string', Rule::in(['by_level', 'fixed'])],
            'expected_level_fixed' => ['nullable', 'integer', 'between:1,5', Rule::requiredIf(fn (): bool => $request->input('expected_level_mode') === 'fixed')],
        ], [
            'expected_level_mode.in' => 'รูปแบบระดับความคาดหวังที่เลือกไม่ถูกต้อง',
            'expected_level_fixed.required' => 'กรุณาเลือกระดับความคาดหวังที่ใช้กับทุกคนในรอบนี้',
            'expected_level_fixed.between' => 'ระดับความคาดหวังต้องอยู่ระหว่าง 1 ถึง 5',
            'assessment_method.in' => 'วิธีการประเมินที่เลือกไม่ถูกต้อง',
            'name.required' => 'กรุณาระบุชื่อรอบการประเมิน',
            'name.unique' => 'ชื่อรอบการประเมินนี้ถูกใช้แล้ว',
            'self_assess_start.required' => 'กรุณาระบุวันเริ่มการประเมินก่อนเปิดใช้งานรอบ',
            'self_assess_end.required' => 'กรุณาระบุวันสิ้นสุดการประเมินก่อนเปิดใช้งานรอบ',
            'supervisor_assess_end.required' => 'กรุณาระบุวันสิ้นสุดการตรวจของหัวหน้าก่อนเปิดใช้งานรอบ',
            'self_assess_end.after_or_equal' => 'วันสิ้นสุดการประเมินต้องไม่ก่อนวันเริ่มการประเมิน',
            'supervisor_assess_end.after_or_equal' => 'วันสิ้นสุดการตรวจของหัวหน้าต้องไม่ก่อนวันสิ้นสุดการประเมิน',
        ]);

        $storedYear = $round ? (int) DB::table('assessment_rounds')->where('id', $round)->value('year') : null;
        $hasAssessments = $round && DB::table('assessments')->where('assessment_round_id', $round)->exists();
        $startDate = $data['self_assess_start'] ?? null;
        $data['year'] = $hasAssessments
            ? $storedYear
            : ($startDate
                ? CarbonImmutable::parse($startDate)->year + 543
                : ($storedYear ?: now()->year + 543));

        return $data;
    }

    /**
     * The method can change only until the first self-assessment is submitted in the round.
     */
    private function assessmentMethods(): AssessmentMethodResolver
    {
        return app(AssessmentMethodResolver::class);
    }

    private function methodValues(int $round, ?string $method): array
    {
        $current = DB::table('assessment_rounds')->where('id', $round)->value('assessment_method');
        if ($method === null || $method === $current) {
            return [];
        }

        $hasSubmittedAssessment = DB::table('assessments')
            ->where('assessment_round_id', $round)
            ->whereNotNull('self_submitted_at')
            ->exists();
        if ($hasSubmittedAssessment) {
            throw ValidationException::withMessages([
                'assessment_method' => 'ไม่สามารถเปลี่ยนวิธีการประเมินได้ เนื่องจากมีบุคลากรส่งแบบประเมินในรอบนี้แล้ว',
            ]);
        }

        // Position competencies belong to one dictionary format; switching would mix the two.
        $boundQuery = DB::table('position_competencies')
            ->join('competencies', 'competencies.id', '=', 'position_competencies.competency_id')
            ->where('position_competencies.assessment_round_id', $round);
        $boundOtherFormat = $this->assessmentMethods()->whereIncompatibleWithMethod($boundQuery, $method)->exists();
        if ($boundOtherFormat) {
            throw ValidationException::withMessages([
                'assessment_method' => 'รอบนี้ผูกสมรรถนะ'.AssessmentMethodResolver::formatLabel((string) $current).'ไว้กับตำแหน่งแล้ว กรุณาลบสมรรถนะประจำตำแหน่งของรอบนี้ก่อนเปลี่ยนวิธีประเมิน',
            ]);
        }

        return [
            'assessment_method' => $method,
            'assessment_method_version' => AssessmentMethodResolver::CURRENT_VERSIONS[$method],
        ];
    }

    /** @return array{expected_level_mode: string, expected_level_fixed: int|null} */
    private function expectedLevelValues(array $data): array
    {
        $fixed = ($data['expected_level_mode'] ?? 'by_level') === 'fixed';

        return [
            'expected_level_mode' => $fixed ? 'fixed' : 'by_level',
            'expected_level_fixed' => $fixed ? (int) $data['expected_level_fixed'] : null,
        ];
    }

    /**
     * The expected-level policy is frozen once any gap exists in the round: gaps keep the expected
     * level they were scored against, so changing the rule afterwards would mix two standards.
     */
    private function expectedLevelPolicyValues(int $round, array $data): array
    {
        if (! array_key_exists('expected_level_mode', $data) || $data['expected_level_mode'] === null) {
            return [];
        }

        $new = $this->expectedLevelValues($data);
        $current = DB::table('assessment_rounds')->where('id', $round)->first(['expected_level_mode', 'expected_level_fixed']);
        if ($current->expected_level_mode === $new['expected_level_mode']
            && (int) $current->expected_level_fixed === (int) $new['expected_level_fixed']) {
            return [];
        }

        $locked = DB::table('competency_gaps')
            ->join('assessments', 'assessments.id', '=', 'competency_gaps.assessment_id')
            ->where('assessments.assessment_round_id', $round)
            ->exists();
        if ($locked) {
            throw ValidationException::withMessages([
                'expected_level_mode' => 'ไม่สามารถเปลี่ยนระดับความคาดหวังได้ เนื่องจากรอบนี้เริ่มประเมินแล้ว',
            ]);
        }

        return $new;
    }

    private function roundValues(array $data, ?bool $isActive = null): array
    {
        return [
            'name' => trim($data['name']),
            'year' => (int) $data['year'],
            'self_assess_start' => $data['self_assess_start'] ?? null,
            'self_assess_end' => $data['self_assess_end'] ?? null,
            'supervisor_assess_end' => $data['supervisor_assess_end'] ?? null,
            'is_active' => $isActive ?? (bool) $data['is_active'],
        ];
    }

    private function copyConfiguration(int $sourceRoundId, int $targetRoundId, string $method): void
    {
        $now = now();
        // Only competencies of the new round's format can be carried across.
        $sourceQuery = DB::table('position_competencies')
            ->join('competencies', 'competencies.id', '=', 'position_competencies.competency_id')
            ->where('position_competencies.assessment_round_id', $sourceRoundId);
        $competencies = $this->assessmentMethods()->whereCompatibleWithMethod($sourceQuery, $method)
            ->get(['position_competencies.position_id', 'position_competencies.position_title_id', 'position_competencies.competency_id'])
            ->map(fn (object $row): array => [
                'assessment_round_id' => $targetRoundId,
                'position_id' => $row->position_id,
                'position_title_id' => $row->position_title_id,
                'competency_id' => $row->competency_id,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($competencies !== []) {
            DB::table('position_competencies')->insertOrIgnore($competencies);
        }

        DB::table('position_fc_selection_rules')
            ->where('assessment_round_id', $sourceRoundId)
            ->get(['position_id', 'position_title_id', 'required_fc_count'])
            ->each(function (object $rule) use ($targetRoundId, $now): void {
                DB::table('position_fc_selection_rules')->insertOrIgnore([
                    'assessment_round_id' => $targetRoundId,
                    'position_id' => $rule->position_id,
                    'position_title_id' => $rule->position_title_id,
                    'required_fc_count' => $rule->required_fc_count,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

        DB::table('position_fc_unit_rules')
            ->where('assessment_round_id', $sourceRoundId)
            ->get(['position_title_id', 'support_unit_id', 'required_fc_count'])
            ->each(function (object $rule) use ($targetRoundId, $now): void {
                DB::table('position_fc_unit_rules')->insertOrIgnore([
                    'assessment_round_id' => $targetRoundId,
                    'position_title_id' => $rule->position_title_id,
                    'support_unit_id' => $rule->support_unit_id,
                    'required_fc_count' => $rule->required_fc_count,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

    }
}

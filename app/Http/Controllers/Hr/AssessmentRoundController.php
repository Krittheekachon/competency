<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\CompetencyAssessmentSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssessmentRoundController extends Controller
{
    public function __construct(private CompetencyAssessmentSyncService $assessmentSync)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data): void {
            if ($data['is_active']) {
                DB::table('assessment_rounds')->where('is_active', true)->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
            }

            $roundId = DB::table('assessment_rounds')->insertGetId([
                ...$this->roundValues($data),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (! empty($data['copy_from_round_id'])) {
                $this->copyConfiguration((int) $data['copy_from_round_id'], (int) $roundId);
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

        if (DB::table('assessments')->where('assessment_round_id', $round)->exists()) {
            $storedYear = (int) DB::table('assessment_rounds')->where('id', $round)->value('year');
            if ((int) $data['year'] !== $storedYear) {
                return back()->withErrors(['year' => 'ไม่สามารถเปลี่ยนปีของรอบที่เริ่มมีผลประเมินแล้ว']);
            }
        }

        DB::transaction(function () use ($data, $round): void {
            if ($data['is_active']) {
                DB::table('assessment_rounds')
                    ->where('id', '!=', $round)
                    ->where('is_active', true)
                    ->update(['is_active' => false, 'updated_at' => now()]);
            }

            DB::table('assessment_rounds')->where('id', $round)->update([
                ...$this->roundValues($data),
                'updated_at' => now(),
            ]);
        });

        if ($data['is_active']) {
            $this->assessmentSync->syncAllActiveUsers();
        }

        return back()->with('success', 'บันทึกรอบการประเมินเรียบร้อยแล้ว');
    }

    public function activate(int $round): RedirectResponse
    {
        abort_unless(DB::table('assessment_rounds')->where('id', $round)->exists(), 404);

        DB::transaction(function () use ($round): void {
            DB::table('assessment_rounds')->where('is_active', true)->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
            DB::table('assessment_rounds')->where('id', $round)->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
        });

        $this->assessmentSync->syncAllActiveUsers();

        return back()->with('success', 'เปลี่ยนรอบการประเมินที่ใช้งานแล้ว');
    }

    private function validated(Request $request, ?int $round = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('assessment_rounds', 'name')->ignore($round),
            ],
            'year' => ['required', 'integer', 'min:2500', 'max:2700'],
            'self_assess_start' => ['nullable', 'date'],
            'self_assess_end' => ['nullable', 'date', 'after_or_equal:self_assess_start'],
            'supervisor_assess_end' => ['nullable', 'date', 'after_or_equal:self_assess_end'],
            'is_active' => ['required', 'boolean'],
            'copy_from_round_id' => ['nullable', 'integer', 'exists:assessment_rounds,id'],
        ], [
            'name.required' => 'กรุณาระบุชื่อรอบการประเมิน',
            'name.unique' => 'ชื่อรอบการประเมินนี้ถูกใช้แล้ว',
            'year.required' => 'กรุณาระบุปีการประเมิน',
            'self_assess_end.after_or_equal' => 'วันสิ้นสุดการประเมินต้องไม่ก่อนวันเริ่มการประเมิน',
            'supervisor_assess_end.after_or_equal' => 'วันสิ้นสุดการตรวจของหัวหน้าต้องไม่ก่อนวันสิ้นสุดการประเมิน',
        ]);
    }

    private function roundValues(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'year' => (int) $data['year'],
            'self_assess_start' => $data['self_assess_start'] ?? null,
            'self_assess_end' => $data['self_assess_end'] ?? null,
            'supervisor_assess_end' => $data['supervisor_assess_end'] ?? null,
            'is_active' => (bool) $data['is_active'],
        ];
    }

    private function copyConfiguration(int $sourceRoundId, int $targetRoundId): void
    {
        $now = now();
        $competencies = DB::table('position_competencies')
            ->where('assessment_round_id', $sourceRoundId)
            ->get(['position_id', 'competency_id'])
            ->map(fn (object $row): array => [
                'assessment_round_id' => $targetRoundId,
                'position_id' => $row->position_id,
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
            ->get(['position_id', 'required_fc_count'])
            ->each(function (object $rule) use ($targetRoundId, $now): void {
                DB::table('position_fc_selection_rules')->insertOrIgnore([
                    'assessment_round_id' => $targetRoundId,
                    'position_id' => $rule->position_id,
                    'required_fc_count' => $rule->required_fc_count,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }
}

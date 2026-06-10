<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\CompetencyAssessmentSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompetencyAssignmentController extends Controller
{
    public function __construct(private CompetencyAssessmentSyncService $competencyAssessmentSync)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'workline_name' => ['required', 'string', Rule::exists('worklines', 'name')],
            'job_family_name' => ['required', 'string'],
            'level_name' => ['required', 'string'],
            'competency_ids' => ['array'],
            'competency_ids.*' => ['integer', Rule::exists('competencies', 'id')],
        ]);

        $workline = DB::table('worklines')->where('name', $data['workline_name'])->first();
        $jobFamily = DB::table('job_families')
            ->where('workline_id', $workline->id)
            ->where('name', $data['job_family_name'])
            ->first();

        if (! $jobFamily) {
            throw ValidationException::withMessages([
                'job_family_name' => 'ไม่พบกลุ่มงานในสายงานที่เลือก',
            ]);
        }

        $level = $this->resolveLevel($data['level_name'], (int) $workline->id, (int) $jobFamily->id);

        $roundId = $this->activeRoundId();
        $competencyIds = collect($data['competency_ids'] ?? [])->unique()->values();

        DB::transaction(function () use ($roundId, $jobFamily, $level, $competencyIds): void {
            DB::table('hr_expectations')
                ->where('assessment_round_id', $roundId)
                ->where('job_family_id', $jobFamily->id)
                ->where('level_id', $level->id)
                ->whereNull('position_id')
                ->delete();

            $now = now();

            foreach ($competencyIds as $competencyId) {
                DB::table('hr_expectations')->insert([
                    'assessment_round_id' => $roundId,
                    'position_id' => null,
                    'level_id' => $level->id,
                    'job_family_id' => $jobFamily->id,
                    'competency_id' => $competencyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        $this->competencyAssessmentSync->syncScope($roundId, $jobFamily->id, $level->id);

        return back()->with('success', 'บันทึกการผูกสมรรถนะกับกลุ่มงานแล้ว');
    }

    private function activeRoundId(): int
    {
        $roundId = DB::table('assessment_rounds')
            ->where('is_active', true)
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->value('id');

        if ($roundId) {
            return (int) $roundId;
        }

        return (int) DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบประเมินปัจจุบัน',
            'year' => (int) now()->format('Y') + 543,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function resolveLevel(string $levelName, int $worklineId, int $jobFamilyId): object
    {
        $level = DB::table('levels')
            ->where('name', $levelName)
            ->where(function ($query) use ($worklineId, $jobFamilyId) {
                $query->where('job_family_id', $jobFamilyId)
                    ->orWhere(function ($nested) use ($worklineId) {
                        $nested->whereNull('job_family_id')
                            ->where('workline_id', $worklineId);
                    })
                    ->orWhere(function ($nested) {
                        $nested->whereNull('job_family_id')
                            ->whereNull('workline_id');
                    });
            })
            ->orderByRaw('case when job_family_id is not null then 0 when workline_id is not null then 1 else 2 end')
            ->first();

        if ($level) {
            return $level;
        }

        $levelId = DB::table('levels')->insertGetId([
            'workline_id' => $worklineId,
            'job_family_id' => $jobFamilyId,
            'name' => $levelName,
            'expected_level' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('levels')->where('id', $levelId)->first();
    }
}

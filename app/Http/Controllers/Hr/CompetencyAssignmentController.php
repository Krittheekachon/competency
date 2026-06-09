<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompetencyAssignmentController extends Controller
{
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

        $level = DB::table('levels')
            ->where('name', $data['level_name'])
            ->where(function ($query) use ($workline, $jobFamily) {
                $query->where('job_family_id', $jobFamily->id)
                    ->orWhere(function ($nested) use ($workline) {
                        $nested->whereNull('job_family_id')
                            ->where('workline_id', $workline->id);
                    })
                    ->orWhere(function ($nested) {
                        $nested->whereNull('job_family_id')
                            ->whereNull('workline_id');
                    });
            })
            ->orderByRaw('case when job_family_id is not null then 0 when workline_id is not null then 1 else 2 end')
            ->first();

        if (! $level) {
            throw ValidationException::withMessages([
                'level_name' => 'ไม่พบระดับตำแหน่งที่เลือก',
            ]);
        }

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
}

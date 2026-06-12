<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CompetencyAssessmentSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PositionCompetencyController extends Controller
{
    public function __construct(private CompetencyAssessmentSyncService $competencyAssessmentSync)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'position_id' => ['required', 'integer', 'exists:positions,id'],
            'competency_id' => ['nullable', 'integer', 'exists:competencies,id'],
            'competency_ids' => ['nullable', 'array'],
            'competency_ids.*' => ['integer', 'exists:competencies,id'],
        ]);

        $competencyIds = collect($data['competency_ids'] ?? [])
            ->push($data['competency_id'] ?? null)
            ->filter()
            ->unique()
            ->values();

        if ($competencyIds->isEmpty()) {
            return back()->withErrors(['competency_id' => 'กรุณาเลือกสมรรถนะอย่างน้อย 1 รายการ']);
        }

        $now = now();

        $rows = $competencyIds->map(fn (int $competencyId) => [
            'position_id' => $data['position_id'],
            'competency_id' => $competencyId,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('position_competencies')->insertOrIgnore($rows);
        $this->syncUsersForPosition((int) $data['position_id']);

        return back()->with('success', 'ผูกสมรรถนะกับตำแหน่งเรียบร้อยแล้ว');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'position_id' => ['required', 'integer', 'exists:positions,id'],
            'competency_id' => ['required', 'integer', 'exists:competencies,id'],
        ]);

        DB::table('position_competencies')
            ->where('position_id', $data['position_id'])
            ->where('competency_id', $data['competency_id'])
            ->delete();
        $this->syncUsersForPosition((int) $data['position_id']);

        return back()->with('success', 'ลบสมรรถนะออกจากตำแหน่งเรียบร้อยแล้ว');
    }

    private function syncUsersForPosition(int $positionId): void
    {
        $position = DB::table('positions')
            ->join('job_families', 'positions.job_family_id', '=', 'job_families.id')
            ->join('worklines', 'job_families.workline_id', '=', 'worklines.id')
            ->where('positions.id', $positionId)
            ->select(
                'positions.id',
                'positions.name',
                'job_families.name as job_family_name',
                'worklines.name as workline_name',
            )
            ->first();

        if (! $position) {
            return;
        }

        User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($position) {
                $query->where('position_id', $position->id)
                    ->orWhere(function ($nested) use ($position) {
                        $nested->where('position', $position->name)
                            ->where('workline', $position->workline_name)
                            ->where(function ($department) use ($position) {
                                $department->where('department', $position->job_family_name)
                                    ->orWhere('department', 'like', $position->job_family_name.' > %');
                            });
                    });
            })
            ->get()
            ->each(fn (User $user) => $this->competencyAssessmentSync->syncUser($user));
    }
}

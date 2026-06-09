<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PositionCompetencyController extends Controller
{
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

        return back()->with('success', 'ลบสมรรถนะออกจากตำแหน่งเรียบร้อยแล้ว');
    }
}

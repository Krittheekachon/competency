<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Competency;
use App\Models\CompetencyType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CompetencyController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        DB::transaction(function () use ($data): void {
            $competency = Competency::create($this->competencyData($data));
            $this->replaceLevels($competency, $data['levels']);
        });

        return back()->with('success', 'บันทึกสมรรถนะเรียบร้อยแล้ว');
    }

    public function update(Request $request, Competency $competency): RedirectResponse
    {
        $data = $this->validatedData($request, $competency);

        DB::transaction(function () use ($competency, $data): void {
            $competency->update($this->competencyData($data));
            $this->replaceLevels($competency, $data['levels']);
        });

        return back()->with('success', 'อัปเดตสมรรถนะเรียบร้อยแล้ว');
    }

    public function destroy(Competency $competency): RedirectResponse
    {
        $competency->delete();

        return back()->with('success', 'ลบสมรรถนะเรียบร้อยแล้ว');
    }

    private function validatedData(Request $request, ?Competency $competency = null): array
    {
        return $request->validate([
            'competency_type_id' => ['required', 'integer', Rule::exists('competency_types', 'id')],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('competencies', 'code')->ignore($competency?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'detail' => ['nullable', 'string'],
            'levels' => ['required', 'array', 'min:1'],
            'levels.*.level' => ['required', 'integer', 'min:1', 'max:10'],
            'levels.*.description' => ['nullable', 'string'],
            'levels.*.indicators' => ['required', 'array', 'min:1'],
            'levels.*.indicators.*.description' => ['required', 'string'],
            'levels.*.indicators.*.weight' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
        ]);
    }

    private function competencyData(array $data): array
    {
        return [
            'competency_type_id' => $data['competency_type_id'],
            'code' => $data['code'],
            'name' => $data['name'],
            'detail' => $data['detail'] ?? null,
        ];
    }

    private function replaceLevels(Competency $competency, array $levels): void
    {
        $competency->levels()->delete();

        foreach ($levels as $levelData) {
            $level = $competency->levels()->create([
                'level' => $levelData['level'],
                'description' => $levelData['description'] ?? null,
            ]);

            foreach ($levelData['indicators'] as $indicatorData) {
                $level->indicators()->create([
                    'description' => $indicatorData['description'],
                    'weight' => $indicatorData['weight'] ?? null,
                ]);
            }
        }
    }
}

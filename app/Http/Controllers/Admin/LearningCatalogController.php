<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LearningCatalogController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        DB::transaction(function () use ($data) {
            $catalogId = DB::table('learning_catalogs')->insertGetId([
                ...$this->catalogPayload($data),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncCompetencies($catalogId, $data['competency_ids'] ?? []);
        });

        return back()->with('success', 'บันทึก Learning Catalog เรียบร้อยแล้ว');
    }

    public function update(Request $request, int $catalog): RedirectResponse
    {
        $data = $this->validatedData($request);

        DB::transaction(function () use ($catalog, $data) {
            DB::table('learning_catalogs')
                ->where('id', $catalog)
                ->update([
                    ...$this->catalogPayload($data),
                    'updated_at' => now(),
                ]);

            $this->syncCompetencies($catalog, $data['competency_ids'] ?? []);
        });

        return back()->with('success', 'อัปเดต Learning Catalog เรียบร้อยแล้ว');
    }

    public function destroy(int $catalog): RedirectResponse
    {
        DB::table('learning_catalogs')->where('id', $catalog)->delete();

        return back()->with('success', 'ลบ Learning Catalog เรียบร้อยแล้ว');
    }

    private function validatedData(Request $request): array
    {
        $catalogId = $request->route('catalog');

        return $request->validate([
            'code' => ['nullable', 'string', 'max:100', Rule::unique('learning_catalogs', 'code')->ignore($catalogId)],
            'name' => ['required', 'string', 'max:255', Rule::unique('learning_catalogs', 'name')->ignore($catalogId)],
            'method_key' => [
                'nullable',
                'string',
                Rule::exists('learning_method_types', 'key')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'delivery_type' => ['required', Rule::in(['e_learning', 'in_class'])],
            'source_type' => ['required', Rule::in(['internal', 'external'])],
            'provider' => ['nullable', 'string', 'max:255'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'hours' => ['nullable', 'numeric', 'min:0'],
            'expected_levels' => ['nullable', 'array'],
            'expected_levels.*' => ['integer', 'between:1,5'],
            'competency_ids' => ['nullable', 'array', 'max:1'],
            'competency_ids.*' => ['integer', Rule::exists('competencies', 'id')],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ], [
            'code.unique' => 'รหัสหลักสูตรนี้ถูกใช้งานแล้ว',
            'name.unique' => 'ชื่อหลักสูตรนี้ถูกใช้งานแล้ว',
        ]);
    }

    private function catalogPayload(array $data): array
    {
        $expectedLevels = collect($data['expected_levels'] ?? [])
            ->map(fn ($level) => (int) $level)
            ->filter(fn ($level) => $level >= 1 && $level <= 5)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'method_type_id' => $this->learningMethodId($data['method_key'] ?? null),
            'delivery_type' => $data['delivery_type'],
            'source_type' => $data['source_type'],
            'provider' => $data['provider'] ?? null,
            'cost' => $data['cost'] ?? null,
            'hours' => $data['hours'] ?? null,
            'expected_levels' => $expectedLevels ? json_encode($expectedLevels) : null,
            'description' => $data['description'] ?? null,
            'is_active' => (bool) $data['is_active'],
        ];
    }

    private function learningMethodId(?string $key): ?int
    {
        if (!$key) return null;

        return DB::table('learning_method_types')
            ->where('key', $key)
            ->value('id');
    }

    private function syncCompetencies(int $catalogId, array $competencyIds): void
    {
        DB::table('learning_catalog_competency')
            ->where('learning_catalog_id', $catalogId)
            ->delete();

        $rows = collect($competencyIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->map(fn ($id) => [
                'learning_catalog_id' => $catalogId,
                'competency_id' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        if ($rows) {
            DB::table('learning_catalog_competency')->insert($rows);
        }
    }
}

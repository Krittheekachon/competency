<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IdpController extends Controller
{
    public function saveDraft(Request $request): RedirectResponse|JsonResponse
    {
        $this->persist($request, 'draft');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'บันทึกร่างแผน IDP แล้ว',
                'savedAt' => now()->toIso8601String(),
            ]);
        }

        return back()->with('success', 'บันทึกร่างแผน IDP แล้ว');
    }

    public function submit(Request $request): RedirectResponse
    {
        $this->persist($request, 'submitted');

        return back()->with('success', 'ส่งแผน IDP ให้หัวหน้าอนุมัติแล้ว');
    }

    public function submitItem(Request $request): RedirectResponse
    {
        $request->merge([
            'items' => [$request->input('item', [])],
        ]);
        $this->persist($request, 'submitted');

        return back()->with('success', 'ส่งแผนสมรรถนะนี้ให้หัวหน้าอนุมัติแล้ว');
    }

    private function persist(Request $request, string $status): void
    {
        $required = $status === 'submitted' ? 'required' : 'nullable';
        $validated = $request->validate([
            'items' => ['present', 'array'],
            'items.*.competencyGapId' => ['required', 'integer'],
            'items.*.goal' => [$required, 'string'],
            'items.*.successCriteria' => [$required, 'string'],
            'items.*.activities' => ['present', 'array'],
            'items.*.activities.*.methodKey' => [$required, 'string', 'max:255'],
            'items.*.activities.*.developmentToolId' => ['nullable', 'integer', 'exists:idp_learning_methods,id'],
            'items.*.activities.*.learningCatalogId' => ['nullable', 'integer', 'exists:learning_catalogs,id'],
            'items.*.activities.*.activityName' => [$required, 'string', 'max:255'],
            'items.*.activities.*.activityDescription' => ['nullable', 'string'],
            'items.*.activities.*.documentReferenceNumber' => ['nullable', 'string', 'max:255'],
            'items.*.activities.*.weightPercent' => [$required, 'numeric', 'min:0', 'max:100'],
            'items.*.activities.*.startDate' => [$required, 'date'],
            'items.*.activities.*.endDate' => [$required, 'date'],
        ]);

        $items = collect($validated['items'] ?? [])->values();
        $this->validateUniqueGaps($items);
        $validGaps = $this->validGapsForCurrentUser($items->pluck('competencyGapId')->unique()->values());

        if ($items->isNotEmpty() && $validGaps->count() !== $items->pluck('competencyGapId')->unique()->count()) {
            throw ValidationException::withMessages([
                'items' => 'ไม่สามารถจัดทำ IDP จาก Gap ที่ไม่ใช่ของผู้ใช้ปัจจุบัน หรือผลประเมินยังไม่อนุมัติ',
            ]);
        }

        if ($status === 'submitted' && $items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'ไม่มีรายการ Gap ที่ต้องจัดทำ IDP',
            ]);
        }

        $methodIdsByKey = DB::table('learning_method_types')
            ->whereIn('key', $items->flatMap(fn (array $item) => $item['activities'] ?? [])->pluck('methodKey')->filter()->unique())
            ->pluck('id', 'key');
        $toolFocusById = DB::table('idp_learning_methods')
            ->whereIn('id', $items->flatMap(fn (array $item) => $item['activities'] ?? [])->pluck('developmentToolId')->filter()->unique())
            ->where('is_active', true)
            ->pluck('focus_type', 'id');
        $catalogCompetencies = DB::table('learning_catalog_competency')
            ->join('learning_catalogs', 'learning_catalog_competency.learning_catalog_id', '=', 'learning_catalogs.id')
            ->whereIn('learning_catalog_id', $items->flatMap(fn (array $item) => $item['activities'] ?? [])->pluck('learningCatalogId')->filter()->unique())
            ->where('learning_catalogs.is_active', true)
            ->get(['learning_catalog_competency.learning_catalog_id', 'learning_catalog_competency.competency_id'])
            ->groupBy('learning_catalog_id')
            ->map(fn ($rows) => $rows->pluck('competency_id')->map(fn ($id) => (int) $id));

        $this->validateActivities(
            $items,
            $status,
            $validGaps,
            $methodIdsByKey,
            $toolFocusById,
            $catalogCompetencies,
        );

        DB::transaction(function () use ($items, $status, $methodIdsByKey): void {
            $idpId = $this->currentUserIdpId();
            foreach ($items as $item) {
                $gapId = (int) $item['competencyGapId'];
                $existing = DB::table('idp_items')
                    ->where('idp_id', $idpId)
                    ->where('competency_gap_id', $gapId)
                    ->first();

                if ($existing && in_array($existing->status, ['submitted', 'approved'], true)) {
                    continue;
                }

                $values = [
                    'behavior_key' => 'competency-gap:'.$item['competencyGapId'],
                    'behavior_description' => null,
                    'goal' => $item['goal'] ?? null,
                    'success_criteria' => $item['successCriteria'] ?? null,
                    'status' => $status,
                    'submitted_at' => $status === 'submitted' ? now() : null,
                    'updated_at' => now(),
                ];

                if ($status === 'submitted') {
                    $values = [
                        ...$values,
                        'approved_by' => null,
                        'approved_at' => null,
                        'rejected_by' => null,
                        'rejected_at' => null,
                        'reject_comment' => null,
                    ];
                }

                if ($existing) {
                    DB::table('idp_items')->where('id', $existing->id)->update($values);
                    $itemId = (int) $existing->id;
                    DB::table('idp_activities')->where('idp_item_id', $itemId)->delete();
                } else {
                    $itemId = DB::table('idp_items')->insertGetId([
                        'idp_id' => $idpId,
                        'competency_gap_id' => $gapId,
                        ...$values,
                        'created_at' => now(),
                    ]);
                }

                foreach ($item['activities'] ?? [] as $activity) {
                    DB::table('idp_activities')->insert([
                        'idp_item_id' => $itemId,
                        'learning_catalog_id' => $activity['learningCatalogId'] ?? null,
                        'method_type_id' => $methodIdsByKey[$activity['methodKey'] ?? ''] ?? null,
                        'idp_learning_method_id' => $activity['developmentToolId'] ?? null,
                        'activity_name' => $activity['activityName'] ?? null,
                        'weight_percent' => $activity['weightPercent'] ?? null,
                        'start_date' => $activity['startDate'] ?? null,
                        'end_date' => $activity['endDate'] ?? null,
                        'description' => $activity['activityDescription'] ?? null,
                        'document_reference_number' => $activity['documentReferenceNumber'] ?? null,
                        'status' => 'planned',
                        'result' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $this->syncIdpStatus($idpId);
        });
    }

    private function validateUniqueGaps(Collection $items): void
    {
        if ($items->pluck('competencyGapId')->filter()->count() !== $items->pluck('competencyGapId')->filter()->unique()->count()) {
            throw ValidationException::withMessages([
                'items' => 'หนึ่งสมรรถนะสามารถมีแผน IDP ได้เพียงหนึ่งแผน',
            ]);
        }
    }

    private function validateActivities(
        Collection $items,
        string $status,
        Collection $validGaps,
        Collection $methodIdsByKey,
        Collection $toolFocusById,
        Collection $catalogCompetencies,
    ): void {
        foreach ($items as $itemIndex => $item) {
            $activities = collect($item['activities'] ?? [])->values();

            if ($status === 'submitted' && $activities->isEmpty()) {
                throw ValidationException::withMessages([
                    "items.$itemIndex.activities" => 'ต้องมีกิจกรรมพัฒนาอย่างน้อยหนึ่งรายการ',
                ]);
            }

            if ($status === 'submitted' && round((float) $activities->sum('weightPercent'), 2) !== 100.0) {
                throw ValidationException::withMessages([
                    "items.$itemIndex.activities" => 'น้ำหนักกิจกรรมของแต่ละสมรรถนะต้องรวม 100%',
                ]);
            }

            $competencyId = (int) ($validGaps[$item['competencyGapId']] ?? 0);

            foreach ($activities as $activityIndex => $activity) {
                $prefix = "items.$itemIndex.activities.$activityIndex";
                $methodKey = $activity['methodKey'] ?? '';
                $focusType = $this->focusTypeForMethodKey($methodKey);

                if ($methodKey !== '' && ! $methodIdsByKey->has($methodKey)) {
                    throw ValidationException::withMessages([
                        "$prefix.methodKey" => 'ไม่พบประเภทการเรียนรู้ที่เลือก',
                    ]);
                }

                if (in_array($focusType, ['experiential', 'social'], true)) {
                    $toolId = $activity['developmentToolId'] ?? null;
                    if ($status === 'submitted' && ! $toolId) {
                        throw ValidationException::withMessages([
                            "$prefix.developmentToolId" => 'กรุณาเลือกเครื่องมือหรือแนวทางการพัฒนา',
                        ]);
                    }
                    if ($toolId && ($toolFocusById[$toolId] ?? null) !== $focusType) {
                        throw ValidationException::withMessages([
                            "$prefix.developmentToolId" => 'เครื่องมือพัฒนาไม่ตรงกับประเภทการเรียนรู้',
                        ]);
                    }
                }

                if ($focusType === 'formal') {
                    $catalogId = $activity['learningCatalogId'] ?? null;
                    if ($status === 'submitted' && ! $catalogId) {
                        throw ValidationException::withMessages([
                            "$prefix.learningCatalogId" => 'กรุณาเลือกหลักสูตรจาก Learning Catalog',
                        ]);
                    }
                    if ($catalogId && ! ($catalogCompetencies[$catalogId] ?? collect())->contains($competencyId)) {
                        throw ValidationException::withMessages([
                            "$prefix.learningCatalogId" => 'หลักสูตรนี้ไม่ได้ผูกกับสมรรถนะที่ต้องพัฒนา',
                        ]);
                    }
                }

                if (! empty($activity['startDate'])
                    && ! empty($activity['endDate'])
                    && $activity['endDate'] < $activity['startDate']) {
                    throw ValidationException::withMessages([
                        "$prefix.endDate" => 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น',
                    ]);
                }
            }
        }
    }

    private function focusTypeForMethodKey(string $methodKey): string
    {
        $normalized = strtolower($methodKey);

        return match (true) {
            str_contains($normalized, 'experiential'),
            str_contains($normalized, 'experience') => 'experiential',
            str_contains($normalized, 'social') => 'social',
            str_contains($normalized, 'formal') => 'formal',
            default => $normalized,
        };
    }

    private function validGapsForCurrentUser(Collection $gapIds): Collection
    {
        if ($gapIds->isEmpty()) {
            return collect();
        }

        return DB::table('competency_gaps')
            ->join('assessments', 'competency_gaps.assessment_id', '=', 'assessments.id')
            ->where('assessments.user_id', auth()->id())
            ->whereIn('competency_gaps.id', $gapIds)
            ->where('competency_gaps.requires_idp', true)
            ->where('competency_gaps.gap', '<', 0)
            ->whereIn('competency_gaps.status', ['approved', 'dean_approved'])
            ->pluck('competency_gaps.competency_id', 'competency_gaps.id');
    }

    private function currentUserIdpId(): int
    {
        $year = (int) (DB::table('assessment_rounds')
            ->where('is_active', true)
            ->orderByDesc('year')
            ->value('year') ?: ((int) now()->format('Y') + 543));
        $existing = DB::table('idps')
            ->where('user_id', auth()->id())
            ->where('year', $year)
            ->orderByDesc('id')
            ->value('id');
        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('idps')->insertGetId([
            'user_id' => auth()->id(),
            'year' => $year,
            'status' => 'draft',
            'submitted_at' => null,
            'updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function syncIdpStatus(int $idpId): void
    {
        $statuses = DB::table('idp_items')->where('idp_id', $idpId)->pluck('status');
        $status = match (true) {
            $statuses->isNotEmpty() && $statuses->every(fn (string $itemStatus) => $itemStatus === 'approved') => 'approved',
            $statuses->contains('submitted') => 'partially_submitted',
            $statuses->contains('revision_required') => 'revision_required',
            $statuses->contains('approved') => 'in_progress',
            default => 'draft',
        };

        DB::table('idps')->where('id', $idpId)->update([
            'status' => $status,
            'submitted_at' => $statuses->contains('submitted') ? now() : null,
            'updated_at' => now(),
        ]);
    }
}

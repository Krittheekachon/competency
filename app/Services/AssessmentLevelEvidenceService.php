<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssessmentLevelEvidenceService
{
    public const FACULTY_EVIDENCE_LEVEL = 5;

    public function existsFor(int $assessmentId, int $competencyId): bool
    {
        return DB::table('assessment_level_evidences')
            ->where('assessment_id', $assessmentId)
            ->where('competency_id', $competencyId)
            ->exists();
    }

    /**
     * @return Collection<string, array<int, array<string, mixed>>> keyed by "assessmentId:competencyId"
     */
    public function payloadsForAssessments(Collection $assessmentIds): Collection
    {
        if ($assessmentIds->isEmpty()) {
            return collect();
        }

        return DB::table('assessment_level_evidences')
            ->whereIn('assessment_id', $assessmentIds)
            ->orderBy('id')
            ->get()
            ->groupBy(fn (object $evidence): string => $evidence->assessment_id.':'.$evidence->competency_id)
            ->map(fn (Collection $rows): array => $rows
                ->map(fn (object $evidence): array => $this->payload($evidence))
                ->values()
                ->all());
    }

    public function payloadsFor(int $assessmentId, int $competencyId): array
    {
        return $this->payloadsForAssessments(collect([$assessmentId]))
            ->get($assessmentId.':'.$competencyId, []);
    }

    public function payload(object $evidence): array
    {
        return [
            'id' => $evidence->public_id,
            'kind' => $evidence->kind,
            'name' => $evidence->kind === 'link'
                ? ($evidence->description ?: $evidence->url)
                : ($evidence->original_name ?: 'หลักฐาน'),
            'description' => $evidence->description,
            'url' => $evidence->kind === 'link'
                ? $evidence->url
                : route('assessments.evidence.show', $evidence->public_id),
            'sizeBytes' => $evidence->size_bytes === null ? null : (int) $evidence->size_bytes,
            'uploadedAt' => $evidence->created_at,
        ];
    }
}

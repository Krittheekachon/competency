<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IdpItemReviewWorkflow
{
    public function configuredSteps(object $owner): Collection
    {
        return collect([1, 2, 3])
            ->filter(fn (int $step): bool => filled($owner->{'supervisor_id_'.$step} ?? null))
            ->values();
    }

    public function firstStep(object $owner): int
    {
        $step = $this->configuredSteps($owner)->first();

        if (! $step) {
            throw ValidationException::withMessages([
                'item' => 'ยังไม่ได้กำหนดผู้อนุมัติแผน IDP',
            ]);
        }

        return (int) $step;
    }

    public function nextStep(object $owner, int $currentStep): ?int
    {
        $step = $this->configuredSteps($owner)
            ->first(fn (int $step): bool => $step > $currentStep);

        return $step ? (int) $step : null;
    }

    public function reviewerIdForStep(object $owner, int $step): ?int
    {
        $id = $owner->{'supervisor_id_'.$step} ?? null;

        return $id ? (int) $id : null;
    }

    public function statusForStep(int $step): string
    {
        return 'review_step_'.$step;
    }

    public function isUnderReview(string $status): bool
    {
        return in_array($status, ['review_step_1', 'review_step_2', 'review_step_3'], true);
    }

    public function assertCurrentReviewer(object $item, int $reviewerId): void
    {
        $step = (int) ($item->current_review_step ?? 0);

        if ($step < 1
            || $item->status !== $this->statusForStep($step)
            || $this->reviewerIdForStep($item, $step) !== $reviewerId) {
            throw ValidationException::withMessages([
                'idpItemId' => 'คุณไม่มีสิทธิ์ตรวจแผนสมรรถนะนี้ หรือแผนไม่ได้อยู่ในขั้นตอนของคุณ',
            ]);
        }
    }

    public function syncParentStatus(int $idpId): void
    {
        $statuses = DB::table('idp_items')->where('idp_id', $idpId)->pluck('status');
        $underReview = $statuses->contains(
            fn (string $status): bool => $this->isUnderReview($status)
        );

        $status = match (true) {
            $statuses->isNotEmpty()
                && $statuses->every(fn (string $status): bool => $status === 'approved') => 'approved',
            $underReview => 'partially_submitted',
            $statuses->contains('revision_required') => 'revision_required',
            $statuses->contains('approved') => 'in_progress',
            default => 'draft',
        };

        DB::table('idps')->where('id', $idpId)->update([
            'status' => $status,
            'submitted_at' => $underReview ? now() : null,
            'updated_at' => now(),
        ]);
    }
}

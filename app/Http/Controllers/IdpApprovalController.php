<?php

namespace App\Http\Controllers;

use App\Services\IdpItemReviewWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IdpApprovalController extends Controller
{
    public function __construct(
        private readonly IdpItemReviewWorkflow $reviewWorkflow
    ) {
    }

    public function approve(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'idpItemId' => ['required', 'integer'],
            'comment' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated): void {
            $item = $this->reviewableItem((int) $validated['idpItemId']);
            $step = (int) $item->current_review_step;
            $now = now();

            $this->recordDecision(
                $item,
                $step,
                'approved',
                filled($validated['comment'] ?? null)
                    ? trim($validated['comment'])
                    : null,
                $now
            );

            $nextStep = $this->reviewWorkflow->nextStep($item, $step);
            DB::table('idp_items')->where('id', $item->id)->update($nextStep
                ? [
                    'status' => $this->reviewWorkflow->statusForStep($nextStep),
                    'current_review_step' => $nextStep,
                    'updated_at' => $now,
                ]
                : [
                    'status' => 'approved',
                    'current_review_step' => null,
                    'approved_by' => auth()->id(),
                    'approved_at' => $now,
                    'rejected_by' => null,
                    'rejected_at' => null,
                    'reject_comment' => null,
                    'updated_at' => $now,
                ]);

            $this->reviewWorkflow->syncParentStatus((int) $item->idp_id);
        });

        return back()->with('success', 'อนุมัติแผนสมรรถนะแล้ว');
    }

    public function reject(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'idpItemId' => ['required', 'integer'],
            'comment' => ['required', 'string'],
        ]);

        DB::transaction(function () use ($validated): void {
            $item = $this->reviewableItem((int) $validated['idpItemId']);
            $step = (int) $item->current_review_step;
            $comment = trim($validated['comment']);
            $now = now();

            $this->recordDecision($item, $step, 'rejected', $comment, $now);

            DB::table('idp_items')->where('id', $item->id)->update([
                'status' => 'revision_required',
                'current_review_step' => null,
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => auth()->id(),
                'rejected_at' => $now,
                'reject_comment' => $comment,
                'updated_at' => $now,
            ]);

            $this->reviewWorkflow->syncParentStatus((int) $item->idp_id);
        });

        return back()->with('success', 'ตีกลับแผนสมรรถนะให้แก้ไขแล้ว');
    }

    private function reviewableItem(int $itemId): object
    {
        $item = DB::table('idp_items')
            ->join('idps', 'idp_items.idp_id', '=', 'idps.id')
            ->join('users', 'idps.user_id', '=', 'users.id')
            ->where('idp_items.id', $itemId)
            ->select(
                'idp_items.id',
                'idp_items.idp_id',
                'idp_items.status',
                'idp_items.submission_version',
                'idp_items.current_review_step',
                'users.id as user_id',
                'users.supervisor_id_1',
                'users.supervisor_id_2',
                'users.supervisor_id_3'
            )
            ->lockForUpdate()
            ->first();

        if (! $item) {
            throw ValidationException::withMessages([
                'idpItemId' => 'ไม่พบแผนสมรรถนะ',
            ]);
        }

        $this->reviewWorkflow->assertCurrentReviewer($item, (int) auth()->id());

        return $item;
    }

    private function recordDecision(
        object $item,
        int $step,
        string $decision,
        ?string $comment,
        $decidedAt
    ): void {
        DB::table('idp_item_reviews')->insert([
            'idp_item_id' => $item->id,
            'submission_version' => $item->submission_version,
            'review_step' => $step,
            'reviewer_id' => auth()->id(),
            'decision' => $decision,
            'comment' => $comment,
            'decided_at' => $decidedAt,
            'created_at' => $decidedAt,
            'updated_at' => $decidedAt,
        ]);
    }
}

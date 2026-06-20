<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IdpApprovalController extends Controller
{
    public function approve(Request $request): RedirectResponse
    {
        $item = $this->reviewableItem((int) $request->validate([
            'idpItemId' => ['required', 'integer'],
        ])['idpItemId']);

        DB::transaction(function () use ($item): void {
            DB::table('idp_items')->where('id', $item->id)->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'reject_comment' => null,
                'updated_at' => now(),
            ]);

            $this->syncParentStatus((int) $item->idp_id);
        });

        return back()->with('success', 'อนุมัติแผนสมรรถนะแล้ว');
    }

    public function reject(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'idpItemId' => ['required', 'integer'],
            'comment' => ['required', 'string'],
        ]);
        $item = $this->reviewableItem((int) $validated['idpItemId']);

        DB::transaction(function () use ($item, $validated): void {
            DB::table('idp_items')->where('id', $item->id)->update([
                'status' => 'revision_required',
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'reject_comment' => trim($validated['comment']),
                'updated_at' => now(),
            ]);

            $this->syncParentStatus((int) $item->idp_id);
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
                'users.supervisor_id_1'
            )
            ->first();

        if (! $item || (int) $item->supervisor_id_1 !== (int) auth()->id() || $item->status !== 'submitted') {
            throw ValidationException::withMessages([
                'idpItemId' => 'คุณไม่มีสิทธิ์ตรวจแผนสมรรถนะนี้ หรือแผนไม่ได้อยู่ในสถานะรอตรวจ',
            ]);
        }

        return $item;
    }

    private function syncParentStatus(int $idpId): void
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
            'updated_at' => now(),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ReviewerChainResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HierarchyController extends Controller
{
    public function __construct(private ReviewerChainResolver $reviewerChainResolver)
    {
    }

    // ดึงสายการบังคับบัญชาทั้งหมด
    public function index()
    {
        $users = User::with(['role'])
            ->select('id', 'sso', 'name', 'first_name_th', 'last_name_th', 'role_id', 'department', 'position')
            ->where('is_active', true)
            ->get()
            ->map(function (User $user) {
                $reviewerSteps = $this->reviewerChainResolver->payloadForUser($user);

                return [
                    'id' => $user->id,
                    'sso' => $user->sso,
                    'name' => $user->name,
                    'first_name_th' => $user->first_name_th,
                    'last_name_th' => $user->last_name_th,
                    'role_key' => $user->role?->key,
                    'reviewerSteps' => $reviewerSteps,
                    'supervisorChain' => $reviewerSteps,
                    'evaluator1_name' => $reviewerSteps[0]['name'] ?? '',
                    'evaluator2_name' => $reviewerSteps[1]['name'] ?? '',
                    'evaluator3_name' => $reviewerSteps[2]['name'] ?? '',
                    'department' => $user->department,
                    'position' => $user->position,
                ];
            });

        return response()->json($users);
    }

    // ดึงรายชื่อตาม role (ไว้ใช้ใน dropdown)
    public function byRole(string $roleKey)
    {
        $users = User::with('role')
            ->select('id', 'sso', 'name', 'first_name_th', 'last_name_th', 'role_id', 'position')
            ->whereHas('role', fn ($query) => $query->where('key', $roleKey))
            ->where('is_active', true)
            ->get()
            ->map(fn (User $user) => [
                'sso' => $user->sso,
                'name' => $user->name,
                'first_name_th' => $user->first_name_th,
                'last_name_th' => $user->last_name_th,
                'role_key' => $user->role?->key,
                'position' => $user->position,
            ]);

        return response()->json($users);
    }

    // อัพเดทสายการบังคับบัญชาของ user คนนึง
    public function update(Request $request, string $sso)
    {
        $request->validate([
            'reviewer_ids' => 'nullable|array',
            'reviewer_ids.*' => 'nullable|integer|exists:users,id',
        ]);

        $user = User::where('sso', $sso)->firstOrFail();
        $rawReviewerIds = $request->input('reviewer_ids') ?? [];

        $reviewerIds = collect($rawReviewerIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (in_array((int) $user->id, $reviewerIds, true)) {
            return response()->json([
                'message' => 'ไม่สามารถเลือกผู้ใช้นี้เป็นผู้ประเมินของตัวเองได้',
            ], 422);
        }

        $this->syncReviewerSteps($user, $reviewerIds);

        return response()->json([
            'message' => 'Hierarchy updated',
            'user'    => $user,
        ]);
    }

    // อัพเดท orgSups (หัวหน้าฝ่าย/งาน)
    public function updateOrgSup(Request $request)
    {
        $request->validate([
            'path'       => 'required|string',
            'supervisor_id' => 'required|integer|exists:users,id',
        ]);

        // อัพเดท user ทุกคนที่อยู่ใน department/work นั้น
        $path = $request->path;
        $parts = explode(' > ', $path);

        if (count($parts) === 1) {
            // อัพเดทระดับ dept → เปลี่ยน evaluator2
            $this->setReviewerStepForQuery(
                User::where('department', 'LIKE', $parts[0] . '%'),
                2,
                (int) $request->supervisor_id
            );
        } elseif (count($parts) === 2) {
            // อัพเดทระดับ work → เปลี่ยน supervisor
            $this->setReviewerStepForQuery(
                User::where('department', 'LIKE', $parts[0] . ' > ' . $parts[1] . '%'),
                1,
                (int) $request->supervisor_id
            );
        }

        return response()->json(['message' => 'OrgSup updated']);
    }

    private function displayNameForUser(?User $user): string
    {
        if (! $user) {
            return '';
        }

        return trim(($user->title ?: '').$user->name);
    }

    private function setReviewerStepForQuery($query, int $step, int $reviewerId): void
    {
        $userIds = (clone $query)->pluck('id');

        if (! Schema::hasTable('user_reviewer_steps')) {
            return;
        }

        $now = now();
        foreach ($userIds as $userId) {
            if ((int) $userId === $reviewerId) {
                continue;
            }

            DB::table('user_reviewer_steps')
                ->where('user_id', (int) $userId)
                ->where('reviewer_id', $reviewerId)
                ->where('step_order', '!=', $step)
                ->when(Schema::hasColumn('user_reviewer_steps', 'chain_type'), fn ($query) => $query->where('chain_type', 'assessment'))
                ->delete();

            $keys = [
                'user_id' => (int) $userId,
                'step_order' => $step,
            ];
            $values = [
                'reviewer_id' => $reviewerId,
                'updated_at' => $now,
                'created_at' => $now,
            ];
            if (Schema::hasColumn('user_reviewer_steps', 'chain_type')) {
                $keys['chain_type'] = 'assessment';
            }

            DB::table('user_reviewer_steps')->updateOrInsert(
                $keys,
                $values
            );
        }
    }

    private function syncReviewerSteps(User $user, array $reviewerIds): void
    {
        if (! Schema::hasTable('user_reviewer_steps')) {
            return;
        }

        $deleteQuery = DB::table('user_reviewer_steps')->where('user_id', $user->id);
        if (Schema::hasColumn('user_reviewer_steps', 'chain_type')) {
            $deleteQuery->where('chain_type', 'assessment');
        }
        $deleteQuery->delete();

        $now = now();
        $rows = collect($reviewerIds)
            ->reject(fn (int $reviewerId): bool => $reviewerId === (int) $user->id)
            ->values()
            ->map(function (int $reviewerId, int $index) use ($user, $now): array {
                $row = [
                    'user_id' => $user->id,
                    'step_order' => $index + 1,
                    'reviewer_id' => $reviewerId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (Schema::hasColumn('user_reviewer_steps', 'chain_type')) {
                    $row['chain_type'] = 'assessment';
                }

                return $row;
            })
            ->all();

        if ($rows !== []) {
            DB::table('user_reviewer_steps')->insert($rows);
        }
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OrganizationChartService;
use App\Services\OrganizationReviewerDefaults;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationChartController extends Controller
{
    public function update(Request $request, OrganizationChartService $chart)
    {
        $data = $request->validate([
            'node_key' => ['required', 'string'],
            'head_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'use_profile_members' => ['required', 'boolean'],
            'member_ids' => ['present', 'array'],
            'member_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);
        $updatedReviewerChains = 0;
        DB::transaction(function () use ($data, $chart, &$updatedReviewerChains) {
            $node = $chart->node($data['node_key']);
            $previousChart = $chart->payload();
            $previousHeadId = DB::table('organization_chart_assignments')
                ->where('node_key', $node['key'])
                ->value('head_user_id');
            $newHeadId = filled($data['head_user_id'] ?? null) ? (int) $data['head_user_id'] : null;
            // Lock the existing organization row against concurrent deletion.
            DB::table(OrganizationChartService::TABLES[$node['type']])->where('id', $node['id'])->lockForUpdate()->first();
            DB::table('organization_chart_assignments')->updateOrInsert(['node_key' => $node['key']], [
                'head_user_id' => $data['head_user_id'] ?? null,
                'members_overridden' => !$data['use_profile_members'],
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('organization_chart_members')->where('node_key', $node['key'])->delete();
            if (!$data['use_profile_members']) {
                foreach ($data['member_ids'] as $id) {
                    DB::table('organization_chart_members')->insert(['node_key' => $node['key'], 'user_id' => $id]);
                }
            }

            if ((int) $previousHeadId !== (int) $newHeadId) {
                $currentChart = $chart->payload();
                $previousDefaults = app(OrganizationReviewerDefaults::class);
                $previousNodeMembers = $this->descendantMemberIds($previousChart, $node['key']);
                $users = User::query()->whereIn('id', $previousNodeMembers)->get();
                foreach ($users as $user) {
                    $oldDefaultIds = $previousDefaults->forUser($user, $previousChart)['reviewerIds'];
                    $newDefaultIds = $previousDefaults->forUser($user, $currentChart)['reviewerIds'];
                    $currentIds = DB::table('user_reviewer_steps')
                        ->where('user_id', $user->id)
                        ->where('chain_type', 'assessment')
                        ->orderBy('step_order')
                        ->pluck('reviewer_id')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    if ($currentIds === []) {
                        $nextIds = $newDefaultIds;
                    } elseif ($currentIds === $oldDefaultIds) {
                        $nextIds = $newDefaultIds;
                    } elseif ($previousHeadId && in_array((int) $previousHeadId, $currentIds, true)) {
                        $nextIds = array_map(
                            fn (int $id): int => $id === (int) $previousHeadId && $newHeadId ? $newHeadId : $id,
                            $currentIds,
                        );
                        if (! $newHeadId) {
                            $nextIds = array_values(array_filter($nextIds, fn (int $id): bool => $id !== (int) $previousHeadId));
                        }
                        $nextIds = array_values(array_unique($nextIds));
                    } else {
                        continue;
                    }

                    if ($currentIds === $nextIds) {
                        continue;
                    }
                    $grants = \App\Services\ReviewerChainResolver::storedScoringByReviewer((int) $user->id, 'assessment');
                    DB::table('user_reviewer_steps')->where('user_id', $user->id)->where('chain_type', 'assessment')->delete();
                    if ($nextIds !== []) {
                        $now = now();
                        DB::table('user_reviewer_steps')->insert(collect($nextIds)->values()->map(fn (int $reviewerId, int $index): array => [
                            'user_id' => $user->id,
                            'step_order' => $index + 1,
                            'reviewer_id' => $reviewerId,
                            'chain_type' => 'assessment',
                            'scoring_types' => $grants[$reviewerId] ?? null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])->all());
                    }
                    $updatedReviewerChains++;
                }
            }
        });
        $message = 'บันทึกหัวหน้าและสมาชิกในแผนผังแล้ว';
        if ($updatedReviewerChains > 0) {
            $message .= " และอัปเดตสายการประเมินของบุคลากร {$updatedReviewerChains} คนแล้ว";
        }
        return back()->with('success', $message);
    }

    private function descendantMemberIds(array $chart, string $nodeKey): array
    {
        $nodes = collect($chart['nodes'])->keyBy('key');
        $children = $nodes->groupBy('parentKey');
        $collectIds = function (string $key) use (&$collectIds, $nodes, $children): array {
            $ids = collect($nodes->get($key)['members'] ?? [])->pluck('id')->map(fn ($id): int => (int) $id);
            foreach ($children->get($key, collect()) as $child) {
                $ids = $ids->merge($collectIds($child['key']));
            }
            return $ids->unique()->values()->all();
        };

        return $collectIds($nodeKey);
    }
}

<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReviewerChainWriter
{
    /**
     * Replaces one reviewer chain of a user.
     *
     * @param  list<int|string>  $reviewerIds  ordered reviewer ids
     * @param  array<int|string, array<int, string>>|null  $scoring  reviewer_id => competency types the reviewer may
     *                                                               score (assessment chain only). null keeps the
     *                                                               grants already stored.
     */
    public function sync(User $user, array $reviewerIds, string $chainType, ?array $scoring = null): void
    {
        if (! Schema::hasTable('user_reviewer_steps')) {
            return;
        }

        $stored = ReviewerChainResolver::storedScoringByReviewer((int) $user->id, $chainType);

        $deleteQuery = DB::table('user_reviewer_steps')->where('user_id', $user->id);
        if (Schema::hasColumn('user_reviewer_steps', 'chain_type')) {
            $deleteQuery->where('chain_type', $chainType);
        }
        $deleteQuery->delete();

        $now = now();
        $rows = collect($reviewerIds)
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->map(function (int $reviewerId, int $index) use ($user, $chainType, $now, $scoring, $stored): array {
                $row = [
                    'user_id' => $user->id,
                    'step_order' => $index + 1,
                    'reviewer_id' => $reviewerId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($chainType === 'assessment') {
                    $row['scoring_types'] = $scoring !== null
                        ? (implode(',', ReviewerChainResolver::parseScoringTypes(implode(',', $scoring[$reviewerId] ?? []))) ?: null)
                        : ($stored[$reviewerId] ?? null);
                }

                if (Schema::hasColumn('user_reviewer_steps', 'chain_type')) {
                    $row['chain_type'] = $chainType;
                }

                return $row;
            })
            ->all();

        if ($rows !== []) {
            DB::table('user_reviewer_steps')->insert($rows);
        }
    }

    /** @return list<int> reviewer ids of one chain, in step order */
    public function reviewerIds(int $userId, string $chainType): array
    {
        return DB::table('user_reviewer_steps')
            ->where('user_id', $userId)
            ->where('chain_type', $chainType)
            ->orderBy('step_order')
            ->pluck('reviewer_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}

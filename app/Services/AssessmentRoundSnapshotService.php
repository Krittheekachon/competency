<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AssessmentRoundSnapshotService
{
    public function captureIfMissing(User $user, int $roundId): void
    {
        if ($roundId <= 0 || ! Schema::hasTable('user_round_profiles')) {
            return;
        }

        DB::transaction(function () use ($user, $roundId): void {
            $created = DB::table('user_round_profiles')->insertOrIgnore([
                'assessment_round_id' => $roundId,
                'user_id' => $user->id,
                'position_id' => $user->position_id,
                'level_id' => $user->level_id,
                'workline' => $user->workline,
                'department' => $user->department,
                'position' => $user->position,
                'level' => $user->level,
                'captured_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (! $created || ! Schema::hasTable('user_round_reviewer_steps')) {
                return;
            }

            $steps = DB::table('user_reviewer_steps')
                ->where('user_id', $user->id)
                ->orderBy('chain_type')
                ->orderBy('step_order')
                ->get(['chain_type', 'step_order', 'reviewer_id'])
                ->map(fn (object $step): array => [
                    'assessment_round_id' => $roundId,
                    'user_id' => $user->id,
                    'chain_type' => $step->chain_type,
                    'step_order' => $step->step_order,
                    'reviewer_id' => $step->reviewer_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all();

            if ($steps !== []) {
                DB::table('user_round_reviewer_steps')->insertOrIgnore($steps);
            }
        });
    }

    public function userForRound(User $user, int $roundId): User
    {
        if ($roundId <= 0 || ! Schema::hasTable('user_round_profiles')) {
            return $user;
        }

        $profile = DB::table('user_round_profiles')
            ->where('assessment_round_id', $roundId)
            ->where('user_id', $user->id)
            ->first();

        if (! $profile) {
            return $user;
        }

        $snapshot = clone $user;
        $snapshot->forceFill([
            'position_id' => $profile->position_id,
            'level_id' => $profile->level_id,
            'workline' => $profile->workline,
            'department' => $profile->department,
            'position' => $profile->position,
            'level' => $profile->level,
        ]);
        $snapshot->setAttribute('assessment_round_id', $roundId);

        return $snapshot;
    }
}

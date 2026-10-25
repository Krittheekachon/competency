<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReviewerChainResolver
{
    private ?bool $hasReviewerStepsTable = null;

    private ?bool $hasChainTypeColumn = null;

    public function stepsForUser(
        object $user,
        string $chainType = 'assessment',
        bool $allowUncapturedSnapshot = false,
    ): array
    {
        $userId = (int) ($user->user_id ?? $user->id ?? 0);
        $roundId = (int) ($user->assessment_round_id ?? 0) ?: null;

        if ($userId > 0 && $roundId) {
            if (! Schema::hasTable('assessment_rounds')
                || ! Schema::hasColumn('assessment_rounds', 'snapshot_required')) {
                return [];
            }

            $round = DB::table('assessment_rounds')
                ->where('id', $roundId)
                ->first(['snapshot_required']);

            if (! $round) {
                return [];
            }

            $hasProfileSnapshot = Schema::hasTable('user_round_profiles')
                && DB::table('user_round_profiles')
                    ->where('assessment_round_id', $roundId)
                    ->where('user_id', $userId)
                    ->exists();

            $hasReviewerSnapshots = Schema::hasTable('user_round_reviewer_steps')
                && DB::table('user_round_reviewer_steps')
                    ->where('assessment_round_id', $roundId)
                    ->where('user_id', $userId)
                    ->exists();

            if ($hasProfileSnapshot) {
                if (! Schema::hasTable('user_round_reviewer_steps')) {
                    return [];
                }

                return DB::table('user_round_reviewer_steps')
                    ->where('assessment_round_id', $roundId)
                    ->where('user_id', $userId)
                    ->where('chain_type', $chainType)
                    ->orderBy('step_order')
                    ->get(['step_order', 'reviewer_id'])
                    ->map(fn (object $row): array => [
                        'step' => (int) $row->step_order,
                        'reviewer_id' => (int) $row->reviewer_id,
                    ])
                    ->all();
            }

            // An orphaned reviewer snapshot indicates incomplete round data; never substitute current assignments.
            if ($hasReviewerSnapshots) {
                return [];
            }

            // Only legacy rounds or the pre-capture assessment guard may read current assignments.
            if (filter_var($round->snapshot_required, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false
                && ! $allowUncapturedSnapshot) {
                return [];
            }
        }

        if ($userId > 0 && $this->hasReviewerStepsTable()) {
            $rows = $this->rowsForUser($userId, $chainType);

            if ($rows->isNotEmpty()) {
                return $rows
                    ->map(fn (object $row): array => [
                        'step' => (int) $row->step_order,
                        'reviewer_id' => (int) $row->reviewer_id,
                    ])
                    ->filter(fn (array $step): bool => $step['step'] > 0 && $step['reviewer_id'] > 0)
                    ->values()
                    ->all();
            }
        }

        return [];
    }

    public function payloadForUser(User $user, string $chainType = 'assessment'): array
    {
        $steps = $this->stepsForUser($user, $chainType);

        if ($steps === []) {
            return [];
        }

        $reviewers = User::query()
            ->with('role')
            ->whereIn('id', collect($steps)->pluck('reviewer_id'))
            ->get()
            ->keyBy('id');

        return $this->payloadFromSteps($steps, $reviewers, $chainType);
    }

    public function payloadsForUsers(iterable $users, string $chainType = 'assessment'): array
    {
        $users = collect($users)->keyBy(fn (object $user): int => (int) $user->id);

        if ($users->isEmpty() || ! $this->hasReviewerStepsTable()) {
            return [];
        }

        $query = DB::table('user_reviewer_steps')
            ->whereIn('user_id', $users->keys())
            ->orderBy('user_id')
            ->orderBy('step_order');

        if ($this->hasChainTypeColumn()) {
            $query->where('chain_type', $chainType);
        } elseif ($chainType !== 'assessment') {
            return $users->mapWithKeys(fn (object $user): array => [(int) $user->id => []])->all();
        }

        $rowsByUser = $query->get(['user_id', 'step_order', 'reviewer_id'])->groupBy('user_id');
        $reviewers = User::query()
            ->with('role')
            ->whereIn('id', $rowsByUser->flatten(1)->pluck('reviewer_id')->unique())
            ->get()
            ->keyBy('id');

        return $users->mapWithKeys(function (object $user) use ($rowsByUser, $reviewers, $chainType): array {
            $steps = collect($rowsByUser->get((int) $user->id, []))
                ->map(fn (object $row): array => [
                    'step' => (int) $row->step_order,
                    'reviewer_id' => (int) $row->reviewer_id,
                ])
                ->filter(fn (array $step): bool => $step['step'] > 0 && $step['reviewer_id'] > 0)
                ->values()
                ->all();

            return [(int) $user->id => $this->payloadFromSteps($steps, $reviewers, $chainType)];
        })->all();
    }

    private function payloadFromSteps(array $steps, Collection $reviewers, string $chainType): array
    {
        return collect($steps)
            ->map(function (array $step) use ($reviewers, $chainType): ?array {
                $reviewer = $reviewers->get($step['reviewer_id']);

                if (! $reviewer) {
                    return null;
                }

                return [
                    'id' => (int) $reviewer->id,
                    'chainType' => $chainType,
                    'step' => (int) $step['step'],
                    'label' => 'ผู้ประเมินลำดับ '.(int) $step['step'],
                    'name' => trim(($reviewer->title ?: '').$reviewer->name),
                    'position' => $reviewer->position ?: '',
                    'role' => $this->normalizeRoleKey((string) ($reviewer->role?->key ?? '')),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function firstReviewerId(object $user, string $chainType = 'assessment'): ?int
    {
        $firstStep = $this->stepsForUser($user, $chainType)[0] ?? null;

        return $firstStep ? (int) $firstStep['reviewer_id'] : null;
    }

    public function stepForReviewer(object $user, int $reviewerId, string $chainType = 'assessment'): ?int
    {
        foreach ($this->stepsForUser($user, $chainType) as $step) {
            if ((int) $step['reviewer_id'] === $reviewerId) {
                return (int) $step['step'];
            }
        }

        return null;
    }

    public function userIdsForReviewer(object $reviewer, string $chainType = 'assessment'): array
    {
        $reviewerId = (int) ($reviewer->reviewer_id ?? $reviewer->id ?? 0);

        if ($reviewerId <= 0 || ! $this->hasReviewerStepsTable()) {
            return [];
        }

        $query = DB::table('user_reviewer_steps')
            ->where('reviewer_id', $reviewerId);

        if ($this->hasChainTypeColumn()) {
            $query->where('chain_type', $chainType);
        }

        $currentUserIds = $query
            ->distinct()
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id);

        $snapshotUserIds = Schema::hasTable('user_round_reviewer_steps')
            ? DB::table('user_round_reviewer_steps')
                ->where('reviewer_id', $reviewerId)
                ->where('chain_type', $chainType)
                ->distinct()
                ->pluck('user_id')
                ->map(fn ($id): int => (int) $id)
            : collect();

        return $currentUserIds->merge($snapshotUserIds)->unique()->sort()->values()->all();
    }

    public function pendingStatusForStep(int $step): string
    {
        return match ($step) {
            1 => 'self_submitted',
            2 => 'unit_evaluated',
            3 => 'dept_evaluated',
            default => 'review_step_'.$step,
        };
    }

    public function nextStatusAfterStep(object $user, int $currentStep, string $chainType = 'assessment'): string
    {
        foreach ($this->stepsForUser($user, $chainType) as $step) {
            if ((int) $step['step'] > $currentStep) {
                return $this->pendingStatusForStep((int) $step['step']);
            }
        }

        return 'approved';
    }

    public function submittedAtColumnForStep(int $step): string
    {
        return match ($step) {
            1 => 'supervisor_1_submitted_at',
            2 => 'supervisor_2_submitted_at',
            3 => 'dean_approved_at',
            default => 'updated_at',
        };
    }

    private function rowsForUser(int $userId, string $chainType)
    {
        $query = DB::table('user_reviewer_steps')
            ->where('user_id', $userId);

        if ($this->hasChainTypeColumn()) {
            $query->where('chain_type', $chainType);
        }

        return $query
            ->orderBy('step_order')
            ->get(['step_order', 'reviewer_id']);
    }

    private function hasReviewerStepsTable(): bool
    {
        return $this->hasReviewerStepsTable ??= Schema::hasTable('user_reviewer_steps');
    }

    private function hasChainTypeColumn(): bool
    {
        return $this->hasChainTypeColumn ??= $this->hasReviewerStepsTable()
            && Schema::hasColumn('user_reviewer_steps', 'chain_type');
    }

    private function normalizeRoleKey(string $roleKey): string
    {
        return match ($roleKey) {
            'manager' => 'dean',
            'manager_dept' => 'dept_head',
            default => $roleKey,
        };
    }
}

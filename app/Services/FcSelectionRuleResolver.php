<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves how many FC topics a user must pre-select in a round.
 *
 * A unit rule (position_fc_unit_rules) for the user's support unit (position unit, else the
 * users.department path) overrides the
 * position rule (position_fc_selection_rules). FC choices always come from the position.
 */
class FcSelectionRuleResolver
{
    public function __construct(
        private PositionTitleResolver $positionTitles,
        private SupportUnitResolver $supportUnits,
    ) {}

    public function requiredCount(int $positionTitleId, ?int $positionId, int $roundId, ?string $department = null): int
    {
        return $this->requiredCountsForUsers(collect([(object) [
            'id' => 0,
            'position_id' => $positionId,
            'position_title_id' => $positionTitleId,
            'department' => $department,
        ]]), $roundId)->get(0, 0);
    }

    /**
     * @param  Collection<int, object>  $users  rows with id, position_id and optionally position_title_id and department
     * @return Collection<int, int> required count keyed by user id
     */
    public function requiredCountsForUsers(Collection $users, int $roundId): Collection
    {
        if ($users->isEmpty() || $roundId <= 0) {
            return collect();
        }

        // Global support positions carry no unit; the unit then comes from users.department.
        $context = $users->mapWithKeys(fn (object $user): array => [
            (int) $user->id => [
                'titleId' => $this->positionTitleId($user),
                'unitId' => (int) ($this->supportUnits->unitIdFor(
                    $user->position_id ? (int) $user->position_id : null,
                    $user->department ?? null,
                ) ?? 0),
            ],
        ]);
        $titleIds = $context->pluck('titleId')->filter()->unique()->values();

        if ($titleIds->isEmpty()) {
            return $users->mapWithKeys(fn (object $user): array => [(int) $user->id => 0]);
        }

        $positionRules = DB::table('position_fc_selection_rules')
            ->where('assessment_round_id', $roundId)
            ->whereIn('position_title_id', $titleIds)
            ->pluck('required_fc_count', 'position_title_id');
        $unitRules = DB::table('position_fc_unit_rules')
            ->where('assessment_round_id', $roundId)
            ->whereIn('position_title_id', $titleIds)
            ->get(['position_title_id', 'support_unit_id', 'required_fc_count'])
            ->mapWithKeys(fn (object $rule): array => [
                $rule->position_title_id.':'.$rule->support_unit_id => (int) $rule->required_fc_count,
            ]);

        return $context->map(function (array $item) use ($positionRules, $unitRules): int {
            if (! $item['titleId']) {
                return 0;
            }

            return $unitRules->get($item['titleId'].':'.$item['unitId'])
                ?? (int) ($positionRules[$item['titleId']] ?? 0);
        });
    }

    private function positionTitleId(object $user): ?int
    {
        if (! empty($user->position_title_id)) {
            return (int) $user->position_title_id;
        }

        return ! empty($user->position_id)
            ? $this->positionTitles->forPositionId((int) $user->position_id)
            : null;
    }
}

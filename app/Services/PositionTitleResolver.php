<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PositionTitleResolver
{
    public function forUser(User $user): ?int
    {
        if ($user->position_title_id) {
            return (int) $user->position_title_id;
        }

        if ($user->position_id && ($id = $this->forPositionId((int) $user->position_id))) {
            return $id;
        }

        $name = $user->sso_position_name ?: $user->position;

        return $this->forName($name, $user->sso_workline_type ?: $this->worklineType($user->workline));
    }

    public function forPositionId(int $positionId): ?int
    {
        $position = DB::table('positions')
            ->join('job_families', 'positions.job_family_id', '=', 'job_families.id')
            ->leftJoin('worklines', 'job_families.workline_id', '=', 'worklines.id')
            ->where('positions.id', $positionId)
            ->first(['positions.id', 'positions.position_title_id', 'positions.name', 'worklines.name as workline_name']);

        if (! $position) {
            return null;
        }

        if ($position->position_title_id) {
            return (int) $position->position_title_id;
        }

        $normalized = $this->normalize($position->name);
        $worklineType = $this->worklineType($position->workline_name);
        DB::table('position_titles')->insertOrIgnore([
            'workline_type' => $worklineType,
            'name' => $position->name,
            'normalized_name' => $normalized,
            'position_type_name' => null,
            'normalized_position_type_name' => '',
            'source' => 'legacy',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = DB::table('position_titles')
            ->where('workline_type', $worklineType)
            ->where('normalized_position_type_name', '')
            ->where('normalized_name', $normalized)
            ->value('id');

        if ($id) {
            DB::table('positions')->where('id', $positionId)->update(['position_title_id' => $id]);
            DB::table('users')->where('position_id', $positionId)->whereNull('position_title_id')->update([
                'position_title_id' => $id,
            ]);
            foreach (['position_competencies', 'position_fc_selection_rules', 'fc_topic_selections'] as $table) {
                DB::table($table)->where('position_id', $positionId)->whereNull('position_title_id')->update([
                    'position_title_id' => $id,
                ]);
            }
        }

        return $id ? (int) $id : null;
    }

    public function forName(?string $name, ?string $worklineType = null): ?int
    {
        $normalized = $this->normalize($name);
        if ($normalized === '') {
            return null;
        }

        $ids = DB::table('position_titles')
            ->where('normalized_name', $normalized)
            ->when($worklineType, fn ($query) => $query->where('workline_type', $worklineType))
            ->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    public function positionIds(int $positionTitleId): array
    {
        return DB::table('positions')
            ->where('position_title_id', $positionTitleId)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function normalize(?string $value): string
    {
        return Str::lower(trim((string) preg_replace('/\s+/u', ' ', trim((string) $value))));
    }

    private function worklineType(?string $workline): ?string
    {
        $value = preg_replace('/\s+/u', '', trim((string) $workline));

        return match ($value) {
            'วิชาการ', 'สายวิชาการ', 'สายงานวิชาการ' => 'academic',
            'สนับสนุน', 'สายสนับสนุน', 'สายงานสนับสนุน' => 'support',
            default => null,
        };
    }
}

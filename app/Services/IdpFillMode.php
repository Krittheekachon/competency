<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class IdpFillMode
{
    public function current(): string
    {
        $mode = DB::table('system_settings')->where('key', 'idp_fill_mode')->value('value');
        return in_array($mode, ['quick', 'form'], true) ? $mode : 'quick';
    }

    public function save(string $mode): void
    {
        DB::table('system_settings')->upsert([
            ['key' => 'idp_fill_mode', 'value' => $mode, 'created_at' => now(), 'updated_at' => now()],
        ], ['key'], ['value', 'updated_at']);
    }

    public function fromActivities(iterable $activities): ?string
    {
        $found = false;
        foreach ($activities as $activity) {
            $found = true;
            $details = is_array($activity) ? ($activity['formDetails'] ?? []) : ($activity->form_details ?? []);
            if (is_string($details)) $details = json_decode($details, true) ?: [];
            if (($details['_behaviorPlan'] ?? false) !== true) return 'form';
        }
        return $found ? 'quick' : null;
    }

    public function forUserGap(int $userId, int $gapId): string
    {
        $itemId = DB::table('idp_items')->join('idps', 'idps.id', '=', 'idp_items.idp_id')
            ->where('idps.user_id', $userId)->where('idp_items.competency_gap_id', $gapId)
            ->orderByDesc('idp_items.id')->value('idp_items.id');
        return ($itemId ? $this->fromActivities(DB::table('idp_activities')->where('idp_item_id', $itemId)->get(['form_details'])) : null) ?? $this->current();
    }
}

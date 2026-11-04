<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationChartService
{
    public const TABLES = ['workline' => 'worklines', 'family' => 'job_families', 'department' => 'support_departments', 'work' => 'support_works', 'unit' => 'support_units'];

    public function payload(): array
    {
        $nodes = [];
        $worklines = DB::table('worklines')->orderBy('name')->get();
        $support = $worklines->first(fn ($row) => in_array($row->name, ['สายสนับสนุน', 'สายงานสนับสนุน'], true));
        foreach (self::TABLES as $type => $table) {
            foreach (DB::table($table)->orderBy('name')->get() as $row) {
                // Support job families are position categories, not organizational departments.
                if ($type === 'family' && $support && $row->workline_id === $support->id) {
                    continue;
                }
                $parent = match ($type) {
                    'family' => $row->workline_id ? 'workline:'.$row->workline_id : null,
                    'department' => $support ? 'workline:'.$support->id : null,
                    'work' => 'department:'.$row->support_department_id,
                    'unit' => 'work:'.$row->support_work_id,
                    default => null,
                };
                $key = $type.':'.$row->id;
                $nodes[$key] = ['key' => $key, 'id' => $row->id, 'type' => $type, 'name' => $row->name, 'parentKey' => $parent, 'head' => null, 'members' => [], 'membershipSource' => 'profile'];
            }
        }
        $people = DB::table('users')->orderBy('name')->get(['id', 'name', 'title', 'workline', 'department', 'position', 'position_id', 'is_active']);
        $personPayload = fn ($user) => ['id' => $user->id, 'name' => trim(($user->title ?? '').$user->name), 'position' => $user->position, 'active' => (bool) $user->is_active];
        $peopleById = $people->keyBy('id');
        $presenter = app(UserOrganizationPresenter::class);
        foreach ($people as $user) {
            $org = $presenter->forUser($user);
            $candidates = collect($nodes)->filter(function ($node) use ($user, $org, $nodes) {
                $path = [];
                $current = $node;
                while ($current) {
                    $path[$current['type']] = $current['name'];
                    $current = $nodes[$current['parentKey']] ?? null;
                }
                if ($node['type'] === 'unit') {
                    return $org['unitName'] === $node['name'] && $org['workName'] === ($path['work'] ?? null) && (!$org['departmentName'] || $org['departmentName'] === ($path['department'] ?? null));
                }
                if ($node['type'] === 'work') {
                    return !$org['unitName'] && $org['workName'] === $node['name'] && $org['departmentName'] === ($path['department'] ?? null);
                }
                if ($node['type'] === 'department') {
                    return !$org['unitName'] && !$org['workName'] && $org['type'] === 'support' && $org['departmentName'] === $node['name'];
                }
                return $node['type'] === 'family' && $org['type'] === 'academic' && $org['departmentName'] === $node['name'] && $user->workline === ($path['workline'] ?? null);
            });
            // Ambiguous legacy names must not silently assign a person to the wrong unit.
            if ($candidates->count() === 1) {
                $nodes[$candidates->keys()->first()]['members'][] = $personPayload($user);
            }
        }
        $assignedMembers = DB::table('organization_chart_members')->get()->groupBy('node_key');
        foreach (DB::table('organization_chart_assignments')->get() as $assignment) {
            if (!isset($nodes[$assignment->node_key])) {
                continue;
            }
            $node = &$nodes[$assignment->node_key];
            $head = $peopleById->get($assignment->head_user_id);
            $node['head'] = $head ? $personPayload($head) : null;
            if ($assignment->members_overridden) {
                $node['membershipSource'] = 'chart';
                $node['members'] = ($assignedMembers[$assignment->node_key] ?? collect())->map(fn ($member) => $peopleById->get($member->user_id))->filter()->map($personPayload)->values()->all();
            }
            unset($node);
        }
        return ['nodes' => array_values($nodes), 'people' => $people->map($personPayload)->all()];
    }

    public function node(string $key): array
    {
        return collect($this->payload()['nodes'])->firstWhere('key', $key)
            ?? throw ValidationException::withMessages(['node_key' => 'ไม่พบหน่วยงานนี้ กรุณาโหลดข้อมูลใหม่']);
    }

    public function assertDeletable(string $key): void
    {
        // Support job families remain editable in the position catalog, outside the chart.
        if (str_starts_with($key, 'family:') && !collect($this->payload()['nodes'])->contains('key', $key)) {
            $id = (int) substr($key, 7);
            if (DB::table('positions')->where('job_family_id', $id)->exists() || DB::table('levels')->where('job_family_id', $id)->exists()) {
                throw ValidationException::withMessages(['name' => 'ลบไม่ได้: กลุ่มงานยังมีข้อมูลที่อ้างอิง']);
            }
            return;
        }
        $node = $this->node($key);
        $children = collect($this->payload()['nodes'])->contains('parentKey', $key);
        [$type, $id] = explode(':', $key);
        $linked = match ($type) {
            'workline' => DB::table('job_families')->where('workline_id', $id)->exists() || DB::table('levels')->where('workline_id', $id)->exists() || DB::table('users')->where('workline', $node['name'])->exists(),
            'family' => DB::table('positions')->where('job_family_id', $id)->exists() || DB::table('users')->where('department', $node['name'])->exists(),
            'unit' => DB::table('positions')->where('support_unit_id', $id)->exists(),
            default => false,
        };
        // Profile memberships still protect deletion even when a chart override hides them.
        $paths = [$node['name']];
        $parent = $node['parentKey'];
        while ($parent && !str_starts_with($parent, 'workline:')) {
            $ancestor = $this->node($parent);
            array_unshift($paths, $ancestor['name']);
            $parent = $ancestor['parentKey'];
        }
        $profileMembers = DB::table('users')->where('department', implode(' > ', $paths))->exists();
        if ($children || $node['members'] || $node['head'] || $linked || $profileMembers) {
            throw ValidationException::withMessages(['name' => 'ลบไม่ได้: หน่วยงานยังมีหน่วยงานย่อย หัวหน้า สมาชิก หรือข้อมูลที่อ้างอิง กรุณาจัดการข้อมูลเหล่านี้ก่อน']);
        }
    }
}

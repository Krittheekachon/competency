<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrganizationReviewerDefaults
{
    public function forUser(User $user, array $chart): array
    {
        $nodes = collect($chart['nodes'])->keyBy('key');
        $result = ['reviewerIds' => [], 'warnings' => [], 'error' => null, 'path' => []];
        $parts = array_values(array_filter(preg_split('/\s*>\s*/u', trim((string) $user->department))));
        $position = $user->position_id ? DB::table('positions')->where('id', $user->position_id)->first() : null;
        if ($position?->support_unit_id) {
            $matches = $nodes->filter(fn ($node) => $node['key'] === 'unit:'.$position->support_unit_id);
        } elseif ($position && !in_array((string) $user->workline, ['สายสนับสนุน', 'สายงานสนับสนุน'], true)
            && $nodes->has('family:'.$position->job_family_id)) {
            $matches = $nodes->filter(fn ($node) => $node['key'] === 'family:'.$position->job_family_id);
        } else {
            $matches = $nodes->filter(function ($node) use ($nodes, $parts, $user) {
                $path = []; $current = $node; $workline = null;
                while ($current) {
                    if ($current['type'] === 'workline') $workline = $current['name'];
                    else array_unshift($path, $current['name']);
                    $current = $nodes->get($current['parentKey']);
                }
                if ($node['type'] === 'family' && $workline !== $user->workline) return false;
                if (!$parts) return $node['type'] === 'workline' && $node['name'] === $user->workline;
                if ($node['type'] === 'workline') return false;
                return count($path) >= count($parts) && array_slice($path, -count($parts)) === $parts;
            });
        }
        if ($matches->count() !== 1) {
            $result['error'] = $matches->isEmpty() ? 'ไม่พบสังกัดจริงที่ตรงกับโครงสร้าง กรุณาตรวจข้อมูลสังกัดก่อน' : 'สังกัดกำกวม พบหลายหน่วยงานที่ตรงกัน กรุณาระบุฝ่าย/งาน/หน่วยให้ชัดเจน';
            return $result;
        }
        $current = $matches->first();
        while ($current) {
            $result['path'][] = $current['name'];
            $head = $current['head'];
            if (!$head) $result['warnings'][] = $current['name'].': ยังไม่ได้กำหนดหัวหน้า';
            elseif (!$head['active']) $result['warnings'][] = $current['name'].': หัวหน้าถูกระงับใช้งาน จึงไม่เติม';
            elseif ((int) $head['id'] === (int) $user->id) $result['warnings'][] = $current['name'].': ข้ามตัวผู้ถูกประเมินเอง';
            elseif (in_array((int) $head['id'], $result['reviewerIds'], true)) $result['warnings'][] = $current['name'].': หัวหน้าซ้ำกับขั้นก่อนหน้า จึงใช้เพียงครั้งเดียว';
            else $result['reviewerIds'][] = (int) $head['id'];
            $current = $nodes->get($current['parentKey']);
        }
        if (!$result['reviewerIds']) $result['error'] = 'ไม่มีหัวหน้าที่ใช้เป็นผู้ประเมินได้ รายการเดิมยังไม่ถูกแทนที่';
        return $result;
    }

    public function payload(): array
    {
        $chart = app(OrganizationChartService::class)->payload();
        return User::query()->get()->mapWithKeys(fn ($user) => [$user->id => $this->forUser($user, $chart)])->all();
    }
}

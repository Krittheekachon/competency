<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OrganizationChartService;
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
        DB::transaction(function () use ($data, $chart) {
            $node = $chart->node($data['node_key']);
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
        });
        return back()->with('success', 'บันทึกหัวหน้าและสมาชิกในแผนผังแล้ว');
    }
}

<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IdpActivityUpdateController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'activityId' => ['required', 'integer'],
            'progressNote' => ['nullable', 'string'],
            'percentComplete' => ['required', 'integer', 'min:0', 'max:100'],
            'evidenceUrl' => ['nullable', 'url', 'max:2048'],
            'evidenceDescription' => ['nullable', 'string'],
        ]);

        $activity = DB::table('idp_activities')
            ->join('idp_items', 'idp_activities.idp_item_id', '=', 'idp_items.id')
            ->join('idps', 'idp_items.idp_id', '=', 'idps.id')
            ->where('idp_activities.id', $data['activityId'])
            ->where('idps.user_id', $request->user()->id)
            ->select('idp_activities.id', 'idp_items.status')
            ->first();

        if (! $activity || $activity->status !== 'approved') {
            throw ValidationException::withMessages([
                'activityId' => 'สามารถอัปเดตความก้าวหน้าได้หลังแผนผ่านการอนุมัติครบทุกลำดับแล้ว',
            ]);
        }

        DB::table('idp_activity_updates')->insert([
            'activity_id' => $activity->id,
            'progress_note' => $data['progressNote'] ?? null,
            'percent_complete' => $data['percentComplete'],
            'evidence_url' => $data['evidenceUrl'] ?? null,
            'evidence_description' => $data['evidenceDescription'] ?? null,
            'updated_by' => $request->user()->id,
            'status' => 'saved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'บันทึกความก้าวหน้ากิจกรรมแล้ว');
    }
}

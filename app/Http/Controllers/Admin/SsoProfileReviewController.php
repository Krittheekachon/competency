<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SsoProfileReviewController extends Controller
{
    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['keep', 'restructure', 'rename'])],
        ]);

        $change = DB::table('sso_profile_changes')
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'restructure'])
            ->orderByDesc('id')
            ->first();

        if (! $change) {
            throw ValidationException::withMessages([
                'decision' => 'ไม่พบข้อมูล SSO ที่รอตรวจสอบ',
            ]);
        }

        DB::transaction(function () use ($request, $user, $change, $data): void {
            if ($data['decision'] === 'rename') {
                $this->renameCurrentPosition($user);
            }

            $status = match ($data['decision']) {
                'keep' => 'kept',
                'rename' => 'renamed',
                default => 'restructure',
            };

            DB::table('sso_profile_changes')->where('id', $change->id)->update([
                'status' => $status,
                'resolution_note' => match ($status) {
                    'kept' => 'คงข้อมูลโครงสร้างเดิม',
                    'renamed' => 'เปลี่ยนชื่อข้อมูลเดิมตาม SSO โดยคงรหัสและ workflow เดิม',
                    default => 'รอผู้ดูแลระบบจัดโครงสร้างใหม่',
                },
                'resolved_by' => $request->user()->id,
                'resolved_at' => $status === 'restructure' ? null : now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', match ($data['decision']) {
            'keep' => 'คงข้อมูลเดิมและปิดรายการตรวจสอบแล้ว',
            'rename' => 'เปลี่ยนชื่อโดยคงโครงสร้างและ workflow เดิมแล้ว',
            default => 'เลือกใช้ข้อมูลใหม่แล้ว กรุณาจัดโครงสร้างผู้ใช้ให้ครบถ้วน',
        });
    }

    private function renameCurrentPosition(User $user): void
    {
        $incomingName = trim((string) $user->sso_position_name);
        if (! $user->position_id || $incomingName === '') {
            throw ValidationException::withMessages([
                'decision' => 'ผู้ใช้นี้ยังไม่มีตำแหน่งเดิม หรือ SSO ไม่ได้ส่งชื่อตำแหน่งใหม่',
            ]);
        }

        $position = DB::table('positions')->where('id', $user->position_id)->lockForUpdate()->first();
        if (! $position) {
            throw ValidationException::withMessages([
                'decision' => 'ไม่พบตำแหน่งเดิมในโครงสร้างองค์กร',
            ]);
        }

        $duplicate = DB::table('positions')
            ->where('id', '!=', $position->id)
            ->where('job_family_id', $position->job_family_id)
            ->where('name', $incomingName)
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'decision' => 'มีชื่อตำแหน่งใหม่นี้อยู่ในโครงสร้างเดียวกันแล้ว กรุณาเลือกจัดโครงสร้างแทน',
            ]);
        }

        $titleId = $this->positionTitleIdForUser($user, $incomingName);
        DB::table('position_title_aliases')->insertOrIgnore([
            'position_title_id' => $titleId,
            'name' => $position->name,
            'normalized_name' => $this->normalizeName($position->name),
            'source' => 'internal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('positions')->where('id', $position->id)->update([
            'name' => $incomingName,
            'position_title_id' => $titleId,
            'updated_at' => now(),
        ]);
        DB::table('users')->where('position_id', $position->id)->update([
            'position' => $incomingName,
            'updated_at' => now(),
        ]);
    }

    private function positionTitleIdForUser(User $user, string $incomingName): int
    {
        $normalizedName = $this->normalizeName($incomingName);
        $normalizedType = $this->normalizeName($user->sso_position_type_name);
        $query = DB::table('position_titles')
            ->where('workline_type', $user->sso_workline_type)
            ->where('normalized_position_type_name', $normalizedType)
            ->where('normalized_name', $normalizedName);

        if ($id = $query->value('id')) {
            return (int) $id;
        }

        return (int) DB::table('position_titles')->insertGetId([
            'workline_type' => $user->sso_workline_type,
            'name' => $incomingName,
            'normalized_name' => $normalizedName,
            'position_type_name' => $user->sso_position_type_name,
            'normalized_position_type_name' => $normalizedType,
            'source' => 'sso',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function normalizeName(?string $value): string
    {
        return Str::lower(trim((string) preg_replace('/\s+/u', ' ', trim((string) $value))));
    }
}

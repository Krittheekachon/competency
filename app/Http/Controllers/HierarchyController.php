<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HierarchyController extends Controller
{
    // ดึงสายการบังคับบัญชาทั้งหมด
    public function index()
    {
        $users = User::with(['role', 'evaluatorLevel1', 'evaluatorLevel2', 'evaluatorLevel3'])
            ->select('id', 'sso', 'name', 'first_name_th', 'last_name_th', 'role_id', 'supervisor_id_1', 'supervisor_id_2', 'supervisor_id_3', 'department', 'position')
            ->where('is_active', true)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'sso' => $user->sso,
                'name' => $user->name,
                'first_name_th' => $user->first_name_th,
                'last_name_th' => $user->last_name_th,
                'role_key' => $user->role?->key,
                'supervisor_id_1' => $user->supervisor_id_1,
                'supervisor_id_2' => $user->supervisor_id_2,
                'supervisor_id_3' => $user->supervisor_id_3,
                'evaluator1_name' => $this->displayNameForUser($user->evaluatorLevel1),
                'evaluator2_name' => $this->displayNameForUser($user->evaluatorLevel2),
                'evaluator3_name' => $this->displayNameForUser($user->evaluatorLevel3),
                'department' => $user->department,
                'position' => $user->position,
            ]);

        return response()->json($users);
    }

    // ดึงรายชื่อตาม role (ไว้ใช้ใน dropdown)
    public function byRole(string $roleKey)
    {
        $users = User::with('role')
            ->select('id', 'sso', 'name', 'first_name_th', 'last_name_th', 'role_id', 'position')
            ->whereHas('role', fn ($query) => $query->where('key', $roleKey))
            ->where('is_active', true)
            ->get()
            ->map(fn (User $user) => [
                'sso' => $user->sso,
                'name' => $user->name,
                'first_name_th' => $user->first_name_th,
                'last_name_th' => $user->last_name_th,
                'role_key' => $user->role?->key,
                'position' => $user->position,
            ]);

        return response()->json($users);
    }

    // อัพเดทสายการบังคับบัญชาของ user คนนึง
    public function update(Request $request, string $sso)
    {
        $request->validate([
            'supervisor_id_1' => 'nullable|integer|exists:users,id',
            'supervisor_id_2' => 'nullable|integer|exists:users,id',
            'supervisor_id_3' => 'nullable|integer|exists:users,id',
        ]);

        $user = User::where('sso', $sso)->firstOrFail();

        $user->update([
            'supervisor_id_1' => $request->supervisor_id_1,
            'supervisor_id_2' => $request->supervisor_id_2,
            'supervisor_id_3' => $request->supervisor_id_3,
        ]);

        return response()->json([
            'message' => 'Hierarchy updated',
            'user'    => $user,
        ]);
    }

    // อัพเดท orgSups (หัวหน้าฝ่าย/งาน)
    public function updateOrgSup(Request $request)
    {
        $request->validate([
            'path'       => 'required|string',
            'supervisor_id' => 'required|integer|exists:users,id',
        ]);

        // อัพเดท user ทุกคนที่อยู่ใน department/work นั้น
        $path = $request->path;
        $parts = explode(' > ', $path);

        if (count($parts) === 1) {
            // อัพเดทระดับ dept → เปลี่ยน evaluator2
            User::where('department', 'LIKE', $parts[0] . '%')
                ->update(['supervisor_id_2' => $request->supervisor_id]);
        } elseif (count($parts) === 2) {
            // อัพเดทระดับ work → เปลี่ยน supervisor
            User::where('department', 'LIKE', $parts[0] . ' > ' . $parts[1] . '%')
                ->update(['supervisor_id_1' => $request->supervisor_id]);
        }

        return response()->json(['message' => 'OrgSup updated']);
    }

    private function displayNameForUser(?User $user): string
    {
        if (! $user) {
            return '';
        }

        return trim(($user->title ?: '').$user->name);
    }
}

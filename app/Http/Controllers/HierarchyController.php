<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HierarchyController extends Controller
{
    // ดึงสายการบังคับบัญชาทั้งหมด
    public function index()
    {
        $users = User::select('sso', 'name', 'first_name_th', 'last_name_th', 'role_key', 'supervisor', 'evaluator2', 'department', 'position')
            ->where('is_active', true)
            ->get();

        return response()->json($users);
    }

    // ดึงรายชื่อตาม role (ไว้ใช้ใน dropdown)
    public function byRole(string $roleKey)
    {
        $users = User::select('sso', 'name', 'first_name_th', 'last_name_th', 'role_key', 'position')
            ->where('role_key', $roleKey)
            ->where('is_active', true)
            ->get();

        return response()->json($users);
    }

    // อัพเดทสายการบังคับบัญชาของ user คนนึง
    public function update(Request $request, string $sso)
    {
        $request->validate([
            'supervisor'  => 'nullable|string',
            'evaluator2'  => 'nullable|string',
        ]);

        $user = User::where('sso', $sso)->firstOrFail();

        $user->update([
            'supervisor'  => $request->supervisor,
            'evaluator2'  => $request->evaluator2,
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
            'supervisor' => 'required|string',
        ]);

        // อัพเดท user ทุกคนที่อยู่ใน department/work นั้น
        $path = $request->path;
        $parts = explode(' > ', $path);

        if (count($parts) === 1) {
            // อัพเดทระดับ dept → เปลี่ยน evaluator2
            User::where('department', 'LIKE', $parts[0] . '%')
                ->update(['evaluator2' => $request->supervisor]);
        } elseif (count($parts) === 2) {
            // อัพเดทระดับ work → เปลี่ยน supervisor
            User::where('department', 'LIKE', $parts[0] . ' > ' . $parts[1] . '%')
                ->update(['supervisor' => $request->supervisor]);
        }

        return response()->json(['message' => 'OrgSup updated']);
    }
}
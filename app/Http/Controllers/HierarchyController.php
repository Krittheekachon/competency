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
        if (Schema::hasColumn('users', 'role_key')) {
            $users = User::select('sso', 'name', 'first_name_th', 'last_name_th', 'role_key', 'supervisor', 'evaluator2', 'department', 'position')
                ->where('is_active', true)
                ->get();
        } else {
            $users = DB::table('users')
                ->leftJoin('roles', 'users.role_id', '=', 'roles.'.$this->roleIdColumn())
                ->where('users.is_active', true)
                ->select(
                    'users.sso',
                    'users.name',
                    'users.first_name_th',
                    'users.last_name_th',
                    DB::raw('roles.'.$this->roleKeyColumn().' as role_key'),
                    'users.supervisor',
                    'users.evaluator2',
                    'users.department',
                    'users.position',
                )
                ->get();
        }

        return response()->json($users);
    }

    // ดึงรายชื่อตาม role (ไว้ใช้ใน dropdown)
    public function byRole(string $roleKey)
    {
        if (Schema::hasColumn('users', 'role_key')) {
            $users = User::select('sso', 'name', 'first_name_th', 'last_name_th', 'role_key', 'position')
                ->where('role_key', $roleKey)
                ->where('is_active', true)
                ->get();
        } else {
            $users = DB::table('users')
                ->join('roles', 'users.role_id', '=', 'roles.'.$this->roleIdColumn())
                ->where('roles.'.$this->roleKeyColumn(), $roleKey)
                ->where('users.is_active', true)
                ->select(
                    'users.sso',
                    'users.name',
                    'users.first_name_th',
                    'users.last_name_th',
                    DB::raw('roles.'.$this->roleKeyColumn().' as role_key'),
                    'users.position',
                )
                ->get();
        }

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

    private function roleKeyColumn(): string
    {
        return Schema::hasColumn('roles', 'role_key') ? 'role_key' : 'key';
    }

    private function roleIdColumn(): string
    {
        return Schema::hasColumn('roles', 'role_id') ? 'role_id' : 'id';
    }
}

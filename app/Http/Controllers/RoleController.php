<?php

namespace App\Http\Controllers;

use App\Models\User; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// app/Http/Controllers/RoleController.php
class RoleController extends Controller
{
    // ดู users ทั้งหมด (Admin)
    public function index()
    {
        $users = User::with('role')->get();
        return response()->json($users);
    }

    // เปลี่ยน role user (Admin)
    public function updateRole(Request $request, $id)
    {
        $request->validate([
            'role_key' => 'required|exists:roles,key',
        ]);

        $role = \DB::table('roles')
                   ->where('key', $request->role_key)
                   ->first();

        $user = User::findOrFail($id);
        $user->update([
            'role_id'  => $role->id,
        ]);

        return response()->json([
            'message' => 'Role updated',
            'user'    => $user->load('role'),
        ]);
    }

    // ดู user ตาม department (HR + Admin)
    public function byDepartment($dept)
    {
        $users = User::with('role')
                     ->where('department', $dept)
                     ->get();
        return response()->json($users);
    }
}

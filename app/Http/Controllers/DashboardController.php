<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia; 
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * จัดการหน้า Dashboard ตาม Role ID
     */
    public function index()
    {
        // ตรวจสอบว่า User ล็อกอินอยู่หรือไม่ และดึง role_id ออกมา
        $role = auth()->user()->role_id;
        $users = User::orderBy('name')->get()->map(fn (User $user) => [
            'db_id' => $user->id,
            'sso' => $user->sso ?: (string) $user->id,
            't' => $user->title ?: '',
            'n' => $user->name,
            'fn' => $user->first_name_th ?: $user->name,
            'ln' => $user->last_name_th ?: '',
            'fe' => $user->first_name_en ?: '',
            'le' => $user->last_name_en ?: '',
            'g' => $user->gender ?: 'ไม่ระบุ',
            'em' => $user->email,
            'ph' => $user->phone ?: '',
            'w' => $user->workline ?: '',
            'd' => $user->department ?: '',
            'p' => $user->position ?: '',
            'l' => $user->level ?: '',
            'r' => $user->role_key ?: $this->roleKeyFromId($user->role_id),
            'sup' => $user->supervisor ?: '',
            'evaluator2' => $user->evaluator2 ?: '',
            'act' => (bool) $user->is_active,
        ]);

        return match ($role) {
            0 => Inertia::render('Admin/Dashboard', ['users' => $users]),
            1 => Inertia::render('HR/Dashboard'),
            2 => Inertia::render('Executive/Dashboard'),
            3 => Inertia::render('Head/Dashboard'),
            4 => Inertia::render('Staff/Dashboard'),
            5 => Inertia::render('Super/Dashboard'),
            default => Inertia::render('Dashboard'),
        };
    }

    private function roleKeyFromId(int $roleId): string
    {
        return match ($roleId) {
            0 => 'admin',
            1 => 'hr',
            2 => 'manager',
            3 => 'manager_dept',
            5 => 'supervisor',
            default => 'employee',
        };
    }
}

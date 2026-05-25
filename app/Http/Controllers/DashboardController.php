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

        return match ($role) {
            0 => Inertia::render('Admin/Dashboard'),
            1 => Inertia::render('HR/Dashboard', [
                'hrSummary' => [
                    'totalUsers' => User::count(),
                    'hrUsers' => User::where('role_id', 1)->count(),
                    'staffUsers' => User::where('role_id', 4)->count(),
                    'source' => 'database',
                ],
            ]),
            2 => Inertia::render('Executive/Dashboard'),
            3 => Inertia::render('SuperV/Dashboard'),
            4 => Inertia::render('Staff/Dashboard'),
            5 => Inertia::render('Super/Dashboard'),
            default => Inertia::render('Dashboard'),
        };
    }
}

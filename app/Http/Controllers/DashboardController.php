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

        $managerSummary = [
            'totalUsers' => User::count(),
            'evaluatedUsers' => 0,
            'passedUsers' => 0,
            'failedUsers' => 0,
            'trainingNeeds' => 0,
            'pendingAssessmentApprovals' => 0,
            'pendingIdpApprovals' => 0,
            'source' => 'database',
        ];

        return match ($role) {
            0 => Inertia::render('Admin/Dashboard'),
            1 => Inertia::render('HR/Dashboard'),
            2 => Inertia::render('Executive/Dashboard', [
                'managerSummary' => $managerSummary,
                'activeCycleName' => '',
                'departmentRows' => [],
                'problemCompetencyRows' => [],
                'idpProgressRows' => [],
                'idpNoProgressRows' => [],
                'trainingNeedRows' => [],
                'assessmentApprovals' => [],
                'idpApprovals' => [],
            ]),
            3 => Inertia::render('SuperV/Dashboard'),
            4 => Inertia::render('Staff/Dashboard'),
            5 => Inertia::render('Super/Dashboard'),
            default => Inertia::render('Dashboard'),
        };
    }
}

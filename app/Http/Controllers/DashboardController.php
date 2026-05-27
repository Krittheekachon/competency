<?php

namespace App\Http\Controllers;

use App\Models\CompetencyType;
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
        $this->ensureDefaultCompetencyTypes();
        $competencyTypes = CompetencyType::orderBy('code')->get()->map(fn (CompetencyType $type) => [
            'id' => $type->id,
            'code' => $type->code,
            'fullName' => $type->full_name,
            'desc' => $type->description,
        ]);
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
            0 => Inertia::render('Admin/Dashboard', [
                'users' => $users,
                'competencyTypes' => $competencyTypes,
            ]),
            1 => Inertia::render('HR/Dashboard', [
                'hrSummary' => [
                    'totalUsers' => User::count(),
                    'hrUsers' => User::where('role_id', 1)->count(),
                    'staffUsers' => User::where('role_id', 4)->count(),
                    'source' => 'database',
                ],
            ]),
            2 => Inertia::render('Executive/Dashboard'),
            3 => Inertia::render('Head/Dashboard', ['users' => $users]),
            4 => Inertia::render('Staff/Dashboard'),
            5 => Inertia::render('Super/Dashboard', ['users' => $users]),
            default => Inertia::render('Dashboard'),
        };
    }

    private function ensureDefaultCompetencyTypes(): void
    {
        if (CompetencyType::query()->exists()) {
            return;
        }

        $defaults = [
            ['code' => 'CC', 'full_name' => 'Core Competency', 'description' => 'สมรรถนะหลักที่บุคลากรทุกตำแหน่งควรมีร่วมกัน'],
            ['code' => 'MC', 'full_name' => 'Managerial Competency', 'description' => 'สมรรถนะด้านการบริหารและภาวะผู้นำสำหรับตำแหน่งบริหารหรือหัวหน้างาน'],
            ['code' => 'FC1', 'full_name' => 'Functional Competency 1', 'description' => 'สมรรถนะเฉพาะตามสายงานหรือกลุ่มงานระดับที่ 1'],
            ['code' => 'FC2', 'full_name' => 'Functional Competency 2', 'description' => 'สมรรถนะเฉพาะตามสายงานหรือกลุ่มงานระดับที่ 2'],
        ];

        foreach ($defaults as $default) {
            CompetencyType::create($default);
        }
    }

    private function roleKeyFromId(int $roleId): string
    {
        return match ($roleId) {
            0 => 'admin',
            1 => 'hr',
            2 => 'manager',
            3 => 'dept_head',
            5 => 'supervisor',
            default => 'employee',
        };
    }
}

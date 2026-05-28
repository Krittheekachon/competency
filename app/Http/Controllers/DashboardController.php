<?php

namespace App\Http\Controllers;

use App\Models\CompetencyType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $competencyTypes = CompetencyType::orderBy('code')->get()->map(fn (CompetencyType $type) => [
            'id' => $type->id,
            'code' => $type->code,
            'fullName' => $type->full_name,
            'desc' => $type->description,
        ]);
        $competencies = $this->competencyPayload();
        $users = User::orderBy('name')->get()->map(fn (User $user) => $this->dashboardUserPayload($user));
        $activeCycleName = DB::table('assessment_rounds')
            ->where('is_active', true)
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->value('name') ?? '';
        $learningMethods = DB::table('learning_method_types')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (object $method) => [
                'key' => $method->key,
                'label' => $method->label,
                'desc' => $method->description ?? '',
            ]);
        $structureData = $this->adminStructurePayload();

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
                'competencies' => $competencies,
                ...$structureData,
                'learningMethods' => $learningMethods,
            ]),
            1 => Inertia::render('HR/Dashboard', [
                'hrSummary' => [
                    'totalUsers' => User::count(),
                    'hrUsers' => User::where('role_id', 1)->count(),
                    'staffUsers' => User::where('role_id', 4)->count(),
                    'source' => 'database',
                ],
                'overviewUsers' => User::query()
                    ->select(['name', 'email'])
                    ->get()
                    ->map(fn (User $user) => [
                        'n' => $user->name,
                        't' => '',
                        'sso' => $user->email,
                        'p' => '',
                        'w' => '',
                        'd' => '',
                        'evalStatus' => 'draft',
                        'act' => true,
                    ]),
            ]),
            2 => Inertia::render('Executive/Dashboard', [
                'users' => $users,
                'managerSummary' => $managerSummary,
                'activeCycleName' => $activeCycleName,
                'departmentRows' => [],
                'problemCompetencyRows' => [],
                'idpProgressRows' => [],
                'idpNoProgressRows' => [],
                'trainingNeedRows' => [],
                'assessmentApprovals' => [],
                'idpApprovals' => [],
            ]),
            3 => Inertia::render('Head/Dashboard', ['users' => $users]),
            4 => Inertia::render('Staff/Dashboard', [
                'currentUser' => $this->dashboardUserPayload(auth()->user()),
                'activeCycleName' => $activeCycleName,
                'learningMethods' => $learningMethods,
            ]),
            5 => Inertia::render('Super/Dashboard', ['users' => $users]),
            default => Inertia::render('Dashboard'),
        };
    }

    private function dashboardUserPayload(User $user): array
    {
        return [
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
        ];
    }

    private function adminStructurePayload(): array
    {
        $jobFamilies = DB::table('job_families')
            ->leftJoin('worklines', 'job_families.workline_id', '=', 'worklines.id')
            ->select('job_families.id', 'job_families.name', 'worklines.name as workline_name')
            ->orderBy('worklines.name')
            ->orderBy('job_families.name')
            ->get();

        $positionsByFamily = DB::table('positions')
            ->select('job_family_id', 'name')
            ->orderBy('name')
            ->get()
            ->groupBy('job_family_id');

        $jobFamiliesWithPositions = $jobFamilies->map(fn (object $family) => [
            'id' => $family->id,
            'name' => $family->name,
            'worklineName' => $family->workline_name,
            'positions' => ($positionsByFamily[$family->id] ?? collect())
                ->pluck('name')
                ->values(),
        ]);

        return [
            'worklines' => DB::table('worklines')->orderByDesc('id')->pluck('name'),
            'jobFamiliesByWorkline' => $jobFamiliesWithPositions
                ->groupBy('worklineName')
                ->map(fn ($families) => $families->mapWithKeys(fn (array $family) => [
                    $family['name'] => $family['positions'],
                ]))
                ->all(),
            'academicJobFamilies' => $jobFamiliesWithPositions
                ->where('worklineName', 'สายวิชาการ')
                ->pluck('name')
                ->values(),
            'supportJobFamilies' => $jobFamiliesWithPositions
                ->where('worklineName', 'สายสนับสนุน')
                ->pluck('name')
                ->values(),
            'adminJobFamilies' => $jobFamiliesWithPositions
                ->where('worklineName', 'สายงานบริหาร')
                ->pluck('name')
                ->values(),
            'supportPositionGroups' => $jobFamiliesWithPositions
                ->where('worklineName', 'สายสนับสนุน')
                ->mapWithKeys(fn (array $family) => [$family['name'] => $family['positions']])
                ->all(),
            'levels' => DB::table('levels')->orderBy('name')->pluck('name'),
            'levelsByWorkline' => DB::table('levels')
                ->leftJoin('worklines', 'levels.workline_id', '=', 'worklines.id')
                ->select('levels.name', 'worklines.name as workline_name')
                ->orderBy('levels.name')
                ->get()
                ->filter(fn (object $level) => $level->workline_name !== null)
                ->groupBy('workline_name')
                ->map(fn ($levels) => $levels->pluck('name')->values())
                ->all(),
        ];
    }

    private function competencyPayload()
    {
        return DB::table('competencies')
            ->leftJoin('competency_types', 'competencies.competency_type_id', '=', 'competency_types.id')
            ->select(
                'competencies.id',
                'competencies.competency_type_id',
                'competencies.code',
                'competencies.name',
                'competencies.detail',
                'competency_types.code as type_code'
            )
            ->orderBy('competencies.code')
            ->get()
            ->map(function (object $competency) {
                $levels = DB::table('competency_levels')
                    ->where('competency_id', $competency->id)
                    ->orderBy('level')
                    ->get()
                    ->map(function (object $level) {
                        $indicators = DB::table('comp_level_indicators')
                            ->where('competency_level_id', $level->id)
                            ->orderBy('id')
                            ->get();

                        return [
                            'id' => $level->id,
                            'lvl' => $level->level,
                            'label' => "ระดับที่ {$level->level}",
                            'description' => $level->description ?? '',
                            'indicators' => $indicators->pluck('description')->values(),
                            'weights' => $indicators->pluck('weight')->map(fn ($weight) => (float) $weight)->values(),
                        ];
                    })
                    ->values();

                return [
                    'id' => $competency->id,
                    'competencyTypeId' => $competency->competency_type_id,
                    'cd' => $competency->code,
                    'n' => $competency->name,
                    't' => $competency->type_code,
                    'tg' => 'tag-'.strtolower((string) $competency->type_code),
                    'det' => $competency->detail ?? '',
                    'lv' => $levels->count(),
                    'grp' => '',
                    'levels' => $levels,
                ];
            });
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

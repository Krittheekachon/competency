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
                'adminPage' => session('adminPage'),
            ]),
            1 => Inertia::render('Super/Dashboard', ['users' => $users]),
            2 => Inertia::render('Head/Dashboard', ['users' => $users]),
            3 => Inertia::render('Staff/Dashboard', [
                'currentUser' => $this->dashboardUserPayload(auth()->user()),
                'activeCycleName' => $activeCycleName,
                'learningMethods' => $learningMethods,
            ]),
            4 => Inertia::render('HR/Dashboard', [
                'hrSummary' => [
                    'totalUsers' => User::count(),
                    'hrUsers' => User::where('role_id', 4)->count(),
                    'staffUsers' => User::where('role_id', 3)->count(),
                    'source' => 'database',
                ],
                'competencyTypes' => $competencyTypes,
                'competencies' => $competencies,
                'learningMethods' => $learningMethods,
                'activeCycleName' => $activeCycleName,
                'hrWorklines' => $structureData['worklines'] ?? [],
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
            5 => Inertia::render('Executive/Dashboard', [
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
            default => Inertia::render('Dashboard'),
        };
    }

    public function adminIndex()
    {
        $competencyTypes = CompetencyType::orderBy('code')->get()->map(fn (CompetencyType $type) => [
            'id'       => $type->id,
            'code'     => $type->code,
            'fullName' => $type->full_name,
            'desc'     => $type->description,
        ]);

        $users = User::orderBy('name')->get()
            ->map(fn (User $user) => $this->dashboardUserPayload($user));

        $learningMethods = DB::table('learning_method_types')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (object $m) => [
                'key'   => $m->key,
                'label' => $m->label,
                'desc'  => $m->description ?? '',
            ]);

        return Inertia::render('Admin/Dashboard', [
            'users'          => $users,
            'competencyTypes' => $competencyTypes,
            'competencies'   => $this->competencyPayload(),
            'learningMethods' => $learningMethods,
            'adminPage'      => session('adminPage'),
            ...$this->adminStructurePayload(),
        ]);
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
            'division' => $user->division ?? '',
            'job' => $user->job ?? '',
            'job_family' => $user->job_family ?? '',
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
        $adminWorkline = 'สายบริหาร';
        $academicWorkline = 'สายวิชาการ';
        $supportWorkline = 'สายสนับสนุน';

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

        $divisions = DB::table('divisions')
            ->leftJoin('worklines', 'divisions.workline_id', '=', 'worklines.id')
            ->select('divisions.id', 'divisions.name', 'worklines.name as workline_name')
            ->orderBy('worklines.name')
            ->orderBy('divisions.name')
            ->get();

        $divisionsByWorkline = $divisions
            ->groupBy('workline_name')
            ->map(fn ($divs) => $divs->pluck('name')->values())
            ->all();

        $supportDepts = DB::table('support_departments')->orderBy('name')->get();

        $supportWorks = DB::table('support_works')
            ->orderBy('support_department_id')
            ->orderBy('name')
            ->get()
            ->groupBy('support_department_id');

        $supportUnits = DB::table('support_units')
            ->orderBy('support_work_id')
            ->orderBy('name')
            ->get()
            ->groupBy('support_work_id');

        $supportOrg = $supportDepts->mapWithKeys(function (object $dept) use ($supportWorks, $supportUnits) {
            $works = ($supportWorks[$dept->id] ?? collect())
                ->map(function (object $work) use ($supportUnits) {
                    return [
                        'work'  => $work->name,
                        'units' => ($supportUnits[$work->id] ?? collect())
                            ->pluck('name')
                            ->values()
                            ->all(),
                    ];
                })
                ->values()
                ->all();

            return [$dept->name => $works];
        })->all();

        return [
            'worklines' => DB::table('worklines')
                ->orderByRaw("CASE name WHEN '{$adminWorkline}' THEN 1 WHEN '{$academicWorkline}' THEN 2 WHEN '{$supportWorkline}' THEN 3 ELSE 99 END")
                ->orderBy('name')
                ->pluck('name'),
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
            'divisionsByWorkline' => $divisionsByWorkline,
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
            'supportOrg' => $supportOrg,
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
            1 => 'supervisor',
            2 => 'dept_head',
            3 => 'employee',
            4 => 'hr',
            5 => 'dean',
            default => 'employee',
        };
    }
}

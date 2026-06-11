<?php

namespace App\Http\Controllers;

use App\Models\CompetencyType;
use App\Models\User;
use App\Services\ExpectedLevelResolver;
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
        $role = $this->normalizeRoleKey(auth()->user()->role_key ?: $this->roleKeyFromId(auth()->user()->role_id));
        $competencyTypes = CompetencyType::orderBy('code')->get()->map(fn (CompetencyType $type) => [
            'id' => $type->id,
            'code' => $type->code,
            'fullName' => $type->full_name,
            'desc' => $type->description,
        ]);
        $competencies = $this->competencyPayload();
        $users = User::orderBy('name')->get()->map(fn (User $user) => $this->dashboardUserPayload($user));
        $activeCycleName = 'รอบประเมินปัจจุบัน';
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
        $hrStructureData = $this->hrStructurePayload();

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
            'admin' => Inertia::render('Admin/Dashboard', [
                'users' => $users,
                'competencyTypes' => $competencyTypes,
                'competencies' => $competencies,
                ...$structureData,
                'learningMethods' => $learningMethods,
                'hrCatalogItems' => $this->learningCatalogItems(),
                'idpLearningMethods' => $this->idpLearningMethods(),
            ]),
            'supervisor' => Inertia::render('Super/Dashboard', ['users' => $users]),
            'dept_head' => Inertia::render('Head/Dashboard', ['users' => $users]),
            'employee' => Inertia::render('Employee/Dashboard', [
                'currentUser' => $this->dashboardUserPayload(auth()->user()),
                'currentUserCompetencies' => $this->assignedCompetenciesForUser(auth()->user()),
                'currentUserCompetencyGaps' => $this->competencyGapsForUser(auth()->user()),
                'activeCycleName' => $activeCycleName,
                'learningMethods' => $learningMethods,
            ]),
            'hr' => Inertia::render('HR/Dashboard', [
                'hrSummary' => [
                    'totalUsers' => User::count(),
                    'hrUsers' => User::where('role_id', 4)->count(),
                    'employeeUsers' => User::where('role_id', 3)->count(),
                    'source' => 'database',
                ],
                ...$hrStructureData,
                'competencies' => $competencies,
                'assignedCompetenciesByScope' => $this->assignedCompetenciesByScope(),
                'learningMethods' => $learningMethods,
                'activeCycleName' => $activeCycleName,
                'hrCatalogItems' => $this->learningCatalogItems(),
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
            'dean' => Inertia::render('Executive/Dashboard', [
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

    private function normalizeRoleKey(string $roleKey): string
    {
        return match ($roleKey) {
            'manager' => 'dean',
            'manager_dept' => 'dept_head',
            default => $roleKey,
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
            'position_id' => $user->position_id,
            'level_id' => $user->level_id,
            'r' => $user->role_key ?: $this->roleKeyFromId($user->role_id),
            'sup' => $user->supervisor ?: '',
            'evaluator2' => $user->evaluator2 ?: '',
            'supervisor_id_1' => $user->supervisor_id_1,
            'supervisor_id_2' => $user->supervisor_id_2,
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
            ->select('id', 'job_family_id', 'name')
            ->orderBy('name')
            ->get()
            ->groupBy('job_family_id');

        $supportDepartments = DB::table('support_departments')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $supportWorksByDepartment = DB::table('support_works')
            ->select('id', 'support_department_id', 'name')
            ->orderBy('name')
            ->get()
            ->groupBy('support_department_id');

        $supportUnitsByWork = DB::table('support_units')
            ->select('support_work_id', 'name')
            ->orderBy('name')
            ->get()
            ->groupBy('support_work_id');

        $supportOrg = $supportDepartments
            ->mapWithKeys(fn (object $department) => [
                $department->name => ($supportWorksByDepartment[$department->id] ?? collect())
                    ->map(fn (object $work) => [
                        'work' => $work->name,
                        'units' => ($supportUnitsByWork[$work->id] ?? collect())
                            ->pluck('name')
                            ->values(),
                    ])
                    ->values(),
            ])
            ->all();

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
            'supportOrg' => $supportOrg,
            'positionLookup' => $jobFamilies
                ->flatMap(fn (object $family) => ($positionsByFamily[$family->id] ?? collect())
                    ->map(fn (object $position) => [
                        'id' => $position->id,
                        'name' => $position->name,
                        'jobFamilyName' => $family->name,
                        'worklineName' => $family->workline_name,
                    ]))
                ->values(),
            'levels' => DB::table('levels')->orderBy('name')->pluck('name'),
            'levelsByWorkline' => DB::table('levels')
                ->leftJoin('worklines', 'levels.workline_id', '=', 'worklines.id')
                ->select('levels.name', 'worklines.name as workline_name')
                ->whereNull('levels.job_family_id')
                ->orderBy('levels.name')
                ->get()
                ->filter(fn (object $level) => $level->workline_name !== null)
                ->groupBy('workline_name')
                ->map(fn ($levels) => $levels->pluck('name')->values())
                ->all(),
            'levelsByJobFamily' => DB::table('levels')
                ->join('job_families', 'levels.job_family_id', '=', 'job_families.id')
                ->leftJoin('worklines', 'job_families.workline_id', '=', 'worklines.id')
                ->select('levels.name', 'job_families.name as job_family_name', 'worklines.name as workline_name')
                ->orderBy('levels.name')
                ->get()
                ->filter(fn (object $level) => $level->workline_name !== null)
                ->groupBy('workline_name')
                ->map(fn ($levelsByWorkline) => $levelsByWorkline
                    ->groupBy('job_family_name')
                    ->map(fn ($levels) => $levels->pluck('name')->values())
                    ->all())
                ->all(),
            'levelExpectationsByWorkline' => DB::table('levels')
                ->leftJoin('worklines', 'levels.workline_id', '=', 'worklines.id')
                ->select('levels.name', 'levels.expected_level', 'worklines.name as workline_name')
                ->whereNull('levels.job_family_id')
                ->orderBy('levels.name')
                ->get()
                ->filter(fn (object $level) => $level->workline_name !== null)
                ->groupBy('workline_name')
                ->map(fn ($levels) => $levels->mapWithKeys(fn (object $level) => [
                    $level->name => $level->expected_level,
                ]))
                ->all(),
            'levelExpectationsByJobFamily' => DB::table('levels')
                ->join('job_families', 'levels.job_family_id', '=', 'job_families.id')
                ->leftJoin('worklines', 'job_families.workline_id', '=', 'worklines.id')
                ->select('levels.name', 'levels.expected_level', 'job_families.name as job_family_name', 'worklines.name as workline_name')
                ->orderBy('levels.name')
                ->get()
                ->filter(fn (object $level) => $level->workline_name !== null)
                ->groupBy('workline_name')
                ->map(fn ($levelsByWorkline) => $levelsByWorkline
                    ->groupBy('job_family_name')
                    ->map(fn ($levels) => $levels->mapWithKeys(fn (object $level) => [
                        $level->name => $level->expected_level,
                    ])->all())
                    ->all())
                ->all(),
        ];
    }

    private function hrStructurePayload(): array
    {
        $structure = $this->adminStructurePayload();

        unset($structure['levelsByWorkline']);
        unset($structure['levelExpectationsByWorkline']);
        unset($structure['levelsByJobFamily']);
        unset($structure['levelExpectationsByJobFamily']);

        $structure['positionCompetencies'] = DB::table('position_competencies')
            ->select('position_id', 'competency_id')
            ->orderBy('competency_id')
            ->get()
            ->groupBy('position_id')
            ->map(fn ($items) => $items->pluck('competency_id')->values())
            ->all();

        return $structure;
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
                'competency_types.code as type_code',
                'competency_types.full_name as type_full_name'
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
                    'typeName' => $competency->type_full_name,
                    'tg' => 'tag-'.strtolower((string) $competency->type_code),
                    'det' => $competency->detail ?? '',
                    'lv' => $levels->count(),
                    'grp' => '',
                    'levels' => $levels,
                ];
            });
    }

    private function assignedCompetenciesByScope(): array
    {
        $roundId = $this->activeRoundId();

        if (! $roundId) {
            return [];
        }

        return DB::table('hr_expectations')
            ->join('competencies', 'hr_expectations.competency_id', '=', 'competencies.id')
            ->leftJoin('competency_types', 'competencies.competency_type_id', '=', 'competency_types.id')
            ->leftJoin('job_families', 'hr_expectations.job_family_id', '=', 'job_families.id')
            ->leftJoin('worklines', 'job_families.workline_id', '=', 'worklines.id')
            ->leftJoin('levels', 'hr_expectations.level_id', '=', 'levels.id')
            ->where('hr_expectations.assessment_round_id', $roundId)
            ->select(
                'worklines.name as workline_name',
                'job_families.name as job_family_name',
                'levels.name as level_name',
                'competencies.id',
                'competencies.competency_type_id',
                'competencies.code',
                'competencies.name',
                'competencies.detail',
                'competency_types.code as type_code',
                DB::raw('COALESCE(hr_expectations.expected_level, levels.expected_level) as expected_level')
            )
            ->orderBy('competencies.code')
            ->get()
            ->groupBy(fn (object $item): string => implode('|', [
                $item->workline_name ?: '-',
                $item->job_family_name ?: '-',
                $item->level_name ?: '-',
            ]))
            ->map(fn ($items) => $items->map(fn (object $item): array => $this->compactCompetencyPayload($item))->values()->all())
            ->all();
    }

    private function assignedCompetenciesForUser(User $user): array
    {
        $levelIds = collect();
        $positionIds = $this->positionIdsForUser($user);

        if ($user->level_id) {
            $levelIds->push($user->level_id);
        }

        foreach ([$user->level, $user->position] as $levelName) {
            if (! $levelName) {
                continue;
            }

            $worklineId = $this->worklineIdFromUser($user);

            $matchingLevels = DB::table('levels')
                ->where('name', $levelName)
                ->where(function ($query) use ($worklineId) {
                    $query->whereNull('workline_id');

                    if ($worklineId) {
                        $query->orWhere('workline_id', $worklineId);
                    }
                })
                ->pluck('id');

            $levelIds = $levelIds->merge($matchingLevels);
        }

        $levelIds = $levelIds->filter()->unique()->values();

        if ($levelIds->isEmpty() && $positionIds->isEmpty()) {
            return [];
        }

        $jobFamilyIds = collect();

        if ($user->position_id) {
            $positionFamilyId = DB::table('positions')->where('id', $user->position_id)->value('job_family_id');
            if ($positionFamilyId) {
                $jobFamilyIds->push($positionFamilyId);
            }
        }

        if ($user->workline) {
            $worklineId = $this->worklineIdFromUser($user);
            if ($worklineId) {
                $jobFamilyIds = $jobFamilyIds->merge(
                    DB::table('job_families')->where('workline_id', $worklineId)->pluck('id')
                );
            }
        }

        $jobFamilyIds = $jobFamilyIds->filter()->unique()->values();

        $expectedLevelResolver = app(ExpectedLevelResolver::class);

        $expectationRows = $levelIds->isEmpty()
            ? collect()
            : DB::table('hr_expectations')
                ->join('competencies', 'hr_expectations.competency_id', '=', 'competencies.id')
                ->leftJoin('competency_types', 'competencies.competency_type_id', '=', 'competency_types.id')
                ->leftJoin('levels', 'hr_expectations.level_id', '=', 'levels.id')
                ->whereIn('hr_expectations.level_id', $levelIds)
                ->when($jobFamilyIds->isNotEmpty(), fn ($query) => $query->whereIn('hr_expectations.job_family_id', $jobFamilyIds))
                ->select(
                    'competencies.id',
                    'competencies.competency_type_id',
                    'competencies.code',
                    'competencies.name',
                    'competencies.detail',
                    'competency_types.code as type_code',
                    DB::raw('COALESCE(hr_expectations.expected_level, levels.expected_level) as expected_level')
                )
                ->get();

        $positionRows = $positionIds->isEmpty()
            ? collect()
            : DB::table('position_competencies')
                ->join('competencies', 'position_competencies.competency_id', '=', 'competencies.id')
                ->leftJoin('competency_types', 'competencies.competency_type_id', '=', 'competency_types.id')
                ->whereIn('position_competencies.position_id', $positionIds)
                ->select(
                    'competencies.id',
                    'competencies.competency_type_id',
                    'competencies.code',
                    'competencies.name',
                    'competencies.detail',
                    'competency_types.code as type_code',
                    DB::raw('NULL as expected_level')
                )
                ->get();

        return $expectationRows
            ->merge($positionRows)
            ->sortBy('code')
            ->unique('id')
            ->map(function (object $item) use ($user, $expectedLevelResolver): array {
                $payload = $this->compactCompetencyPayload($item);
                $payload['expectedLevel'] = $payload['expectedLevel']
                    ?? $expectedLevelResolver->forUserCompetency($user, (int) $item->id);

                return $payload;
            })
            ->values()
            ->all();
    }

    private function positionIdsForUser(User $user): \Illuminate\Support\Collection
    {
        $positionIds = collect();

        if ($user->position_id) {
            $positionIds->push($user->position_id);
        }

        if (! $user->position) {
            return $positionIds->filter()->unique()->values();
        }

        $worklineId = $this->worklineIdFromUser($user);

        $matchedIds = DB::table('positions')
            ->join('job_families', 'positions.job_family_id', '=', 'job_families.id')
            ->where('positions.name', $user->position)
            ->when($worklineId, fn ($query) => $query->where('job_families.workline_id', $worklineId))
            ->when($user->department, function ($query) use ($user) {
                $departmentRoot = trim(explode(' > ', $user->department)[0] ?? $user->department);

                $query->where(function ($nested) use ($user, $departmentRoot) {
                    $nested->where('job_families.name', $user->department)
                        ->orWhere('job_families.name', $departmentRoot);
                });
            })
            ->pluck('positions.id');

        return $positionIds->merge($matchedIds)->filter()->unique()->values();
    }

    private function worklineIdFromUser(User $user): ?int
    {
        if (! $user->workline) {
            return null;
        }

        $workline = trim($user->workline);
        $withoutPrefix = preg_replace('/^สาย/u', '', $workline) ?: $workline;
        $candidates = collect([
            $workline,
            $withoutPrefix,
            'สาย'.$withoutPrefix,
            'สายงาน'.$withoutPrefix,
        ])->filter()->unique()->values();

        $id = DB::table('worklines')
            ->whereIn('name', $candidates)
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function compactCompetencyPayload(object $competency): array
    {
        $levels = $this->competencyLevelsPayload((int) $competency->id);

        return [
            'id' => $competency->id,
            'competencyTypeId' => $competency->competency_type_id,
            'cd' => $competency->code,
            'n' => $competency->name,
            't' => $competency->type_code,
            'tg' => 'tag-'.strtolower((string) $competency->type_code),
            'det' => $competency->detail ?? '',
            'expectedLevel' => $competency->expected_level,
            'lv' => count($levels),
            'levels' => $levels,
        ];
    }

    private function competencyLevelsPayload(int $competencyId): array
    {
        return DB::table('competency_levels')
            ->where('competency_id', $competencyId)
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
                    'indicators' => $indicators->pluck('description')->values()->all(),
                    'weights' => $indicators->pluck('weight')->map(fn ($weight) => $weight === null ? null : (float) $weight)->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function competencyGapsForUser(User $user): array
    {
        $expectedLevelResolver = app(ExpectedLevelResolver::class);

        return DB::table('assessments')
            ->join('competencies', 'assessments.competency_id', '=', 'competencies.id')
            ->leftJoin('competency_gaps', function ($join) {
                $join->on('competency_gaps.assessment_id', '=', 'assessments.id')
                    ->on('competency_gaps.competency_id', '=', 'assessments.competency_id');
            })
            ->leftJoin('competency_types', 'competencies.competency_type_id', '=', 'competency_types.id')
            ->where('assessments.user_id', $user->id)
            ->whereNotNull('assessments.last_draft_saved_at')
            ->select(
                'assessments.id as assessment_id',
                'competencies.id',
                'competencies.code',
                'competencies.name',
                'competencies.detail',
                'competency_types.code as type_code',
                'assessments.score',
                'competency_gaps.expected_level',
                'competency_gaps.actual_level',
                'competency_gaps.gap',
                'competency_gaps.requires_idp',
                'competency_gaps.status',
                'assessments.updated_at'
            )
            ->orderBy('competencies.code')
            ->get()
            ->map(function (object $gap) use ($user, $expectedLevelResolver): array {
                $expected = $gap->expected_level === null
                    ? $expectedLevelResolver->forUserCompetency($user, (int) $gap->id)
                    : (float) $gap->expected_level;
                $actual = $gap->actual_level === null ? (float) $gap->score : (float) $gap->actual_level;
                $gapValue = $gap->gap === null && $expected !== null
                    ? round($actual - (float) $expected, 2)
                    : ($gap->gap === null ? null : (float) $gap->gap);

                return [
                    'id' => (int) $gap->id,
                    'cd' => $gap->code,
                    'n' => $gap->name,
                    't' => $gap->type_code ?: '-',
                    'tg' => 'tag-'.strtolower((string) ($gap->type_code ?: 'cc')),
                    'det' => $gap->detail ?? '',
                    'expected' => $expected,
                    'actual' => $actual,
                    'gap' => $gapValue,
                    'requiresIdp' => $gapValue !== null && $gapValue < 0,
                    'missingIndicators' => $gapValue !== null && $gapValue < 0 && $expected !== null
                        ? $this->missingIndicatorsForAssessment((int) $gap->assessment_id, (int) $gap->id, (float) $expected, $actual)
                        : [],
                    'missingIndicatorCount' => $gapValue !== null && $gapValue < 0
                        ? (int) ceil(abs($gapValue) / 0.25)
                        : 0,
                    'status' => $gap->status ?? 'draft',
                    'updatedAt' => $gap->updated_at,
                ];
            })
            ->values()
            ->all();
    }

    private function missingIndicatorsForAssessment(int $assessmentId, int $competencyId, float $expectedLevel, float $actualLevel): array
    {
        $checkedKeys = DB::table('assessment_evidences')
            ->where('assessment_id', $assessmentId)
            ->where('competency_id', $competencyId)
            ->pluck('indicator_key')
            ->flip();
        $maxExpectedLevel = max(1, (int) ceil($expectedLevel));
        $requiredIndicatorCount = max(0, (int) ceil(($expectedLevel - $actualLevel) / 0.25));

        if ($requiredIndicatorCount === 0) {
            return [];
        }

        $missingRows = DB::table('competency_levels')
            ->where('competency_id', $competencyId)
            ->where('level', '<=', $maxExpectedLevel)
            ->orderBy('level')
            ->get()
            ->flatMap(function (object $level) use ($checkedKeys, $competencyId) {
                return DB::table('comp_level_indicators')
                    ->where('competency_level_id', $level->id)
                    ->orderBy('id')
                    ->get()
                    ->values()
                    ->filter(function (object $indicator, int $index) use ($checkedKeys, $competencyId, $level): bool {
                        return ! $checkedKeys->has($competencyId.':'.$level->id.':'.$index);
                    })
                    ->map(fn (object $indicator, int $index): array => [
                        'level' => (int) $level->level,
                        'levelLabel' => 'ระดับ '.$level->level,
                        'code' => $level->level.'.'.($index + 1),
                        'description' => $indicator->description,
                    ])
                    ->values();
            })
            ->take($requiredIndicatorCount)
            ->values();

        return $missingRows
            ->groupBy('level')
            ->map(function ($indicators, int $level): array {
                return [
                    'level' => $level,
                    'label' => $indicators->first()['levelLabel'] ?? 'ระดับ '.$level,
                    'indicators' => $indicators
                        ->map(fn (array $indicator): array => [
                            'code' => $indicator['code'],
                            'description' => $indicator['description'],
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function activeRoundId(): ?int
    {
        $roundId = DB::table('assessment_rounds')
            ->where('is_active', true)
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->value('id');

        return $roundId ? (int) $roundId : null;
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

    private function learningCatalogItems()
    {
        $competencyIdsByCatalog = DB::table('learning_catalog_competency')
            ->select('learning_catalog_id', 'competency_id')
            ->orderBy('competency_id')
            ->get()
            ->groupBy('learning_catalog_id')
            ->map(fn ($items) => $items->pluck('competency_id')->values());

        return DB::table('learning_catalogs')
            ->leftJoin('learning_method_types', 'learning_catalogs.method_type_id', '=', 'learning_method_types.id')
            ->select(
                'learning_catalogs.id',
                'learning_catalogs.code',
                'learning_catalogs.name',
                'learning_catalogs.delivery_type',
                'learning_catalogs.source_type',
                'learning_catalogs.provider',
                'learning_catalogs.cost',
                'learning_catalogs.hours',
                'learning_catalogs.expected_levels',
                'learning_catalogs.description',
                'learning_catalogs.is_active',
                'learning_method_types.key as method_key',
                'learning_method_types.label as method_label'
            )
            ->orderBy('learning_catalogs.name')
            ->get()
            ->map(fn (object $item) => [
                'id' => $item->id,
                'code' => $item->code ?? '',
                'name' => $item->name,
                'methodKey' => $item->method_key,
                'methodLabel' => $item->method_label,
                'deliveryType' => $item->delivery_type ?? 'e_learning',
                'sourceType' => $item->source_type ?? 'internal',
                'provider' => $item->provider ?? '',
                'cost' => $item->cost,
                'hours' => $item->hours,
                'expectedLevels' => $this->decodeExpectedLevels($item->expected_levels),
                'competencyIds' => $competencyIdsByCatalog[$item->id] ?? [],
                'description' => $item->description ?? '',
                'isActive' => (bool) $item->is_active,
            ]);
    }

    private function decodeExpectedLevels($levels): array
    {
        if (!$levels) return [];

        $decoded = is_string($levels) ? json_decode($levels, true) : $levels;

        return collect(is_array($decoded) ? $decoded : [])
            ->map(fn ($level) => (int) $level)
            ->filter(fn ($level) => $level >= 1 && $level <= 5)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function idpLearningMethods()
    {
        return DB::table('idp_learning_methods')
            ->select('id', 'focus_type', 'title', 'template_file_name', 'is_active')
            ->whereIn('focus_type', ['experiential', 'social'])
            ->orderBy('focus_type')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (object $item) => [
                'id' => $item->id,
                'focusType' => $item->focus_type,
                'title' => $item->title,
                'templateFileName' => $item->template_file_name ?? '',
                'isActive' => (bool) $item->is_active,
            ]);
    }
}

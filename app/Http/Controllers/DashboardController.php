<?php

namespace App\Http\Controllers;

use App\Models\CompetencyType;
use App\Models\User;
use App\Services\ExpectedLevelResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia; 
use Inertia\Response;

class DashboardController extends Controller
{
    private ?array $competencyLevelsByCompetency = null;

    private array $worklineIdCache = [];

    private array $jobFamilyNameCache = [];

    /**
     * จัดการหน้า Dashboard ตาม Role ID
     */
    public function index()
    {
        $currentUser = auth()->user()->loadMissing(['role', 'evaluatorLevel1', 'evaluatorLevel2', 'evaluatorLevel3']);
        $role = $this->roleKeyForUser($currentUser);
        $competencyTypes = CompetencyType::orderBy('code')->get()->map(fn (CompetencyType $type) => [
            'id' => $type->id,
            'code' => $type->code,
            'fullName' => $type->full_name,
            'desc' => $type->description,
        ]);
        $competencies = $this->competencyPayload();
        $users = User::with(['role', 'evaluatorLevel1', 'evaluatorLevel2', 'evaluatorLevel3'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->dashboardUserPayload($user));
        $activeCycleName = 'รอบประเมินปัจจุบัน';
        $learningMethods = $this->canonicalLearningMethods();
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
        $approvedIdpActivities = $this->currentUserApprovedIdpActivities($currentUser);

        return match ($role) {
            'admin' => Inertia::render('Admin/Dashboard', [
                'users' => $users,
                'roles' => $this->rolesPayload(),
                'competencyTypes' => $competencyTypes,
                'competencies' => $competencies,
                ...$this->adminStructurePayload(),
                'learningMethods' => $learningMethods,
                'hrCatalogItems' => $this->learningCatalogItems(),
                'idpLearningMethods' => $this->idpLearningMethods(),
                'idpDeliveryTypeSettings' => $this->idpDeliveryTypeSettings(),
                'currentUserApprovedIdpActivities' => $approvedIdpActivities,
            ]),
            'supervisor' => Inertia::render('Super/Dashboard', [
                'users' => $users,
                'roleKey' => 'supervisor',
                'currentUser' => $this->dashboardUserPayload($currentUser),
                'idpReviewItems' => $this->idpReviewItemsForReviewer($currentUser),
                'currentUserApprovedIdpActivities' => $approvedIdpActivities,
            ]),
            'dept_head' => Inertia::render('Head/Dashboard', [
                'users' => $users,
                'roleKey' => 'dept_head',
                'currentUser' => $this->dashboardUserPayload($currentUser),
                'idpReviewItems' => $this->idpReviewItemsForReviewer($currentUser),
                'currentUserApprovedIdpActivities' => $approvedIdpActivities,
            ]),
            'employee' => Inertia::render('Employee/Dashboard', [
                'currentUser' => $this->dashboardUserPayload($currentUser),
                'currentUserCompetencies' => $this->assignedCompetenciesForUser($currentUser),
                'currentUserCompetencyGaps' => $this->competencyGapsForUser($currentUser),
                'currentUserIdp' => $this->currentUserIdpPayload($currentUser),
                'activeCycleName' => $activeCycleName,
                'learningMethods' => $learningMethods,
                'hrCatalogItems' => $this->learningCatalogItems(),
                'idpLearningMethods' => $this->idpLearningMethods(),
                'currentUserApprovedIdpActivities' => $approvedIdpActivities,
            ]),
            'hr' => Inertia::render('HR/Dashboard', [
                'hrSummary' => [
                    'totalUsers' => User::count(),
                    'hrUsers' => User::where('role_id', $this->roleIdByKey('hr'))->count(),
                    'employeeUsers' => User::where('role_id', $this->roleIdByKey('employee'))->count(),
                    'source' => 'database',
                ],
                ...$this->hrStructurePayload(),
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
                'currentUserApprovedIdpActivities' => $approvedIdpActivities,
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
                'currentUserApprovedIdpActivities' => $approvedIdpActivities,
            ]),
            default => Inertia::render('Dashboard'),
        };
    }

    private function canonicalLearningMethods(): array
    {
        return [
            [
                'key' => 'experiential-learning',
                'label' => 'Experiential Learning',
                'desc' => 'การเรียนรู้ผ่านประสบการณ์จากการทำงานจริง เช่น OJT โครงการพิเศษ หรือ Job Rotation',
            ],
            [
                'key' => 'social-learning',
                'label' => 'Social Learning',
                'desc' => 'การเรียนรู้ผ่านบุคคลอื่น การปฏิสัมพันธ์ แลกเปลี่ยนความคิดเห็น ประสบการณ์ร่วมกัน หรือการมีผู้คอยให้คำแนะนำ',
            ],
            [
                'key' => 'formal-learning',
                'label' => 'Formal Learning',
                'desc' => 'การเรียนรู้อย่างเป็นทางการ มีแบบแผน หรือการเรียนในห้องเรียน',
            ],
        ];
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
        $department = $this->currentDepartmentForUser($user);
        $roleKey = $this->roleKeyForUser($user);
        $assignedCompetencies = $this->assignedCompetenciesForUser($user);
        $competencyGaps = $this->competencyGapsForUser($user);
        $evalStatus = $this->evaluationStatusFromGaps($competencyGaps);
        $structureIssues = $roleKey === 'admin'
            ? []
            : [
                ...$this->structureIssuesForUser($user, $department),
                ...$this->reportingLineIssuesForUser($user),
            ];

        return [
            'db_id' => $user->id,
            'sso' => $user->sso ?: (string) $user->id,
            't' => $user->title ?: '',
            'n' => $user->name,
            'fn' => $user->first_name_th ?: $user->name,
            'ln' => $user->last_name_th ?: '',
            'fe' => $user->first_name_en ?: '',
            'le' => $user->last_name_en ?: '',
            'em' => $user->email,
            'ph' => $user->phone ?: '',
            'w' => $user->workline ?: '',
            'd' => $department,
            'p' => $user->position ?: '',
            'l' => $user->level ?: '',
            'position_id' => $user->position_id,
            'level_id' => $user->level_id,
            'r' => $roleKey,
            'supervisor_id_1' => $user->supervisor_id_1,
            'supervisor_id_2' => $user->supervisor_id_2,
            'supervisor_id_3' => $user->supervisor_id_3,
            'sup' => $this->displayNameForUser($user->evaluatorLevel1),
            'evaluator2' => $this->displayNameForUser($user->evaluatorLevel2),
            'evaluator3' => $this->displayNameForUser($user->evaluatorLevel3),
            'supervisorChain' => $this->supervisorChainForUser($user),
            'assignedCompetencies' => $assignedCompetencies,
            'competencyGaps' => $competencyGaps,
            'gaps' => collect($competencyGaps)
                ->filter(fn (array $gap): bool => (float) ($gap['gap'] ?? 0) < 0)
                ->pluck('n')
                ->values()
                ->all(),
            'evalStatus' => $evalStatus,
            'act' => (bool) $user->is_active,
            'structureStatus' => $structureIssues === [] ? 'ok' : 'invalid',
            'structureIssues' => $structureIssues,
        ];
    }

    private function evaluationStatusFromGaps(array $competencyGaps): string
    {
        if ($competencyGaps === []) {
            return 'draft';
        }

        $statuses = collect($competencyGaps)
            ->pluck('status')
            ->filter()
            ->values();

        if ($statuses->contains('revision_required')) {
            return 'revision_required';
        }

        if ($statuses->contains('self_submitted')) {
            return 'self_submitted';
        }

        if ($statuses->contains('unit_evaluated')) {
            return 'unit_evaluated';
        }

        if ($statuses->contains('dept_evaluated')) {
            return 'dept_evaluated';
        }

        if ($statuses->contains('approved') || $statuses->contains('dean_approved')) {
            return 'approved';
        }

        return 'draft';
    }

    private function displayNameForUser(?User $user): string
    {
        if (! $user) {
            return '';
        }

        return trim(($user->title ?: '').$user->name);
    }

    private function supervisorChainForUser(User $user): array
    {
        return collect([
            ['step' => 1, 'label' => 'ผู้บังคับบัญชาลำดับ 1', 'user' => $user->evaluatorLevel1],
            ['step' => 2, 'label' => 'ผู้บังคับบัญชาลำดับ 2', 'user' => $user->evaluatorLevel2],
            ['step' => 3, 'label' => 'ผู้บังคับบัญชาลำดับ 3', 'user' => $user->evaluatorLevel3],
        ])
            ->filter(fn (array $item): bool => (bool) $item['user'])
            ->map(fn (array $item): array => [
                'id' => (int) $item['user']->id,
                'step' => $item['step'],
                'label' => $item['label'],
                'name' => $this->displayNameForUser($item['user']),
                'position' => $item['user']->position ?: '',
            ])
            ->values()
            ->all();
    }

    private function decodeJsonObject(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function currentDepartmentForUser(User $user): string
    {
        $currentJobFamily = $this->currentJobFamilyNameForUser($user);

        if (!$currentJobFamily) {
            return $user->department ?: '';
        }

        $suffix = $this->departmentSuffix($user->department ?: '');

        return $currentJobFamily.$suffix;
    }

    private function currentJobFamilyNameForUser(User $user): ?string
    {
        $cacheKey = $user->position_id
            ? 'position:'.$user->position_id
            : 'structure:'.implode('|', [$user->workline, $user->position]);

        if (array_key_exists($cacheKey, $this->jobFamilyNameCache)) {
            return $this->jobFamilyNameCache[$cacheKey];
        }

        if ($user->position_id) {
            return $this->jobFamilyNameCache[$cacheKey] = DB::table('positions')
                ->join('job_families', 'positions.job_family_id', '=', 'job_families.id')
                ->where('positions.id', $user->position_id)
                ->value('job_families.name');
        }

        if (!$user->workline || !$user->position) {
            return $this->jobFamilyNameCache[$cacheKey] = null;
        }

        $matches = DB::table('positions')
            ->join('job_families', 'positions.job_family_id', '=', 'job_families.id')
            ->join('worklines', 'job_families.workline_id', '=', 'worklines.id')
            ->where('worklines.name', $user->workline)
            ->where('positions.name', $user->position)
            ->pluck('job_families.name')
            ->unique()
            ->values();

        return $this->jobFamilyNameCache[$cacheKey] = $matches->count() === 1
            ? $matches->first()
            : null;
    }

    private function departmentSuffix(string $department): string
    {
        $parts = explode(' > ', $department, 2);

        return isset($parts[1]) ? ' > '.$parts[1] : '';
    }

    private function structureIssuesForUser(User $user, string $department): array
    {
        $issues = [];

        if (!$user->workline) {
            $issues[] = 'ยังไม่ได้กำหนดสายงาน';

            return $issues;
        }

        $worklineId = DB::table('worklines')->where('name', $user->workline)->value('id');
        if (!$worklineId) {
            $issues[] = 'สายงานนี้ไม่มีในโครงสร้างปัจจุบัน';

            return $issues;
        }

        $jobFamilyName = $this->jobFamilyNameFromDepartment($department);
        $jobFamilyId = $jobFamilyName
            ? DB::table('job_families')
                ->where('workline_id', $worklineId)
                ->where('name', $jobFamilyName)
                ->value('id')
            : null;

        if (!$jobFamilyId) {
            $issues[] = 'กลุ่มงานนี้ไม่มีในโครงสร้างปัจจุบัน';
        }

        if ($user->position) {
            $positionExists = $jobFamilyId
                ? DB::table('positions')
                    ->where('job_family_id', $jobFamilyId)
                    ->where('name', $user->position)
                    ->exists()
                : false;
            $roleKey = $this->roleKeyForUser($user);
            $usesJobFamilyAsPosition = $roleKey === 'dean'
                && $jobFamilyName !== ''
                && $user->position === $jobFamilyName;

            if (!$positionExists && !$usesJobFamilyAsPosition) {
                $issues[] = 'ตำแหน่งนี้ไม่มีในกลุ่มงานปัจจุบัน';
            }
        } else {
            $issues[] = 'ยังไม่ได้กำหนดตำแหน่ง';
        }

        if ($user->level) {
            $levelExists = DB::table('levels')
                ->where('workline_id', $worklineId)
                ->where('name', $user->level)
                ->when($jobFamilyId, function ($query) use ($jobFamilyId) {
                    $query->where(function ($query) use ($jobFamilyId) {
                        $query->whereNull('job_family_id')
                            ->orWhere('job_family_id', $jobFamilyId);
                    });
                })
                ->exists();

            if (!$levelExists) {
                $issues[] = 'ระดับตำแหน่งนี้ไม่มีในโครงสร้างปัจจุบัน';
            }
        } else {
            $issues[] = 'ยังไม่ได้กำหนดระดับตำแหน่ง';
        }

        return $issues;
    }

    private function jobFamilyNameFromDepartment(string $department): string
    {
        return trim(explode(' > ', $department)[0] ?? '');
    }

    private function reportingLineIssuesForUser(User $user): array
    {
        $roleKey = $this->roleKeyForUser($user);

        if (in_array($roleKey, ['admin', 'dean'], true)) {
            return [];
        }

        $evaluatorRoleIssues = $this->evaluatorRoleIssuesForUser($user);
        if ($evaluatorRoleIssues !== []) {
            return $evaluatorRoleIssues;
        }

        $hasAnyEvaluator = collect([
            $user->supervisor_id_1,
            $user->supervisor_id_2,
            $user->supervisor_id_3,
        ])->contains(fn ($id) => filled($id));

        return $hasAnyEvaluator ? [] : ['ยังไม่ได้กำหนดผู้ประเมินหรือหัวหน้างาน'];
    }

    private function evaluatorRoleIssuesForUser(User $user): array
    {
        $expectedEvaluators = [
            ['user' => $user->evaluatorLevel1, 'role' => 'supervisor', 'issue' => 'ผู้ประเมินลำดับที่ 1 ไม่ใช่หัวหน้างานแล้ว'],
            ['user' => $user->evaluatorLevel2, 'role' => 'dept_head', 'issue' => 'ผู้ประเมินลำดับที่ 2 ไม่ใช่ผู้บังคับบัญชาแล้ว'],
            ['user' => $user->evaluatorLevel3, 'role' => 'dean', 'issue' => 'ผู้ประเมินลำดับที่ 3 ไม่ใช่ผู้บริหารคณะแล้ว'],
        ];

        return collect($expectedEvaluators)
            ->filter(fn (array $evaluator) => $evaluator['user'] && $this->roleKeyForUser($evaluator['user']) !== $evaluator['role'])
            ->pluck('issue')
            ->values()
            ->all();
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
                $levels = $this->competencyLevelsPayload((int) $competency->id);

                return [
                    'id' => $competency->id,
                    'competencyTypeId' => $competency->competency_type_id,
                    'cd' => $competency->code,
                    'n' => $competency->name,
                    't' => $competency->type_code,
                    'typeName' => $competency->type_full_name,
                    'tg' => 'tag-'.strtolower((string) $competency->type_code),
                    'det' => $competency->detail ?? '',
                    'lv' => count($levels),
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
                $assessment = DB::table('assessments')
                    ->where('user_id', $user->id)
                    ->where('competency_id', $item->id)
                    ->select('id', 'status', 'last_draft_saved_at')
                    ->first();
                $gapStatus = $assessment
                    ? DB::table('competency_gaps')
                        ->where('assessment_id', $assessment->id)
                        ->where('competency_id', $item->id)
                        ->value('status')
                    : null;

                $payload['assessmentStatus'] = $gapStatus ?? $assessment?->status ?? 'draft';
                $lastDraftSavedAt = $assessment?->last_draft_saved_at;
                $payload['lastDraftSavedAt'] = $lastDraftSavedAt
                    ? \Carbon\Carbon::parse($lastDraftSavedAt)->toISOString()
                    : null;

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

        if (array_key_exists($workline, $this->worklineIdCache)) {
            return $this->worklineIdCache[$workline];
        }

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

        return $this->worklineIdCache[$workline] = $id ? (int) $id : null;
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
        if ($this->competencyLevelsByCompetency === null) {
            $levels = DB::table('competency_levels')
                ->orderBy('competency_id')
                ->orderBy('level')
                ->get();
            $indicatorsByLevel = DB::table('comp_level_indicators')
                ->whereIn('competency_level_id', $levels->pluck('id'))
                ->orderBy('id')
                ->get()
                ->groupBy('competency_level_id');

            $this->competencyLevelsByCompetency = $levels
                ->groupBy('competency_id')
                ->map(fn ($competencyLevels) => $competencyLevels
                    ->map(function (object $level) use ($indicatorsByLevel): array {
                        $indicators = $indicatorsByLevel[$level->id] ?? collect();

                        return [
                            'id' => $level->id,
                            'lvl' => $level->level,
                            'label' => "ระดับที่ {$level->level}",
                            'description' => $level->description ?? '',
                            'indicators' => $indicators->pluck('description')->values()->all(),
                            'weights' => $indicators->pluck('weight')
                                ->map(fn ($weight) => $weight === null ? null : (float) $weight)
                                ->values()
                                ->all(),
                        ];
                    })
                    ->values()
                    ->all())
                ->all();
        }

        return $this->competencyLevelsByCompetency[$competencyId] ?? [];
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
            ->leftJoin('scores as evaluator_scores', 'competency_gaps.supervisor_2_score_id', '=', 'evaluator_scores.id')
            ->leftJoin('competency_types', 'competencies.competency_type_id', '=', 'competency_types.id')
            ->where('assessments.user_id', $user->id)
            ->whereNotNull('assessments.last_draft_saved_at')
            ->select(
                'assessments.id as assessment_id',
                'competency_gaps.id as competency_gap_id',
                'competencies.id as competency_id',
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
                'competency_gaps.reject_comment',
                'assessments.note',
                'evaluator_scores.comment as evaluator_comment',
                'assessments.updated_at'
            )
            ->orderBy('competencies.code')
            ->get()
            ->map(function (object $gap) use ($user, $expectedLevelResolver): array {
                $checkedIndicatorKeys = DB::table('assessment_indicator_results')
                    ->where('assessment_id', $gap->assessment_id)
                    ->where('competency_id', $gap->competency_id)
                    ->where('is_checked', true)
                    ->pluck('indicator_key')
                    ->values()
                    ->all();
                $expected = $gap->expected_level === null
                    ? $expectedLevelResolver->forUserCompetency($user, (int) $gap->competency_id)
                    : (float) $gap->expected_level;
                $actual = $gap->actual_level === null ? (float) $gap->score : (float) $gap->actual_level;
                $gapValue = $gap->gap === null && $expected !== null
                    ? round($actual - (float) $expected, 2)
                    : ($gap->gap === null ? null : (float) $gap->gap);

                return [
                    'id' => (int) $gap->competency_gap_id,
                    'competencyId' => (int) $gap->competency_id,
                    'cd' => $gap->code,
                    'n' => $gap->name,
                    't' => $gap->type_code ?: '-',
                    'tg' => 'tag-'.strtolower((string) ($gap->type_code ?: 'cc')),
                    'det' => $gap->detail ?? '',
                    'expected' => $expected,
                    'actual' => $actual,
                    'gap' => $gapValue,
                    'note' => $gap->note ?? '',
                    'reviewerComment' => $gap->evaluator_comment ?? '',
                    'rejectComment' => $gap->reject_comment ?? '',
                    'levels' => $this->competencyLevelsPayload((int) $gap->competency_id),
                    'checkedIndicatorKeys' => $checkedIndicatorKeys,
                    'checkedIndicatorCount' => count($checkedIndicatorKeys),
                    'requiresIdp' => $gapValue !== null && $gapValue < 0,
                    'missingIndicators' => $gapValue !== null && $gapValue < 0 && $expected !== null
                        ? $this->missingIndicatorsForAssessment((int) $gap->assessment_id, (int) $gap->competency_id, (float) $expected, $actual)
                        : [],
                    'missingIndicatorCount' => $gapValue !== null && $gapValue < 0
                        ? (int) ceil(abs($gapValue) / 0.25)
                        : 0,
                    'status' => $gap->status === 'dean_approved' ? 'approved' : ($gap->status ?? 'draft'),
                    'updatedAt' => $gap->updated_at,
                ];
            })
            ->values()
            ->all();
    }

    private function missingIndicatorsForAssessment(int $assessmentId, int $competencyId, float $expectedLevel, float $actualLevel): array
    {
        $checkedKeys = DB::table('assessment_indicator_results')
            ->where('assessment_id', $assessmentId)
            ->where('competency_id', $competencyId)
            ->where('is_checked', true)
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

    private function currentUserIdpPayload(User $user): ?array
    {
        $year = (int) (DB::table('assessment_rounds')
            ->where('is_active', true)
            ->orderByDesc('year')
            ->value('year') ?: ((int) now()->format('Y') + 543));

        $idp = DB::table('idps')
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->orderByDesc('id')
            ->first();

        if (! $idp) {
            return null;
        }

        $items = DB::table('idp_items')
            ->where('idp_items.idp_id', $idp->id)
            ->select(
                'idp_items.id',
                'idp_items.competency_gap_id',
                'idp_items.goal',
                'idp_items.success_criteria',
                'idp_items.status',
                'idp_items.submission_version',
                'idp_items.current_review_step',
                'idp_items.submitted_at',
                'idp_items.approved_at',
                'idp_items.reject_comment'
            )
            ->orderBy('idp_items.id')
            ->get();
        $activityColumns = [
            'idp_activities.id',
            'idp_activities.idp_item_id',
            'idp_activities.learning_catalog_id',
            'idp_activities.idp_learning_method_id',
            'idp_activities.activity_name',
            'idp_activities.weight_percent',
            'idp_activities.start_date',
            'idp_activities.end_date',
            'idp_activities.description as activity_description',
            'idp_activities.document_reference_number',
            'learning_method_types.key as method_key',
        ];

        if (Schema::hasColumn('idp_activities', 'form_code')) {
            $activityColumns[] = 'idp_activities.form_code';
        }

        if (Schema::hasColumn('idp_activities', 'form_details')) {
            $activityColumns[] = 'idp_activities.form_details';
        }

        $activitiesByItem = DB::table('idp_activities')
            ->leftJoin('learning_method_types', 'idp_activities.method_type_id', '=', 'learning_method_types.id')
            ->whereIn('idp_activities.idp_item_id', $items->pluck('id'))
            ->select($activityColumns)
            ->orderBy('idp_activities.id')
            ->get()
            ->groupBy('idp_item_id');

        $payloadItems = $items
            ->groupBy('competency_gap_id')
            ->map(function ($legacyItems) use ($activitiesByItem): array {
                $first = $legacyItems->first();
                $goal = $legacyItems->pluck('goal')->first(fn ($value) => filled($value)) ?? '';
                $successCriteria = $legacyItems->pluck('success_criteria')->first(fn ($value) => filled($value)) ?? '';

                return [
                    'id' => (int) $first->id,
                    'competencyGapId' => (int) $first->competency_gap_id,
                    'goal' => $goal,
                    'successCriteria' => $successCriteria,
                    'status' => $first->status ?? 'draft',
                    'submissionVersion' => (int) ($first->submission_version ?? 0),
                    'currentReviewStep' => $first->current_review_step
                        ? (int) $first->current_review_step
                        : null,
                    'submittedAt' => $first->submitted_at,
                    'approvedAt' => $first->approved_at,
                    'rejectComment' => $first->reject_comment ?? '',
                    'activities' => $legacyItems
                        ->flatMap(fn (object $item) => $activitiesByItem[$item->id] ?? collect())
                        ->map(fn (object $activity): array => [
                            'id' => (int) $activity->id,
                            'methodKey' => $activity->method_key ?? '',
                            'developmentToolId' => $activity->idp_learning_method_id
                                ? (int) $activity->idp_learning_method_id
                                : null,
                            'learningCatalogId' => $activity->learning_catalog_id
                                ? (int) $activity->learning_catalog_id
                                : null,
                            'activityName' => $activity->activity_name ?? '',
                            'activityDescription' => $activity->activity_description ?? '',
                            'documentReferenceNumber' => $activity->document_reference_number ?? '',
                            'weightPercent' => $activity->weight_percent === null
                                ? ''
                                : (float) $activity->weight_percent,
                            'startDate' => $activity->start_date ?? '',
                            'endDate' => $activity->end_date ?? '',
                            'formCode' => $activity->form_code ?? '',
                            'formDetails' => $this->decodeJsonObject($activity->form_details ?? null),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'id' => (int) $idp->id,
            'year' => (int) $idp->year,
            'status' => $idp->status,
            'submittedAt' => $idp->submitted_at,
            'items' => $payloadItems,
        ];
    }

    private function idpReviewItemsForReviewer(User $reviewer): array
    {
        $items = DB::table('idp_items')
            ->join('idps', 'idp_items.idp_id', '=', 'idps.id')
            ->join('users', 'idps.user_id', '=', 'users.id')
            ->join('competency_gaps', 'idp_items.competency_gap_id', '=', 'competency_gaps.id')
            ->join('competencies', 'competency_gaps.competency_id', '=', 'competencies.id')
            ->where(function ($query) use ($reviewer): void {
                foreach ([1, 2, 3] as $step) {
                    $method = $step === 1 ? 'where' : 'orWhere';
                    $query->{$method}(function ($query) use ($reviewer, $step): void {
                        $query->where("users.supervisor_id_{$step}", $reviewer->id)
                            ->where('idp_items.current_review_step', $step)
                            ->where('idp_items.status', "review_step_{$step}");
                    });
                }
            })
            ->select(
                'idp_items.id',
                'idp_items.goal',
                'idp_items.success_criteria',
                'idp_items.submission_version',
                'idp_items.current_review_step',
                'idp_items.submitted_at',
                'users.sso as user_sso',
                'users.name as user_name',
                'users.title as user_title',
                'users.position as user_position',
                'users.department as user_department',
                'competencies.code as competency_code',
                'competencies.name as competency_name'
            )
            ->orderBy('idp_items.submitted_at')
            ->get();

        $activities = DB::table('idp_activities')
            ->leftJoin('learning_method_types', 'idp_activities.method_type_id', '=', 'learning_method_types.id')
            ->whereIn('idp_activities.idp_item_id', $items->pluck('id'))
            ->select(
                'idp_activities.id',
                'idp_activities.idp_item_id',
                'idp_activities.activity_name',
                'idp_activities.weight_percent',
                'idp_activities.start_date',
                'idp_activities.end_date',
                'idp_activities.document_reference_number',
                'learning_method_types.label as method_label'
            )
            ->orderBy('idp_activities.id')
            ->get()
            ->groupBy('idp_item_id');
        $history = DB::table('idp_item_reviews')
            ->join('users', 'idp_item_reviews.reviewer_id', '=', 'users.id')
            ->whereIn('idp_item_reviews.idp_item_id', $items->pluck('id'))
            ->select(
                'idp_item_reviews.idp_item_id',
                'idp_item_reviews.submission_version',
                'idp_item_reviews.review_step',
                'idp_item_reviews.decision',
                'idp_item_reviews.comment',
                'idp_item_reviews.decided_at',
                'users.name as reviewer_name',
                'users.title as reviewer_title'
            )
            ->orderByDesc('idp_item_reviews.submission_version')
            ->orderBy('idp_item_reviews.review_step')
            ->get()
            ->groupBy('idp_item_id');

        return $items->map(fn (object $item): array => [
            'id' => (int) $item->id,
            'userSso' => $item->user_sso ?: '',
            'userName' => trim(($item->user_title ?: '').$item->user_name),
            'userPosition' => $item->user_position ?: '',
            'userDepartment' => $item->user_department ?: '',
            'competencyCode' => $item->competency_code,
            'competencyName' => $item->competency_name,
            'goal' => $item->goal ?: '',
            'successCriteria' => $item->success_criteria ?: '',
            'submissionVersion' => (int) $item->submission_version,
            'currentReviewStep' => (int) $item->current_review_step,
            'submittedAt' => $item->submitted_at,
            'activities' => ($activities[$item->id] ?? collect())
                ->map(fn (object $activity): array => [
                    'id' => (int) $activity->id,
                    'name' => $activity->activity_name ?: '',
                    'methodLabel' => $activity->method_label ?: '',
                    'weightPercent' => (float) ($activity->weight_percent ?? 0),
                    'startDate' => $activity->start_date ?: '',
                    'endDate' => $activity->end_date ?: '',
                    'documentReferenceNumber' => $activity->document_reference_number ?: '',
                ])
                ->values()
                ->all(),
            'reviewHistory' => ($history[$item->id] ?? collect())
                ->map(fn (object $review): array => [
                    'submissionVersion' => (int) $review->submission_version,
                    'reviewStep' => (int) $review->review_step,
                    'reviewerName' => trim(($review->reviewer_title ?: '').$review->reviewer_name),
                    'decision' => $review->decision,
                    'comment' => $review->comment ?: '',
                    'decidedAt' => $review->decided_at,
                ])
                ->values()
                ->all(),
        ])->values()->all();
    }

    private function currentUserApprovedIdpActivities(User $user): array
    {
        $latestUpdateIds = DB::table('idp_activity_updates')
            ->selectRaw('MAX(id) as id, activity_id')
            ->groupBy('activity_id');

        return DB::table('idp_activities')
            ->join('idp_items', 'idp_activities.idp_item_id', '=', 'idp_items.id')
            ->join('idps', 'idp_items.idp_id', '=', 'idps.id')
            ->join('competency_gaps', 'idp_items.competency_gap_id', '=', 'competency_gaps.id')
            ->join('competencies', 'competency_gaps.competency_id', '=', 'competencies.id')
            ->leftJoinSub($latestUpdateIds, 'latest_update_ids', function ($join): void {
                $join->on('idp_activities.id', '=', 'latest_update_ids.activity_id');
            })
            ->leftJoin('idp_activity_updates', 'latest_update_ids.id', '=', 'idp_activity_updates.id')
            ->where('idps.user_id', $user->id)
            ->where('idp_items.status', 'approved')
            ->select(
                'idp_activities.id',
                'idp_activities.activity_name',
                'idp_activities.start_date',
                'idp_activities.end_date',
                'competencies.code as competency_code',
                'competencies.name as competency_name',
                'idp_activity_updates.progress_note',
                'idp_activity_updates.percent_complete',
                'idp_activity_updates.evidence_url',
                'idp_activity_updates.evidence_description'
            )
            ->orderBy('competencies.code')
            ->orderBy('idp_activities.id')
            ->get()
            ->map(fn (object $activity): array => [
                'id' => (int) $activity->id,
                'competencyCode' => $activity->competency_code,
                'competencyName' => $activity->competency_name,
                'name' => $activity->activity_name,
                'startDate' => $activity->start_date,
                'endDate' => $activity->end_date,
                'latestProgressNote' => $activity->progress_note ?? '',
                'latestPercentComplete' => (int) ($activity->percent_complete ?? 0),
                'latestEvidenceUrl' => $activity->evidence_url ?? '',
                'latestEvidenceDescription' => $activity->evidence_description ?? '',
            ])
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
        $roleKey = DB::table('roles')->where('id', $roleId)->value('key');

        return $roleKey ? $this->normalizeRoleKey($roleKey) : 'employee';
    }

    private function roleKeyForUser(User $user): string
    {
        $roleKey = $user->relationLoaded('role')
            ? $user->role?->key
            : DB::table('roles')->where('id', $user->role_id)->value('key');

        return $this->normalizeRoleKey($roleKey ?: $this->roleKeyFromId($user->role_id));
    }

    private function rolesPayload()
    {

        return DB::table('roles')
            ->orderBy('id')
            ->get(['id', 'key', 'name_th', 'name_en'])
            ->map(fn (object $role) => [
                'id' => $role->id,
                'key' => $role->key,
                'label' => $role->name_th,
                'labelEn' => $role->name_en,
            ]);
    }

    private function roleIdByKey(string $roleKey): ?int
    {
        return DB::table('roles')->where('key', $roleKey)->value('id');
    }

    private function learningCatalogItems()
    {
        $competencyIdsByCatalog = DB::table('learning_catalog_competency')
            ->select('learning_catalog_id', 'competency_id')
            ->orderBy('competency_id')
            ->get()
            ->groupBy('learning_catalog_id')
            ->map(fn ($items) => $items->pluck('competency_id')->values());
        $formCodesByDeliveryType = Schema::hasColumn('learning_catalog_delivery_types', 'form_code')
            ? DB::table('learning_catalog_delivery_types')->pluck('form_code', 'key')
            : collect(['e_learning' => 'form_9_field_trip', 'in_class' => 'form_10_training']);

        $columns = [
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
            'learning_method_types.label as method_label',
        ];

        return DB::table('learning_catalogs')
            ->leftJoin('learning_method_types', 'learning_catalogs.method_type_id', '=', 'learning_method_types.id')
            ->select($columns)
            ->orderBy('learning_catalogs.name')
            ->get()
            ->map(fn (object $item) => [
                'id' => $item->id,
                'code' => $item->code ?? '',
                'name' => $item->name,
                'methodKey' => $item->method_key,
                'methodLabel' => $item->method_label,
                'deliveryType' => $item->delivery_type ?? 'e_learning',
                'formCode' => $formCodesByDeliveryType[$item->delivery_type ?? 'e_learning'] ?? 'form_10_training',
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
        $columns = ['id', 'code', 'focus_type', 'title', 'template_file_name', 'is_active'];
        if (Schema::hasColumn('idp_learning_methods', 'form_code')) {
            $columns[] = 'form_code';
        }

        return DB::table('idp_learning_methods')
            ->select($columns)
            ->whereIn('focus_type', ['experiential', 'social'])
            ->orderBy('focus_type')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (object $item) => [
                'id' => $item->id,
                'code' => $item->code ?? '',
                'focusType' => $item->focus_type,
                'title' => $item->title,
                'formCode' => $item->form_code ?? '',
                'templateFileName' => $item->template_file_name ?? '',
                'isActive' => (bool) $item->is_active,
            ]);
    }

    private function idpDeliveryTypeSettings(): array
    {
        $defaults = [
            'e_learning' => [
                'value' => 'e_learning',
                'code' => '',
                'label' => 'การฝึกอบรมออนไลน์ (e-Learning)',
                'formCode' => 'form_9_field_trip',
            ],
            'in_class' => [
                'value' => 'in_class',
                'code' => '',
                'label' => 'การฝึกอบรมในห้องเรียน (In Class Training)',
                'formCode' => 'form_10_training',
            ],
        ];

        if (!Schema::hasTable('learning_catalog_delivery_types')) {
            return array_values($defaults);
        }

        $deliveryColumns = ['key', 'code', 'name_th', 'name_en', 'is_active'];
        if (Schema::hasColumn('learning_catalog_delivery_types', 'form_code')) {
            $deliveryColumns[] = 'form_code';
        }

        $deliveryTypes = DB::table('learning_catalog_delivery_types')
            ->whereIn('key', array_keys($defaults))
            ->get($deliveryColumns)
            ->keyBy('key');

        return collect($defaults)
            ->map(function (array $item, string $deliveryType) use ($deliveryTypes) {
                $row = $deliveryTypes[$deliveryType] ?? null;

                return [
                    ...$item,
                    'code' => $row->code ?? $item['code'],
                    'label' => $row
                        ? trim($row->name_th.' '.($row->name_en ? "({$row->name_en})" : ''))
                        : $item['label'],
                    'formCode' => $row->form_code ?? ($deliveryType === 'e_learning' ? 'form_9_field_trip' : 'form_10_training'),
                    'isActive' => $row ? (bool) $row->is_active : true,
                ];
            })
            ->values()
            ->all();
    }
}

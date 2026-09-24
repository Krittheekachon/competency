<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentRoundReadinessService
{
    private const SELF_ASSESSMENT_ROLE_KEYS = [
        'employee',
        'supervisor',
        'dept_head',
        'division_head',
        'academic_department_head',
        'manager_dept',
        'hr',
    ];

    public function check(int $roundId): array
    {
        $round = DB::table('assessment_rounds')->where('id', $roundId)->first([
            'id',
            'self_assess_start',
            'self_assess_end',
            'supervisor_assess_end',
        ]);

        if (! $round) {
            return [
                'ready' => false,
                'eligibleUserCount' => 0,
                'issues' => [['key' => 'round', 'count' => 1, 'message' => 'ไม่พบรอบการประเมิน']],
            ];
        }

        $issues = collect();
        $missingDates = collect([
            $round->self_assess_start,
            $round->self_assess_end,
            $round->supervisor_assess_end,
        ])->filter(fn ($date): bool => blank($date))->count();

        if ($missingDates > 0) {
            $issues->push([
                'key' => 'dates',
                'count' => $missingDates,
                'message' => 'กรุณากำหนดวันเริ่ม วันสิ้นสุดการประเมิน และวันสิ้นสุดการตรวจของหัวหน้าให้ครบ',
            ]);
        }

        $eligibleUsers = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.is_active', true)
            ->whereIn('roles.key', self::SELF_ASSESSMENT_ROLE_KEYS)
            ->get(['users.id', 'users.position_id', 'users.level_id']);
        $eligibleUserIds = $eligibleUsers->pluck('id')->map(fn ($id): int => (int) $id);

        $missingStructureCount = $eligibleUsers
            ->filter(fn (object $user): bool => ! $user->position_id || ! $user->level_id)
            ->count();
        if ($missingStructureCount > 0) {
            $issues->push([
                'key' => 'structure',
                'count' => $missingStructureCount,
                'message' => "มีบุคลากร {$missingStructureCount} คนที่ยังไม่ได้กำหนดตำแหน่งหรือระดับตำแหน่ง",
            ]);
        }

        $mappedUserIds = $eligibleUsers
            ->filter(fn (object $user): bool => (bool) $user->position_id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);
        $missingCompetencyCount = $mappedUserIds->isEmpty()
            ? 0
            : DB::table('users')
                ->whereIn('users.id', $mappedUserIds)
                ->whereNotExists(function ($query) use ($roundId): void {
                    $query->selectRaw('1')
                        ->from('position_competencies')
                        ->whereColumn('position_competencies.position_id', 'users.position_id')
                        ->where('position_competencies.assessment_round_id', $roundId);
                })
                ->count();
        if ($missingCompetencyCount > 0) {
            $issues->push([
                'key' => 'competencies',
                'count' => $missingCompetencyCount,
                'message' => "มีบุคลากร {$missingCompetencyCount} คนที่ตำแหน่งยังไม่มีสมรรถนะในรอบนี้",
            ]);
        }

        foreach ([
            'assessment' => 'ลำดับผู้ตรวจการประเมิน',
            'idp' => 'ลำดับผู้ตรวจ IDP',
        ] as $chainType => $label) {
            $missingChainCount = $eligibleUserIds->isEmpty()
                ? 0
                : DB::table('users')
                    ->whereIn('users.id', $eligibleUserIds)
                    ->whereNotExists(function ($query) use ($chainType): void {
                        $query->selectRaw('1')
                            ->from('user_reviewer_steps')
                            ->join('users as reviewers', 'reviewers.id', '=', 'user_reviewer_steps.reviewer_id')
                            ->whereColumn('user_reviewer_steps.user_id', 'users.id')
                            ->where('user_reviewer_steps.chain_type', $chainType)
                            ->where('reviewers.is_active', true);
                    })
                    ->count();

            if ($missingChainCount > 0) {
                $issues->push([
                    'key' => $chainType.'_reviewers',
                    'count' => $missingChainCount,
                    'message' => "มีบุคลากร {$missingChainCount} คนที่ยังไม่มี{$label}",
                ]);
            }
        }

        return [
            'ready' => $issues->isEmpty(),
            'eligibleUserCount' => $eligibleUsers->count(),
            'issues' => $issues->values()->all(),
        ];
    }

    public function assertReady(int $roundId): void
    {
        $result = $this->check($roundId);

        if ($result['ready']) {
            return;
        }

        throw ValidationException::withMessages([
            'round' => collect($result['issues'])->pluck('message')->implode(' · '),
        ]);
    }
}

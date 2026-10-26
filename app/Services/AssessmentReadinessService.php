<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssessmentReadinessService
{
    public function __construct() {}

    public function status(bool $organizationReady, bool $ssoReviewPending, array $competencies, array $reviewerSteps, bool $reviewerRequired = true): array
    {
        if ($ssoReviewPending) return ['key' => 'sso_changed', 'label' => 'ข้อมูล SSO เปลี่ยนจากที่จัดไว้'];
        if (! $organizationReady) return ['key' => 'structure', 'label' => 'รอจัดโครงสร้าง'];
        if ($competencies === []) return ['key' => 'competencies', 'label' => 'รอกำหนดสมรรถนะ'];
        if (collect($competencies)->contains(fn (array $row): bool => ($row['expectedLevel'] ?? null) === null)) return ['key' => 'expected_level', 'label' => 'รอกำหนด Expected Level'];
        if ($reviewerRequired && $reviewerSteps === []) return ['key' => 'reviewers', 'label' => 'รอกำหนดสายผู้ประเมิน'];
        return ['key' => 'ready', 'label' => 'พร้อมประเมิน'];
    }

    public function organizationReady(User $user): bool
    {
        $worklineId = DB::table('worklines')->where('name', $user->workline)->value('id');
        if (! $worklineId || ! $user->position_id || ! $user->level_id) return false;
        $position = DB::table('positions')->where('id', $user->position_id)->first(['id', 'name', 'job_family_id', 'support_unit_id']);
        $level = DB::table('levels')->where('id', $user->level_id)->first(['id', 'name', 'workline_id', 'job_family_id']);
        if (! $position || ! $level || $position->name !== $user->position || $level->name !== $user->level || (int) $level->workline_id !== (int) $worklineId) return false;

        $department = (string) ($user->department ?? '');
        $path = array_values(array_filter(array_map('trim', explode(' > ', $department))));
        $isSupportPath = isset($path[0]) && DB::table('support_departments')->where('name', $path[0])->exists();
        if ($isSupportPath) {
            $workExists = count($path) >= 2 && DB::table('support_works')->join('support_departments', 'support_works.support_department_id', '=', 'support_departments.id')
                ->where('support_departments.name', $path[0])->where('support_works.name', $path[1])->exists();
            $unitId = count($path) === 3 ? DB::table('support_units')->join('support_works', 'support_units.support_work_id', '=', 'support_works.id')
                ->join('support_departments', 'support_works.support_department_id', '=', 'support_departments.id')
                ->where('support_departments.name', $path[0])->where('support_works.name', $path[1])->where('support_units.name', $path[2])
                ->value('support_units.id') : null;
            $roleKey = $user->role?->key ?? DB::table('roles')->where('id', $user->role_id)->value('key');
            $roleKey = match ($roleKey) { 'manager_dept' => 'dept_head', 'manager' => 'dean', default => $roleKey };
            $validPath = match ($roleKey) {
                'division_head' => count($path) === 1 || (count($path) === 2 && $workExists) || (count($path) === 3 && $unitId),
                'dept_head' => (count($path) === 2 && $workExists) || (count($path) === 3 && $unitId),
                default => count($path) === 3 && $unitId,
            };
            if (! $validPath) return false;

            $globalPositionMatches = DB::table('positions')->join('job_families', 'positions.job_family_id', '=', 'job_families.id')
                ->where('positions.id', $position->id)->where('job_families.workline_id', $worklineId)->whereNull('positions.support_unit_id')->exists();
            if ($position->support_unit_id && (int) $position->support_unit_id !== (int) $unitId) return false;
            if (! $position->support_unit_id && ! $globalPositionMatches) return false;
        } else {
            $familyName = trim(explode(' > ', $department)[0] ?? '');
            if (! $position->job_family_id || ! DB::table('job_families')->where('id', $position->job_family_id)
                ->where('workline_id', $worklineId)->where('name', $familyName)->exists()) return false;
            if ($level->job_family_id !== null && (int) $level->job_family_id !== (int) $position->job_family_id) return false;
        }

        if ($isSupportPath && $level->job_family_id !== null) return false;
        return true;
    }
}

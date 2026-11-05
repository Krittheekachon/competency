<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class UserOrganizationPresenter
{
    /** @var array<int, array<string, ?string>> */
    private array $positionCache = [];

    /** @return array{type: string, departmentName: ?string, workName: ?string, unitName: ?string, display: string} */
    public function forUser(object $user): array
    {
        return $this->canonicalForUser($user)
            ?? $this->fromLegacyValues((string) $user->workline, (string) $user->department);
    }

    /** @return array{type: string, departmentName: ?string, workName: ?string, unitName: ?string, display: string}|null */
    public function canonicalForUser(object $user): ?array
    {
        $structure = $user->position_id ? $this->forPosition((int) $user->position_id) : null;

        if (! $structure && app(SsoWorklineNormalizer::class)->structureType($user->workline) === 'management'
            && trim((string) $user->department) === '') {
            return ['type' => 'management', 'departmentName' => null, 'workName' => null, 'unitName' => null, 'display' => $user->workline];
        }

        if (($structure['workName'] ?? null) || ($structure['unitName'] ?? null)) {
            $workDisplay = $this->withPrefix($structure['workName'], 'งาน');
            $unitDisplay = $this->withPrefix($structure['unitName'], 'หน่วย');

            return [
                'type' => 'support',
                'departmentName' => $structure['departmentName'],
                'workName' => $structure['workName'],
                'unitName' => $structure['unitName'],
                'display' => collect([$workDisplay, $unitDisplay])
                    ->filter()
                    ->implode(' · '),
            ];
        }

        if ($structure['academicDepartmentName'] ?? null) {
            $type = app(SsoWorklineNormalizer::class)->structureType($structure['worklineName']) ?? 'other';
            return [
                'type' => $type,
                'departmentName' => $structure['academicDepartmentName'],
                'workName' => null,
                'unitName' => null,
                'display' => $this->withPrefix($structure['academicDepartmentName'], $type === 'academic' ? 'ภาควิชา' : 'หน่วยงาน'),
            ];
        }

        return null;
    }

    /** @return array{type: string, departmentName: ?string, workName: ?string, unitName: ?string, display: string} */
    public function canonicalOrUnassignedForUser(object $user): array
    {
        if ($organization = $this->canonicalForUser($user)) {
            return $organization;
        }

        $type = app(SsoWorklineNormalizer::class)->structureType($user->workline) ?? 'other';
        $isSupport = $type === 'support';

        return [
            'type' => $type,
            'departmentName' => null,
            'workName' => null,
            'unitName' => null,
            'display' => $isSupport ? 'ยังไม่ระบุงาน/หน่วย' : 'ยังไม่ระบุภาควิชา/หน่วยงาน',
        ];
    }

    /** @return array<string, ?string>|null */
    private function forPosition(int $positionId): ?array
    {
        if (array_key_exists($positionId, $this->positionCache)) {
            return $this->positionCache[$positionId];
        }

        $row = DB::table('positions')
            ->leftJoin('support_units', 'positions.support_unit_id', '=', 'support_units.id')
            ->leftJoin('support_works', 'support_units.support_work_id', '=', 'support_works.id')
            ->leftJoin('support_departments', 'support_works.support_department_id', '=', 'support_departments.id')
            ->leftJoin('job_families', 'positions.job_family_id', '=', 'job_families.id')
            ->leftJoin('worklines', 'job_families.workline_id', '=', 'worklines.id')
            ->where('positions.id', $positionId)
            ->select([
                'support_departments.name as department_name',
                'support_works.name as work_name',
                'support_units.name as unit_name',
                'job_families.name as academic_department_name',
                'worklines.name as workline_name',
            ])
            ->first();

        return $this->positionCache[$positionId] = $row ? [
            'departmentName' => $row->department_name,
            'workName' => $row->work_name,
            'unitName' => $row->unit_name,
            'academicDepartmentName' => $row->academic_department_name,
            'worklineName' => $row->workline_name,
        ] : null;
    }

    /** @return array{type: string, departmentName: ?string, workName: ?string, unitName: ?string, display: string} */
    private function fromLegacyValues(string $workline, string $department): array
    {
        $parts = collect(explode(' > ', $department))
            ->map(fn (string $part): string => trim($part))
            ->filter()
            ->values();
        $type = app(SsoWorklineNormalizer::class)->structureType($workline) ?? 'other';
        $isSupport = $type === 'support';

        if ($isSupport) {
            if ($parts->count() < 2) {
                return [
                    'type' => 'support',
                    'departmentName' => $parts->first(),
                    'workName' => null,
                    'unitName' => null,
                    'display' => 'ยังไม่ระบุงาน/หน่วย',
                ];
            }

            $workName = $parts->count() >= 3 ? $parts->get($parts->count() - 2) : $parts->first();
            $unitName = $parts->count() >= 2 ? $parts->last() : null;

            return [
                'type' => 'support',
                'departmentName' => $parts->count() >= 3 ? $parts->first() : null,
                'workName' => $workName,
                'unitName' => $unitName,
                'display' => collect([
                    $this->withPrefix($workName, 'งาน'),
                    $this->withPrefix($unitName, 'หน่วย'),
                ])->filter()->unique()->implode(' · '),
            ];
        }

        $departmentName = $parts->first() ?: null;

        return [
            'type' => $type,
            'departmentName' => $departmentName,
            'workName' => null,
            'unitName' => null,
            'display' => $this->withPrefix($departmentName, $type === 'academic' ? 'ภาควิชา' : 'หน่วยงาน') ?: '',
        ];
    }

    private function withPrefix(?string $value, string $prefix): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return str_starts_with($value, $prefix) ? $value : $prefix.' '.$value;
    }
}

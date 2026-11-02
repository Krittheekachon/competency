<?php

namespace App\Services;

use App\Models\User;

class FacultyAssessmentPermissionResolver
{
    public function canScore(User $reviewer, User $employee, string $competencyType): bool
    {
        $type = strtoupper(trim($competencyType));
        $isSupport = in_array($employee->workline, ['สายสนับสนุน', 'สายงานสนับสนุน'], true);
        $isAcademic = in_array($employee->workline, ['สายวิชาการ', 'สายงานวิชาการ'], true);
        $isExecutive = $employee->hasRole(['deputy_dean', 'assistant_dean']);

        if ($isSupport) {
            return $reviewer->isDeputyDean() && in_array($type, ['CC', 'FC'], true);
        }

        if (! $isAcademic) {
            return false;
        }

        if ($isExecutive && $type === 'MC') {
            return $reviewer->isDean();
        }

        if ($type === 'MC') {
            return false;
        }

        return $reviewer->isAcademicDepartmentHead() && in_array($type, ['CC', 'FC'], true);
    }
}

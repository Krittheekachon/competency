<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Services\FacultyAssessmentPermissionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FacultyAssessmentPermissionResolverTest extends TestCase
{
    #[DataProvider('permissionCases')]
    public function test_scoring_permission_depends_on_reviewer_target_and_competency_type(
        string $reviewerRole,
        string $employeeRole,
        string $workline,
        string $type,
        bool $expected,
    ): void {
        $resolver = new FacultyAssessmentPermissionResolver;

        $this->assertSame($expected, $resolver->canScore(
            $this->userWithRole($reviewerRole),
            $this->userWithRole($employeeRole, $workline),
            $type,
        ));
    }

    public static function permissionCases(): array
    {
        return [
            'support CC by deputy' => ['deputy_dean', 'employee', 'สายสนับสนุน', 'CC', true],
            'support FC by deputy' => ['deputy_dean', 'employee', 'สายสนับสนุน', 'FC', true],
            'support FC1 sub-type by deputy' => ['deputy_dean', 'employee', 'สายสนับสนุน', 'FC1', true],
            'support FC2 sub-type by deputy' => ['deputy_dean', 'employee', 'สายสนับสนุน', 'FC2', true],
            'support by assistant is approval only' => ['assistant_dean', 'employee', 'สายสนับสนุน', 'CC', false],
            'support MC is never scored by deputy' => ['deputy_dean', 'employee', 'สายสนับสนุน', 'MC', false],
            'academic CC by department head' => ['academic_department_head', 'employee', 'สายวิชาการ', 'CC', true],
            'academic FC by department head' => ['academic_department_head', 'employee', 'สายวิชาการ', 'FC', true],
            'executive CC by department head' => ['academic_department_head', 'deputy_dean', 'สายวิชาการ', 'CC', true],
            'executive MC by dean' => ['dean', 'assistant_dean', 'สายวิชาการ', 'MC', true],
            'executive CC cannot be changed by dean' => ['dean', 'deputy_dean', 'สายวิชาการ', 'CC', false],
            'executive MC cannot be changed by department head' => ['academic_department_head', 'assistant_dean', 'สายวิชาการ', 'MC', false],
            'unknown workline is approval only' => ['deputy_dean', 'employee', 'สายอื่น', 'CC', false],
        ];
    }

    private function userWithRole(string $key, ?string $workline = null): User
    {
        $role = new Role(['key' => $key]);
        $user = new User(['workline' => $workline]);
        $user->setRelation('role', $role);

        return $user;
    }
}

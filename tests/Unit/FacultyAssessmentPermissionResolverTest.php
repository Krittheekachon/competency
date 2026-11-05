<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Services\FacultyAssessmentPermissionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class FacultyAssessmentPermissionResolverTest extends TestCase
{
    use RefreshDatabase;
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
            'management deputy MC still by dean' => ['dean', 'deputy_dean', 'สายบริหาร', 'MC', true],
            'management assistant MC still by dean' => ['dean', 'assistant_dean', 'สายบริหาร', 'MC', true],
            'management employee MC not by dean' => ['dean', 'employee', 'สายบริหาร', 'MC', false],
            'executive CC cannot be changed by dean' => ['dean', 'deputy_dean', 'สายวิชาการ', 'CC', false],
            'executive MC cannot be changed by department head' => ['academic_department_head', 'assistant_dean', 'สายวิชาการ', 'MC', false],
            'unknown workline is approval only' => ['deputy_dean', 'employee', 'สายอื่น', 'CC', false],
            'legacy academic alias' => ['academic_department_head', 'employee', 'สายงานวิชาการ', 'CC', true],
            'legacy support alias' => ['deputy_dean', 'employee', 'สนับสนุน', 'FC1', true],
        ];
    }

    public function test_legacy_workline_does_not_require_a_canonical_position(): void
    {
        $resolver = new FacultyAssessmentPermissionResolver;
        $reviewer = $this->userWithRole('deputy_dean');
        $employee = new User(['workline' => 'สายสนับสนุน']);
        $employee->setRelation('role', new Role(['key' => 'employee']));
        $this->assertTrue($resolver->canScore($reviewer, $employee, 'CC'));
        $this->assertFalse($resolver->canScore($reviewer, $employee, 'MC'));
        $employee->position_title_id = DB::table('position_titles')->insertGetId([
            'name' => 'Legacy unclassified', 'normalized_name' => 'legacy-unclassified',
            'workline_type' => null,
        ]);
        $this->assertTrue($resolver->canScore($reviewer, $employee, 'FC'));
        $employee->workline = 'สายอื่น';
        $this->assertFalse($resolver->canScore($reviewer, $employee, 'CC'));
    }

    public function test_existing_workline_takes_priority_over_conflicting_title_classification(): void
    {
        $employee = $this->userWithRole('employee', 'สายวิชาการ');
        DB::table('position_titles')->where('id', $employee->position_title_id)->update(['workline_type' => 'support']);
        $resolver = new FacultyAssessmentPermissionResolver;
        $this->assertTrue($resolver->canScore($this->userWithRole('academic_department_head'), $employee, 'CC'));
        $this->assertFalse($resolver->canScore($this->userWithRole('deputy_dean'), $employee, 'CC'));
    }

    private function userWithRole(string $key, ?string $workline = null): User
    {
        $role = new Role(['key' => $key]);
        $user = new User(['workline' => $workline]);
        if ($workline !== null) {
            $user->position_title_id = DB::table('position_titles')->insertGetId([
                'name' => 'QA '.$key, 'normalized_name' => 'qa-'.$key,
                'workline_type' => match ($workline) { 'สายสนับสนุน' => 'support', 'สายวิชาการ' => 'academic', default => 'unknown' },
            ]);
        }
        $user->setRelation('role', $role);

        return $user;
    }
}

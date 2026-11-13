<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\FacultyAssessmentPermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FacultyAssessmentPermissionResolverTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('grantCases')]
    public function test_scoring_permission_follows_the_grant_on_the_reviewer_step(?string $grant, string $type, bool $expected): void
    {
        $employee = User::factory()->create();
        $reviewer = User::factory()->create();
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employee->id, 'chain_type' => 'assessment', 'step_order' => 1,
            'reviewer_id' => $reviewer->id, 'scoring_types' => $grant,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame($expected, app(FacultyAssessmentPermissionResolver::class)->canScore($reviewer, $employee, $type));
    }

    public static function grantCases(): array
    {
        return [
            'no grant is approval only' => [null, 'CC', false],
            'CC granted' => ['CC', 'CC', true],
            'CC grant does not cover FC' => ['CC', 'FC', false],
            'FC grant covers FC1' => ['CC,FC', 'FC1', true],
            'FC grant covers FC2' => ['FC', 'FC2', true],
            'MC granted' => ['MC', 'MC', true],
            'lowercase type is normalised' => ['CC', 'cc', true],
        ];
    }

    public function test_a_user_outside_the_chain_never_scores(): void
    {
        $employee = User::factory()->create();
        $outsider = User::factory()->create();

        $this->assertFalse(app(FacultyAssessmentPermissionResolver::class)->canScore($outsider, $employee, 'CC'));
    }
}

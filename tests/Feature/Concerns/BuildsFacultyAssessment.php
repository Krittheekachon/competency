<?php

namespace Tests\Feature\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;

trait BuildsFacultyAssessment
{
    protected function activeFacultyRound(): int
    {
        return (int) DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบวิธีคณะ',
            'year' => 2569,
            'assessment_method' => 'faculty',
            'assessment_method_version' => 1,
            'self_assess_start' => now()->subDay()->toDateString(),
            'self_assess_end' => now()->addMonth()->toDateString(),
            'supervisor_assess_end' => now()->addMonths(2)->toDateString(),
            'is_active' => true,
            'snapshot_required' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function makeAssessable(User $employee, int $roundId, string $workline = 'สายวิชาการ'): array
    {
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => $workline, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'ภาควิชาทดสอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $positionId = DB::table('positions')->insertGetId([
            'job_family_id' => $jobFamilyId,
            'name' => 'อาจารย์',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $levelId = DB::table('levels')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'ระดับวิชาการ',
            'expected_level' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $typeId = DB::table('competency_types')->insertGetId([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'Core Competency',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $competencyId = DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId,
            'code' => 'CC-FACULTY-01',
            'name' => 'สมรรถนะวิธีคณะ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $levelIds = [];
        foreach ([1, 3, 5] as $level) {
            $competencyLevelId = DB::table('competency_levels')->insertGetId([
                'competency_id' => $competencyId,
                'level' => $level,
                'description' => "เกณฑ์ระดับ {$level}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $levelIds[$level] = $competencyLevelId;

            foreach (range(1, 3) as $indicator) {
                DB::table('comp_level_indicators')->insert([
                    'competency_level_id' => $competencyLevelId,
                    'description' => "พฤติกรรม {$level}.{$indicator}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('position_competencies')->insert([
            'assessment_round_id' => $roundId,
            'position_id' => $positionId,
            'competency_id' => $competencyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $employee->forceFill([
            'workline' => $workline,
            'department' => 'ภาควิชาทดสอบ',
            'position' => 'อาจารย์',
            'level' => 'ระดับวิชาการ',
            'position_id' => $positionId,
            'level_id' => $levelId,
        ])->save();

        return [$competencyId, $levelIds];
    }

    protected function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }

    protected function assignReviewer(User $employee, User $reviewer, int $step = 1): void
    {
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employee->id,
            'chain_type' => 'assessment',
            'step_order' => $step,
            'reviewer_id' => $reviewer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function submittedAssessment(User $employee, int $roundId, int $competencyId, int $score): int
    {
        $assessmentId = DB::table('assessments')->insertGetId([
            'assessment_round_id' => $roundId,
            'user_id' => $employee->id,
            'competency_id' => $competencyId,
            'score' => $score,
            'status' => 'self_submitted',
            'last_draft_saved_at' => now(),
            'self_submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('competency_gaps')->insert([
            'assessment_id' => $assessmentId,
            'competency_id' => $competencyId,
            'expected_level' => 3,
            'actual_level' => $score,
            'gap' => $score - 3,
            'requires_idp' => $score < 3,
            'status' => 'self_submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $assessmentId;
    }
}

<?php

namespace Database\Seeders;

use App\Services\PositionTitleResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Local walkthrough data for the faculty assessment method.
 *
 * Run explicitly: php artisan db:seed --class=DemoFacultyAssessmentSeeder
 * It makes a faculty round active, so use it on a demo database only.
 */
class DemoFacultyAssessmentSeeder extends Seeder
{
    private const INDICATORS_PER_LEVEL = [1 => 3, 2 => 2, 3 => 3, 4 => 2, 5 => 3];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DemoFacultyAssessmentSeeder ใช้ได้เฉพาะ local/testing');
        }

        DB::transaction(function (): void {
            $now = now();
            DB::table('assessment_rounds')->update(['is_active' => false, 'updated_at' => $now]);
            $roundId = $this->upsertAndGetId('assessment_rounds', ['name' => 'รอบทดสอบวิธีคณะ'], [
                'year' => (int) $now->year + 543,
                'assessment_method' => 'faculty',
                'assessment_method_version' => 1,
                'self_assess_start' => $now->copy()->subDay()->toDateString(),
                'self_assess_end' => $now->copy()->addMonth()->toDateString(),
                'supervisor_assess_end' => $now->copy()->addMonths(2)->toDateString(),
                'is_active' => true,
                'snapshot_required' => false,
            ]);

            $support = $this->organization('สายสนับสนุน', 'งานทดสอบสนับสนุน', 'นักวิชาการคอมพิวเตอร์', 'ชำนาญการ');
            // Support staff sit in a unit so HR can try unit-specific FC counts.
            $departmentId = $this->upsertAndGetId('support_departments', ['name' => 'ฝ่ายทดสอบวิธีคณะ'], []);
            $workId = $this->upsertAndGetId('support_works', ['support_department_id' => $departmentId, 'name' => 'งานทดสอบสนับสนุน'], []);
            $unitId = $this->upsertAndGetId('support_units', ['support_work_id' => $workId, 'name' => 'หน่วยระบบสารสนเทศ'], []);
            $this->upsertAndGetId('support_units', ['support_work_id' => $workId, 'name' => 'หน่วยบริการการศึกษา'], []);
            DB::table('positions')->where('id', $support['positionId'])->update(['support_unit_id' => $unitId]);
            $academic = $this->organization('สายวิชาการ', 'ภาควิชาทดสอบ', 'อาจารย์', 'ผู้ช่วยศาสตราจารย์');

            $cc1 = $this->competency('CC', 'CC-F01', 'การบริการที่ดี');
            $cc2 = $this->competency('CC', 'CC-F02', 'การทำงานเป็นทีม');
            $fc1 = $this->competency('FC1', 'FC1-F01', 'การพัฒนาระบบสารสนเทศ');
            $mc1 = $this->competency('MC', 'MC-F01', 'ภาวะผู้นำเชิงกลยุทธ์');

            $this->assignCompetencies($roundId, $support['positionId'], [$cc1, $cc2, $fc1]);
            $this->assignCompetencies($roundId, $academic['positionId'], [$cc1, $cc2]);
            $deputyPositionId = $this->position($academic['jobFamilyId'], 'รองคณบดี');
            $assistantPositionId = $this->position($academic['jobFamilyId'], 'ผู้ช่วยคณบดี');
            $this->assignCompetencies($roundId, $deputyPositionId, [$cc1, $cc2, $mc1]);
            $this->assignCompetencies($roundId, $assistantPositionId, [$cc1, $cc2, $mc1]);

            $titles = app(PositionTitleResolver::class);
            foreach ([$support['positionId'], $academic['positionId'], $deputyPositionId, $assistantPositionId] as $positionId) {
                $titles->forPositionId($positionId);
            }

            $deputy = $this->user('deputy.faculty@test.com', 'รองคณบดี ทดสอบ', 'deputy_dean', $academic, $deputyPositionId);
            $assistant = $this->user('assistant.faculty@test.com', 'ผู้ช่วยคณบดี ทดสอบ', 'assistant_dean', $academic, $assistantPositionId);
            $academicHead = $this->user('head.faculty@test.com', 'หัวหน้าภาควิชา ทดสอบ', 'academic_department_head', $academic);
            $dean = $this->user('dean.faculty@test.com', 'คณบดี ทดสอบ', 'dean', $academic);
            $supportStaff = $this->user('support.faculty@test.com', 'บุคลากรสายสนับสนุน ทดสอบ', 'employee', $support);
            $academicStaff = $this->user('academic.faculty@test.com', 'อาจารย์ ทดสอบ', 'employee', $academic);

            foreach (['assessment', 'idp'] as $chainType) {
                $this->chain($supportStaff, [$deputy, $assistant], $chainType);
                $this->chain($academicStaff, [$academicHead, $dean], $chainType);
                $this->chain($deputy, [$academicHead, $dean], $chainType);
            }
        });
    }

    private function organization(string $workline, string $jobFamily, string $position, string $level): array
    {
        $worklineId = $this->upsertAndGetId('worklines', ['name' => $workline], []);
        $jobFamilyId = $this->upsertAndGetId('job_families', ['workline_id' => $worklineId, 'name' => $jobFamily], []);

        return [
            'workline' => $workline,
            'jobFamily' => $jobFamily,
            'jobFamilyId' => $jobFamilyId,
            'position' => $position,
            'positionId' => $this->position($jobFamilyId, $position),
            'level' => $level,
            'levelId' => $this->upsertAndGetId('levels', ['workline_id' => $worklineId, 'name' => $level], [
                'expected_level' => 4,
            ]),
        ];
    }

    private function position(int $jobFamilyId, string $name): int
    {
        return $this->upsertAndGetId('positions', ['job_family_id' => $jobFamilyId, 'name' => $name], []);
    }

    private function competency(string $typeCode, string $code, string $name): int
    {
        $typeId = $this->upsertAndGetId('competency_types', ['code' => $typeCode], [
            'full_name' => $typeCode,
            'description' => $typeCode,
        ]);
        $competencyId = $this->upsertAndGetId('competencies', ['code' => $code], [
            'competency_type_id' => $typeId,
            'name' => $name,
            'detail' => "สมรรถนะทดสอบวิธีคณะ: {$name}",
        ]);

        foreach (self::INDICATORS_PER_LEVEL as $level => $count) {
            $levelId = $this->upsertAndGetId('competency_levels', ['competency_id' => $competencyId, 'level' => $level], [
                'description' => "ระดับ {$level}",
            ]);
            DB::table('comp_level_indicators')->where('competency_level_id', $levelId)->delete();
            foreach (range(1, $count) as $index) {
                DB::table('comp_level_indicators')->insert([
                    'competency_level_id' => $levelId,
                    'description' => "{$name} พฤติกรรมระดับ {$level}.{$index}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $competencyId;
    }

    private function assignCompetencies(int $roundId, int $positionId, array $competencyIds): void
    {
        foreach ($competencyIds as $competencyId) {
            $this->upsertAndGetId('position_competencies', [
                'assessment_round_id' => $roundId,
                'position_id' => $positionId,
                'competency_id' => $competencyId,
            ], []);
        }
    }

    private function user(string $email, string $name, string $role, array $organization, ?int $positionId = null): int
    {
        $positionId ??= $organization['positionId'];

        return $this->upsertAndGetId('users', ['email' => $email], [
            'name' => $name,
            'password' => Hash::make('password'),
            'role_id' => DB::table('roles')->where('key', $role)->value('id'),
            'workline' => $organization['workline'],
            'department' => $organization['jobFamily'],
            'position' => DB::table('positions')->where('id', $positionId)->value('name'),
            'level' => $organization['level'],
            'position_id' => $positionId,
            'position_title_id' => DB::table('positions')->where('id', $positionId)->value('position_title_id'),
            'level_id' => $organization['levelId'],
            'is_active' => true,
        ]);
    }

    private function chain(int $userId, array $reviewerIds, string $chainType): void
    {
        DB::table('user_reviewer_steps')->where('user_id', $userId)->where('chain_type', $chainType)->delete();
        foreach (array_values($reviewerIds) as $index => $reviewerId) {
            DB::table('user_reviewer_steps')->insert([
                'user_id' => $userId,
                'chain_type' => $chainType,
                'step_order' => $index + 1,
                'reviewer_id' => $reviewerId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function upsertAndGetId(string $table, array $keys, array $values): int
    {
        $now = now();
        if (DB::table($table)->where($keys)->exists()) {
            if ($values !== []) {
                DB::table($table)->where($keys)->update($values + ['updated_at' => $now]);
            }
        } else {
            DB::table($table)->insert($keys + $values + ['created_at' => $now, 'updated_at' => $now]);
        }

        return (int) DB::table($table)->where($keys)->value('id');
    }
}

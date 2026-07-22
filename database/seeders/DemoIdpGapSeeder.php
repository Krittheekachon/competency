<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DemoIdpGapSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $now = now();

            $this->ensureRoles($now);

            $employeeRoleId = $this->roleId('employee');
            $supervisorRoleId = $this->roleId('supervisor');
            $deptHeadRoleId = $this->roleId('dept_head');
            $deanRoleId = $this->roleId('dean');

            $worklineId = $this->upsertAndGetId('worklines', ['name' => 'สนับสนุน'], ['created_at' => $now, 'updated_at' => $now]);
            $jobFamilyId = $this->upsertAndGetId('job_families', ['workline_id' => $worklineId, 'name' => 'HR'], ['created_at' => $now, 'updated_at' => $now]);
            $positionId = $this->upsertAndGetId('positions', ['job_family_id' => $jobFamilyId, 'name' => 'นักทรัพยากรบุคคล'], ['created_at' => $now, 'updated_at' => $now]);
            $levelId = $this->upsertAndGetId('levels', ['name' => 'ปฏิบัติการ'], ['created_at' => $now, 'updated_at' => $now]);

            $supervisorId = $this->upsertUser('idp_supervisor@test.com', [
                'name' => 'นายหัวหน้า ไอดีพี',
                'title' => 'นาย',
                'first_name_th' => 'หัวหน้า',
                'last_name_th' => 'ไอดีพี',
                'role_id' => $supervisorRoleId,
                'workline' => 'สนับสนุน',
                'department' => 'HR',
                'position' => 'นักทรัพยากรบุคคล',
                'level' => 'ชำนาญการ',
                'position_id' => $positionId,
                'level_id' => $levelId,
                'is_active' => true,
                'updated_at' => $now,
            ]);

            $deptHeadId = $this->upsertUser('idp_dept_head@test.com', [
                'name' => 'นางหัวหน้างาน ไอดีพี',
                'title' => 'นาง',
                'first_name_th' => 'หัวหน้างาน',
                'last_name_th' => 'ไอดีพี',
                'role_id' => $deptHeadRoleId,
                'workline' => 'สนับสนุน',
                'department' => 'HR',
                'position' => 'นักทรัพยากรบุคคล',
                'level' => 'ชำนาญการพิเศษ',
                'position_id' => $positionId,
                'level_id' => $levelId,
                'is_active' => true,
                'updated_at' => $now,
            ]);

            $deanId = $this->upsertUser('idp_dean@test.com', [
                'name' => 'นายคณบดี ไอดีพี',
                'title' => 'นาย',
                'first_name_th' => 'คณบดี',
                'last_name_th' => 'ไอดีพี',
                'role_id' => $deanRoleId,
                'workline' => 'บริหาร',
                'department' => 'คณะวิศวกรรมศาสตร์',
                'position' => 'คณบดี',
                'level' => 'ผู้บริหาร',
                'is_active' => true,
                'updated_at' => $now,
            ]);

            $employeeId = $this->upsertUser('idp@test.com', [
                'name' => 'นายIDP Demo',
                'title' => 'นาย',
                'first_name_th' => 'IDP',
                'last_name_th' => 'Demo',
                'role_id' => $employeeRoleId,
                'workline' => 'สนับสนุน',
                'department' => 'HR',
                'position' => 'นักทรัพยากรบุคคล',
                'level' => 'ปฏิบัติการ',
                'position_id' => $positionId,
                'level_id' => $levelId,
                'supervisor_id_1' => $supervisorId,
                'supervisor_id_2' => $deptHeadId,
                'supervisor_id_3' => $deanId,
                'is_active' => true,
                'updated_at' => $now,
            ]);

            $competencyTypeId = $this->upsertAndGetId('competency_types', ['code' => 'CC'], [
                'full_name' => 'Core Competency',
                'description' => 'สมรรถนะหลักที่บุคลากรทุกตำแหน่งควรมีร่วมกัน',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $competencyId = $this->upsertAndGetId('competencies', ['code' => 'CC-IDP-DEMO'], [
                'competency_type_id' => $competencyTypeId,
                'name' => 'การมุ่งเน้นผู้เรียนและผู้รับบริการ (Customer First)',
                'detail' => 'ช่วยเหลือ เพื่อให้ผู้เรียนและผู้รับบริการได้รับประโยชน์สูงสุด อันนำไปสู่การสร้างผลลัพธ์ที่ยั่งยืนทั้งต่อผู้เรียน ผู้รับบริการ และสังคม',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->seedCompetencyLevels($competencyId, $now);

            $assessmentAttributes = [
                'user_id' => $employeeId,
                'competency_id' => $competencyId,
            ];
            $assessmentValues = [
                'status' => 'supervisor_2_submitted',
                'score' => 2.25,
                'note' => 'ควรพัฒนาเรื่องการรับฟังและการตอบสนองความต้องการของผู้รับบริการให้ชัดเจนขึ้น',
                'last_draft_saved_at' => $now,
                'self_submitted_at' => $now,
                'supervisor_1_submitted_at' => $now,
                'supervisor_2_submitted_at' => $now,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('assessments', 'assessment_round_id')) {
                $assessmentValues['assessment_round_id'] = $this->activeRoundId($now);
            }

            DB::table('assessments')->updateOrInsert($assessmentAttributes, [
                ...$assessmentValues,
                'created_at' => $now,
            ]);

            $assessmentId = DB::table('assessments')
                ->where($assessmentAttributes)
                ->value('id');

            DB::table('assessment_indicator_results')
                ->where('assessment_id', $assessmentId)
                ->where('competency_id', $competencyId)
                ->delete();

            foreach ($this->passedIndicatorKeys($competencyId) as $indicatorKey) {
                DB::table('assessment_indicator_results')->insert([
                    'assessment_id' => $assessmentId,
                    'competency_id' => $competencyId,
                    'indicator_key' => $indicatorKey,
                    'is_checked' => true,
                    'checked_by' => $employeeId,
                    'checked_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $scoreId = $this->upsertAndGetId('scores', [
                'assessment_id' => $assessmentId,
                'competency_id' => $competencyId,
                'assessor_id' => $deptHeadId,
            ], [
                'assessor_role' => 'dept_head',
                'score' => 2,
                'comment' => 'ขอให้วางแผนกิจกรรมที่ทำให้เห็นผลเรื่องการสื่อสารกับผู้รับบริการ และกำหนด KPI ที่วัดผลได้จริง',
                'status' => 'submitted',
                'submitted_at' => $now,
                'auto_saved_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('competency_gaps')->updateOrInsert([
                'assessment_id' => $assessmentId,
                'competency_id' => $competencyId,
            ], [
                'supervisor_2_score_id' => $scoreId,
                'expected_level' => 3,
                'actual_level' => 2.25,
                'gap' => -0.75,
                'requires_idp' => true,
                'status' => 'approved',
                'decided_at' => $now,
                'updated_at' => $now,
            ]);

            $this->ensureLearningMethods($now);
        });
    }

    private function ensureRoles($now): void
    {
        foreach ([
            ['key' => 'employee', 'name_th' => 'บุคลากร', 'name_en' => 'Employee'],
            ['key' => 'supervisor', 'name_th' => 'หัวหน้าหน่วย', 'name_en' => 'Supervisor'],
            ['key' => 'dept_head', 'name_th' => 'หัวหน้างาน', 'name_en' => 'Department Head'],
            ['key' => 'dean', 'name_th' => 'ผู้บริหารคณะ', 'name_en' => 'Dean'],
        ] as $role) {
            DB::table('roles')->updateOrInsert(['key' => $role['key']], [
                ...$role,
                'updated_at' => Schema::hasColumn('roles', 'updated_at') ? $now : null,
            ]);
        }
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }

    private function upsertUser(string $email, array $values): int
    {
        $now = now();

        DB::table('users')->updateOrInsert(['email' => $email], [
            'password' => Hash::make('password'),
            'created_at' => $now,
            ...$values,
            'updated_at' => $values['updated_at'] ?? $now,
        ]);

        return (int) DB::table('users')->where('email', $email)->value('id');
    }

    private function upsertAndGetId(string $table, array $keys, array $values): int
    {
        DB::table($table)->updateOrInsert($keys, $values);

        return (int) DB::table($table)->where($keys)->value('id');
    }

    private function seedCompetencyLevels(int $competencyId, $now): void
    {
        $indicatorsByLevel = [
            1 => [
                'สามารถระบุและจำแนกผู้เรียนและผู้รับบริการที่เกี่ยวข้องกับงานของตนได้อย่างเหมาะสม',
                'รับฟังความต้องการและข้อเสนอแนะเบื้องต้นของผู้เรียนและผู้รับบริการได้อย่างเต็มใจ',
            ],
            2 => [
                'ตอบสนองต่อความต้องการของผู้เรียนและผู้รับบริการตามบทบาทหน้าที่ได้อย่างเหมาะสม',
                'สื่อสารข้อมูลบริการที่เกี่ยวข้องได้ครบถ้วน ชัดเจน และสุภาพ',
            ],
            3 => [
                'วิเคราะห์ความคาดหวังของผู้รับบริการและปรับปรุงวิธีให้บริการได้ตรงจุด',
                'ติดตามผลหลังให้บริการและนำข้อเสนอแนะมาใช้ปรับปรุงงานอย่างต่อเนื่อง',
                'สร้างความสัมพันธ์ที่ดีและความไว้วางใจจากผู้เรียนและผู้รับบริการ',
            ],
        ];

        foreach ($indicatorsByLevel as $level => $indicators) {
            $levelId = $this->upsertAndGetId('competency_levels', [
                'competency_id' => $competencyId,
                'level' => $level,
            ], [
                'description' => "ระดับ {$level}",
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('comp_level_indicators')
                ->where('competency_level_id', $levelId)
                ->delete();

            foreach ($indicators as $indicator) {
                DB::table('comp_level_indicators')->insert([
                    'competency_level_id' => $levelId,
                    'description' => $indicator,
                    'weight' => 0.25,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function passedIndicatorKeys(int $competencyId): array
    {
        return DB::table('competency_levels')
            ->where('competency_id', $competencyId)
            ->where('level', '<=', 2)
            ->orderBy('level')
            ->get()
            ->flatMap(function (object $level) use ($competencyId) {
                return DB::table('comp_level_indicators')
                    ->where('competency_level_id', $level->id)
                    ->orderBy('id')
                    ->get()
                    ->values()
                    ->map(fn (object $indicator, int $index): string => $competencyId.':'.$level->id.':'.$index);
            })
            ->values()
            ->all();
    }

    private function activeRoundId($now): int
    {
        DB::table('assessment_rounds')->updateOrInsert(['year' => 2568], [
            'name' => 'รอบประเมิน 2568',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) DB::table('assessment_rounds')->where('year', 2568)->value('id');
    }

    private function ensureLearningMethods($now): void
    {
        foreach ([
            ['key' => 'experiential', 'label' => 'Experiential Learning', 'description' => 'เรียนรู้จากการลงมือทำและรับผิดชอบงานจริง', 'sort_order' => 1],
            ['key' => 'social', 'label' => 'Social Learning', 'description' => 'เรียนรู้ผ่าน coaching, mentoring และแลกเปลี่ยนกับผู้อื่น', 'sort_order' => 2],
            ['key' => 'formal', 'label' => 'Formal Learning', 'description' => 'เรียนรู้ผ่านหลักสูตร อบรม หรือ e-Learning', 'sort_order' => 3],
        ] as $method) {
            DB::table('learning_method_types')->updateOrInsert(['key' => $method['key']], [
                ...$method,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}

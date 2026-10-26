<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoFacultyAnalyticsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $roundId = $this->activeRoundId($now);
            $employeeRoleId = $this->employeeRoleId();
            $competencies = $this->competencies($now);
            $positionsByScope = $this->demoPositionsByScope();
            $levelsByScope = $this->demoLevelsByScope();
            $reviewerIds = $this->reviewerIds();
            $positionOffsets = ['academic' => 0, 'support' => 0];
            $levelOffsets = ['academic' => 0, 'support' => 0];

            foreach ($this->people() as $index => $person) {
                $scope = str_contains($person['workline'], 'วิชาการ') ? 'academic' : 'support';
                $availablePositions = $positionsByScope[$scope];
                $position = $availablePositions[$positionOffsets[$scope] % count($availablePositions)];
                $positionOffsets[$scope]++;
                $availableLevels = $levelsByScope[$scope];
                $level = $availableLevels[$levelOffsets[$scope] % count($availableLevels)];
                $levelOffsets[$scope]++;

                $userId = $this->upsertPerson($employeeRoleId, $index + 1, $person, $position, $level, $now);
                DB::table('idps')->where('user_id', $userId)->delete();
                $gapIds = $this->seedAssessments($roundId, $userId, $person, $competencies, $now);
                $this->seedIdp($roundId, $userId, $person, $gapIds, $now);
                $this->syncReviewerChain($userId, $reviewerIds, 'assessment', $now);
                $this->syncReviewerChain($userId, $reviewerIds, 'idp', $now);
            }

            $this->syncDemoReviewer($positionsByScope['support'][0], $levelsByScope['support'][0], $now);
        });
    }

    private function people(): array
    {
        return [
            // สายวิชาการ: ผลผสม มี Gap หลายอันดับ และมีผู้ที่ยังประเมินไม่ครบ
            ['name' => 'อ.กมลชนก วัฒนกิจ', 'workline' => 'สายวิชาการ', 'department' => 'ภาควิชาวิศวกรรมคอมพิวเตอร์', 'position' => 'อาจารย์', 'state' => 'passed', 'gaps' => []],
            ['name' => 'อ.ธีรภัทร แสงทอง', 'workline' => 'สายวิชาการ', 'department' => 'ภาควิชาวิศวกรรมคอมพิวเตอร์', 'position' => 'อาจารย์', 'state' => 'failed', 'idp_state' => 'not_started', 'gaps' => ['DEMO-CC-001', 'DEMO-FC-001', 'DEMO-FC-002']],
            ['name' => 'อ.ปาริชาติ อินทร์แก้ว', 'workline' => 'สายวิชาการ', 'department' => 'ภาควิชาวิศวกรรมคอมพิวเตอร์', 'position' => 'อาจารย์', 'state' => 'failed', 'idp_state' => 'draft', 'gaps' => ['DEMO-CC-001', 'DEMO-FC-001']],
            ['name' => 'อ.ณัฐพล จันทร์ดี', 'workline' => 'สายวิชาการ', 'department' => 'ภาควิชาวิศวกรรมคอมพิวเตอร์', 'position' => 'อาจารย์', 'state' => 'pending', 'gaps' => []],

            // ผ่านทั้งหมด
            ['name' => 'อ.ศศิธร พงษ์ไพศาล', 'workline' => 'สายวิชาการ', 'department' => 'ภาควิชาวิศวกรรมไฟฟ้า', 'position' => 'อาจารย์', 'state' => 'passed', 'gaps' => []],
            ['name' => 'อ.วรพล ชัยมงคล', 'workline' => 'สายวิชาการ', 'department' => 'ภาควิชาวิศวกรรมไฟฟ้า', 'position' => 'อาจารย์', 'state' => 'passed', 'gaps' => []],
            ['name' => 'อ.ธิดารัตน์ สุขสวัสดิ์', 'workline' => 'สายวิชาการ', 'department' => 'ภาควิชาวิศวกรรมไฟฟ้า', 'position' => 'อาจารย์', 'state' => 'passed', 'gaps' => []],

            // ผู้ประเมินครบทุกคนแต่มี Gap ทุกคน
            ['name' => 'อ.วุฒิชัย รุ่งเรือง', 'workline' => 'สายวิชาการ', 'department' => 'ภาควิชาวิศวกรรมโยธา', 'position' => 'อาจารย์', 'state' => 'failed', 'idp_state' => 'plan_review', 'gaps' => ['DEMO-MC-001', 'DEMO-CC-002', 'DEMO-FC-002']],
            ['name' => 'อ.ชลธิชา บุญช่วย', 'workline' => 'สายวิชาการ', 'department' => 'ภาควิชาวิศวกรรมโยธา', 'position' => 'อาจารย์', 'state' => 'failed', 'idp_state' => 'passed', 'gaps' => ['DEMO-MC-001', 'DEMO-CC-002']],

            // สายสนับสนุน: ฝ่าย > งาน > หน่วย หลายระดับและหลายรูปแบบผลลัพธ์
            ['name' => 'นางสาวพิมพ์ชนก ศรีสุข', 'workline' => 'สายสนับสนุน', 'department' => 'ฝ่ายบริหาร > งานทรัพยากรบุคคล > หน่วยพัฒนาบุคลากร', 'position' => 'นักทรัพยากรบุคคล', 'state' => 'passed', 'gaps' => []],
            ['name' => 'นายกิตติศักดิ์ พูนผล', 'workline' => 'สายสนับสนุน', 'department' => 'ฝ่ายบริหาร > งานทรัพยากรบุคคล > หน่วยพัฒนาบุคลากร', 'position' => 'นักทรัพยากรบุคคล', 'state' => 'failed', 'idp_state' => 'revision_required', 'gaps' => ['DEMO-CC-001', 'DEMO-CC-002', 'DEMO-FC-001']],
            ['name' => 'นางสาวอรอนงค์ วงศ์คำ', 'workline' => 'สายสนับสนุน', 'department' => 'ฝ่ายบริหาร > งานทรัพยากรบุคคล > หน่วยพัฒนาบุคลากร', 'position' => 'นักทรัพยากรบุคคล', 'state' => 'pending', 'gaps' => []],

            ['name' => 'นายพีรพล บุญมา', 'workline' => 'สายสนับสนุน', 'department' => 'ฝ่ายบริหาร > งานการเงินและบัญชี > หน่วยบัญชี', 'position' => 'นักวิชาการเงินและบัญชี', 'state' => 'passed', 'gaps' => []],
            ['name' => 'นางสาวกัญญารัตน์ ภูทอง', 'workline' => 'สายสนับสนุน', 'department' => 'ฝ่ายบริหาร > งานการเงินและบัญชี > หน่วยบัญชี', 'position' => 'นักวิชาการเงินและบัญชี', 'state' => 'passed', 'gaps' => []],
            ['name' => 'นายธีรเดช แก้วกาญจน์', 'workline' => 'สายสนับสนุน', 'department' => 'ฝ่ายบริหาร > งานการเงินและบัญชี > หน่วยบัญชี', 'position' => 'นักวิชาการเงินและบัญชี', 'state' => 'pending', 'gaps' => []],

            ['name' => 'นางสายฝน รักษ์งาน', 'workline' => 'สายสนับสนุน', 'department' => 'ฝ่ายบริหาร > งานอำนวยการ > หน่วยสารบรรณ', 'position' => 'เจ้าหน้าที่บริหารงานทั่วไป', 'state' => 'passed', 'gaps' => []],
            ['name' => 'นายชาญชัย ใจมั่น', 'workline' => 'สายสนับสนุน', 'department' => 'ฝ่ายบริหาร > งานอำนวยการ > หน่วยสารบรรณ', 'position' => 'เจ้าหน้าที่บริหารงานทั่วไป', 'state' => 'failed', 'idp_state' => 'developing', 'gaps' => ['DEMO-CC-001']],

            ['name' => 'นางสาวจุฑามาศ คำดี', 'workline' => 'สายสนับสนุน', 'department' => 'ฝ่ายยุทธศาสตร์ > งานแผนและประกันคุณภาพ > หน่วยวิเคราะห์ข้อมูล', 'position' => 'นักวิเคราะห์นโยบายและแผน', 'state' => 'failed', 'idp_state' => 'result_review', 'gaps' => ['DEMO-FC-001', 'DEMO-FC-002', 'DEMO-MC-001']],
            ['name' => 'นายศุภกร มั่นคง', 'workline' => 'สายสนับสนุน', 'department' => 'ฝ่ายยุทธศาสตร์ > งานแผนและประกันคุณภาพ > หน่วยวิเคราะห์ข้อมูล', 'position' => 'นักวิเคราะห์นโยบายและแผน', 'state' => 'failed', 'idp_state' => 'failed', 'gaps' => ['DEMO-FC-001', 'DEMO-FC-002']],
            ['name' => 'นางสาวลลิตา แสงงาม', 'workline' => 'สายสนับสนุน', 'department' => 'ฝ่ายยุทธศาสตร์ > งานแผนและประกันคุณภาพ > หน่วยวิเคราะห์ข้อมูล', 'position' => 'นักวิเคราะห์นโยบายและแผน', 'state' => 'pending', 'gaps' => []],

            ['name' => 'นายเจษฎา พัฒนกิจ', 'workline' => 'สายสนับสนุน', 'department' => 'ฝ่ายบริการ > งานเทคโนโลยีสารสนเทศ > หน่วยระบบสารสนเทศ', 'position' => 'นักวิชาการคอมพิวเตอร์', 'state' => 'passed', 'gaps' => []],
            ['name' => 'นางสาวสุรีย์พร มีสุข', 'workline' => 'สายสนับสนุน', 'department' => 'ฝ่ายบริการ > งานเทคโนโลยีสารสนเทศ > หน่วยระบบสารสนเทศ', 'position' => 'นักวิชาการคอมพิวเตอร์', 'state' => 'failed', 'idp_state' => 'overdue', 'gaps' => ['DEMO-CC-002', 'DEMO-MC-001']],
            ['name' => 'นายปกรณ์ สมบูรณ์', 'workline' => 'สายสนับสนุน', 'department' => 'ฝ่ายบริการ > งานเทคโนโลยีสารสนเทศ > หน่วยระบบสารสนเทศ', 'position' => 'นักวิชาการคอมพิวเตอร์', 'state' => 'pending', 'gaps' => []],

            // เติมสถานะให้ dashboard ของทั้งสองสายมีครบทุกกลุ่มที่แสดงผล
            ['name' => 'อ.กฤตภาส วงศ์ดี', 'workline' => 'สายวิชาการ', 'department' => 'ภาควิชาวิศวกรรมอุตสาหการ', 'position' => 'อาจารย์', 'state' => 'failed', 'idp_state' => 'developing', 'gaps' => ['DEMO-FC-002']],
            ['name' => 'นางสาวมณีรัตน์ ศรีคำ', 'workline' => 'สายสนับสนุน', 'department' => 'ฝ่ายบริหาร > งานการเงินและบัญชี > หน่วยการเงิน', 'position' => 'นักวิชาการเงินและบัญชี', 'state' => 'failed', 'idp_state' => 'not_started', 'gaps' => ['DEMO-CC-001']],
            ['name' => 'นายภาคภูมิ ธรรมดี', 'workline' => 'สายสนับสนุน', 'department' => 'ฝ่ายบริการ > งานบริการการศึกษา > หน่วยทะเบียน', 'position' => 'นักวิชาการศึกษา', 'state' => 'failed', 'idp_state' => 'passed', 'gaps' => ['DEMO-CC-002']],
        ];
    }

    private function competencies($now): array
    {
        $definitions = [
            ['code' => 'DEMO-CC-001', 'type' => 'CC', 'name' => 'การสื่อสารอย่างมีประสิทธิภาพ'],
            ['code' => 'DEMO-CC-002', 'type' => 'CC', 'name' => 'การทำงานเป็นทีม'],
            ['code' => 'DEMO-MC-001', 'type' => 'MC', 'name' => 'การคิดเชิงกลยุทธ์'],
            ['code' => 'DEMO-FC-001', 'type' => 'FC1', 'name' => 'การวิเคราะห์ข้อมูล'],
            ['code' => 'DEMO-FC-002', 'type' => 'FC1', 'name' => 'การใช้เทคโนโลยีดิจิทัลและ AI'],
        ];

        $result = [];
        foreach ($definitions as $definition) {
            DB::table('competency_types')->updateOrInsert(['code' => $definition['type']], [
                'full_name' => match ($definition['type']) {
                    'CC' => 'Core Competency',
                    'MC' => 'Managerial Competency',
                    default => 'Functional Competency',
                },
                'description' => 'ข้อมูลสำหรับสาธิตภาพรวมคณะ',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $typeId = DB::table('competency_types')->where('code', $definition['type'])->value('id');

            DB::table('competencies')->updateOrInsert(['code' => $definition['code']], [
                'competency_type_id' => $typeId,
                'name' => $definition['name'],
                'detail' => 'ข้อมูลจำลองสำหรับทดสอบหน้าภาพรวมคณะ',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $result[$definition['code']] = (int) DB::table('competencies')
                ->where('code', $definition['code'])
                ->value('id');
        }

        return $result;
    }

    private function upsertPerson(int $roleId, int $sequence, array $person, array $position, array $level, $now): int
    {
        $email = sprintf('faculty.analytics.demo.%02d@test.com', $sequence);

        DB::table('users')->updateOrInsert(['email' => $email], [
            'name' => $person['name'],
            'password' => Hash::make('password'),
            'role_id' => $roleId,
            'workline' => $position['workline'],
            'department' => $position['department'],
            'position' => $position['name'],
            'position_id' => $position['id'],
            'position_title_id' => $position['positionTitleId'],
            'level' => $level['name'],
            'level_id' => $level['id'],
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) DB::table('users')->where('email', $email)->value('id');
    }

    /** @return array{academic: array<int, array<string, mixed>>, support: array<int, array<string, mixed>>} */
    private function demoPositionsByScope(): array
    {
        $positions = DB::table('positions')
            ->join('job_families', 'positions.job_family_id', '=', 'job_families.id')
            ->join('worklines', 'job_families.workline_id', '=', 'worklines.id')
            ->leftJoin('support_units', 'positions.support_unit_id', '=', 'support_units.id')
            ->leftJoin('support_works', 'support_units.support_work_id', '=', 'support_works.id')
            ->leftJoin('support_departments', 'support_works.support_department_id', '=', 'support_departments.id')
            ->whereNotNull('positions.position_title_id')
            ->where(function ($query): void {
                $query->where(function ($academic): void {
                    $academic->where('worklines.name', 'like', '%วิชาการ%')
                        ->whereNull('positions.support_unit_id');
                })->orWhere(function ($support): void {
                    $support->where('worklines.name', 'like', '%สนับสนุน%')
                        ->whereNotNull('positions.support_unit_id');
                });
            })
            ->orderBy('positions.id')
            ->get([
                'positions.id',
                'positions.position_title_id',
                'positions.name',
                'positions.support_unit_id',
                'worklines.name as workline',
                'job_families.name as job_family',
                'support_departments.name as support_department',
                'support_works.name as support_work',
                'support_units.name as support_unit',
            ])
            ->map(function (object $position): array {
                $scope = $position->support_unit_id ? 'support' : 'academic';
                $department = $scope === 'support'
                    ? collect([$position->support_department, $position->support_work, $position->support_unit])
                        ->filter()
                        ->implode(' > ')
                    : (string) $position->job_family;

                return [
                    'id' => (int) $position->id,
                    'positionTitleId' => (int) $position->position_title_id,
                    'name' => (string) $position->name,
                    'scope' => $scope,
                    'workline' => (string) $position->workline,
                    'department' => $department,
                ];
            })
            ->groupBy('scope');

        $academic = $positions->get('academic', collect())->values()->all();
        $support = $positions->get('support', collect())->values()->all();

        if ($academic === [] || $support === []) {
            throw new \RuntimeException('ต้องมีตำแหน่งในโครงสร้างจริงทั้งสายวิชาการและสายสนับสนุนก่อนสร้างข้อมูล Dashboard จำลอง');
        }

        return compact('academic', 'support');
    }

    /** @return array{academic: array<int, array{id: int, name: string}>, support: array<int, array{id: int, name: string}>} */
    private function demoLevelsByScope(): array
    {
        $levels = DB::table('levels')
            ->join('worklines', 'levels.workline_id', '=', 'worklines.id')
            ->whereNull('levels.job_family_id')
            ->orderBy('levels.id')
            ->get([
                'levels.id',
                'levels.name',
                'worklines.name as workline',
            ])
            ->map(fn (object $level): array => [
                'id' => (int) $level->id,
                'name' => (string) $level->name,
                'scope' => str_contains((string) $level->workline, 'วิชาการ') ? 'academic' : 'support',
            ])
            ->groupBy('scope');

        $academic = $levels->get('academic', collect())->values()->all();
        $support = $levels->get('support', collect())->values()->all();

        if ($academic === [] || $support === []) {
            throw new \RuntimeException('ต้องมีระดับตำแหน่งในโครงสร้างจริงทั้งสายวิชาการและสายสนับสนุนก่อนสร้างข้อมูล Dashboard จำลอง');
        }

        return compact('academic', 'support');
    }

    private function syncDemoReviewer(array $position, array $level, $now): void
    {
        DB::table('users')
            ->where('email', 'faculty.analytics.reviewer@test.com')
            ->update([
                'workline' => $position['workline'],
                'department' => $position['department'],
                'position' => $position['name'],
                'position_id' => $position['id'],
                'position_title_id' => $position['positionTitleId'],
                'level' => $level['name'],
                'level_id' => $level['id'],
                'updated_at' => $now,
            ]);
    }

    private function seedAssessments(int $roundId, int $userId, array $person, array $competencies, $now): array
    {
        $gapIds = [];

        DB::table('assessments')
            ->where('assessment_round_id', $roundId)
            ->where('user_id', $userId)
            ->whereNotIn('competency_id', array_values($competencies))
            ->delete();

        foreach ($competencies as $code => $competencyId) {
            $isPending = $person['state'] === 'pending';
            DB::table('assessments')->updateOrInsert([
                'assessment_round_id' => $roundId,
                'user_id' => $userId,
                'competency_id' => $competencyId,
            ], [
                'status' => $isPending ? ($code === 'DEMO-CC-001' ? 'self_submitted' : 'draft') : 'approved',
                'score' => in_array($code, $person['gaps'], true) ? 2 : 3,
                'note' => 'ข้อมูลจำลองสำหรับหน้าภาพรวมคณะ',
                'self_submitted_at' => $isPending ? $now : $now,
                'dean_approved_at' => $isPending ? null : $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $assessmentId = (int) DB::table('assessments')
                ->where('assessment_round_id', $roundId)
                ->where('user_id', $userId)
                ->where('competency_id', $competencyId)
                ->value('id');

            DB::table('competency_gaps')
                ->where('assessment_id', $assessmentId)
                ->where('competency_id', $competencyId)
                ->delete();

            if (! $isPending && in_array($code, $person['gaps'], true)) {
                $gapIds[$code] = (int) DB::table('competency_gaps')->insertGetId([
                    'assessment_id' => $assessmentId,
                    'competency_id' => $competencyId,
                    'expected_level' => 3,
                    'actual_level' => $code === 'DEMO-FC-002' ? 1.5 : 2,
                    'gap' => $code === 'DEMO-FC-002' ? -1.5 : -1,
                    'requires_idp' => true,
                    'status' => 'approved',
                    'decided_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        return $gapIds;
    }

    private function seedIdp(int $roundId, int $userId, array $person, array $gapIds, $now): void
    {
        $state = $person['idp_state'] ?? null;
        if (! $state || $state === 'not_started' || $gapIds === []) {
            return;
        }

        $roundYear = (int) DB::table('assessment_rounds')->where('id', $roundId)->value('year');
        $assessmentId = (int) DB::table('competency_gaps')->where('id', reset($gapIds))->value('assessment_id');
        $itemStatus = match ($state) {
            'draft' => 'draft',
            'revision_required' => 'revision_required',
            'plan_review' => 'review_step_1',
            default => 'approved',
        };
        $parentStatus = match ($state) {
            'draft' => 'draft',
            'revision_required' => 'revision_required',
            'plan_review' => 'partially_submitted',
            default => 'approved',
        };

        $idpId = (int) DB::table('idps')->insertGetId([
            'assessment_id' => $assessmentId,
            'assessment_round_id' => $roundId,
            'user_id' => $userId,
            'year' => $roundYear,
            'status' => $parentStatus,
            'submitted_at' => $state === 'plan_review' ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($gapIds as $code => $gapId) {
            $itemId = (int) DB::table('idp_items')->insertGetId([
                'idp_id' => $idpId,
                'competency_gap_id' => $gapId,
                'goal' => "ยกระดับสมรรถนะ {$code} ให้ผ่านระดับที่คาดหวัง",
                'success_criteria' => 'สามารถนำความรู้ไปใช้กับงานจริงและมีหลักฐานผลลัพธ์ที่ตรวจสอบได้',
                'target_level' => 3,
                'status' => $itemStatus,
                'submission_version' => in_array($state, ['plan_review', 'developing', 'overdue', 'result_review', 'passed', 'failed'], true) ? 1 : 0,
                'current_review_step' => $state === 'plan_review' ? 1 : null,
                'submitted_at' => $state === 'plan_review' ? $now : null,
                'approved_at' => in_array($state, ['developing', 'overdue', 'result_review', 'passed', 'failed'], true) ? $now->copy()->subMonths(2) : null,
                'rejected_at' => $state === 'revision_required' ? $now->copy()->subDays(2) : null,
                'reject_comment' => $state === 'revision_required' ? 'กรุณาระบุตัวชี้วัดผลสำเร็จให้ชัดเจนก่อนส่งใหม่' : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (! in_array($state, ['developing', 'overdue', 'result_review', 'passed', 'failed'], true)) {
                continue;
            }

            DB::table('idp_activities')->insert([
                'idp_item_id' => $itemId,
                'activity_name' => "กิจกรรมพัฒนา {$code}",
                'weight_percent' => 100,
                'start_date' => $now->copy()->subMonth()->toDateString(),
                'end_date' => $state === 'overdue'
                    ? $now->copy()->subWeek()->toDateString()
                    : $now->copy()->addMonths(2)->toDateString(),
                'description' => 'ข้อมูลจำลองสำหรับติดตามความคืบหน้า IDP ภาพรวมคณะ',
                'status' => in_array($state, ['passed', 'failed'], true) ? 'completed' : 'in_progress',
                'result' => match ($state) {
                    'passed' => 'completed',
                    'failed' => 'incomplete',
                    default => 'pending',
                },
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (! in_array($state, ['result_review', 'passed', 'failed'], true)) {
                continue;
            }

            DB::table('idp_item_completion_submissions')->insert([
                'public_id' => (string) Str::uuid(),
                'idp_item_id' => $itemId,
                'submission_version' => 1,
                'status' => $state === 'result_review' ? 'review_step_1' : 'approved',
                'result' => match ($state) {
                    'passed' => 'passed',
                    'failed' => 'failed',
                    default => null,
                },
                'current_review_step' => $state === 'result_review' ? 1 : null,
                'submitted_at' => $now->copy()->subDays(3),
                'approved_at' => in_array($state, ['passed', 'failed'], true) ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function activeRoundId($now): int
    {
        $roundId = DB::table('assessment_rounds')
            ->where('is_active', true)
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->value('id');

        if ($roundId) {
            return (int) $roundId;
        }

        return (int) DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบจำลองภาพรวมคณะ',
            'year' => (int) now()->format('Y') + 543,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function employeeRoleId(): int
    {
        return (int) DB::table('roles')
            ->where('key', 'employee')
            ->value('id');
    }

    /** @return array<int, int> */
    private function reviewerIds(): array
    {
        $reviewersByEmail = DB::table('users')
            ->whereIn('email', ['13@gmail.com', '12@gmail.com', '11@gmail.com'])
            ->where('is_active', true)
            ->pluck('id', 'email');

        $reviewerIds = collect(['13@gmail.com', '12@gmail.com', '11@gmail.com'])
            ->map(fn (string $email): int => (int) ($reviewersByEmail[$email] ?? 0))
            ->filter()
            ->values()
            ->all();

        if ($reviewerIds === []) {
            $fallbackId = (int) DB::table('users')
                ->where('email', 'faculty.analytics.reviewer@test.com')
                ->where('is_active', true)
                ->value('id');

            if ($fallbackId > 0) {
                $reviewerIds = [$fallbackId];
            }
        }

        if ($reviewerIds === []) {
            throw new \RuntimeException('ต้องมีผู้ประเมินที่เปิดใช้งานอย่างน้อย 1 คนก่อนสร้างข้อมูล Dashboard จำลอง');
        }

        return $reviewerIds;
    }

    /** @param array<int, int> $reviewerIds */
    private function syncReviewerChain(int $userId, array $reviewerIds, string $chainType, $now): void
    {
        DB::table('user_reviewer_steps')
            ->where('user_id', $userId)
            ->where('chain_type', $chainType)
            ->delete();

        DB::table('user_reviewer_steps')->insert(collect($reviewerIds)
            ->values()
            ->map(fn (int $reviewerId, int $index): array => [
                'user_id' => $userId,
                'chain_type' => $chainType,
                'step_order' => $index + 1,
                'reviewer_id' => $reviewerId,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all());
    }
}

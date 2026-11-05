<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\BuildsFacultyAssessment;
use Tests\TestCase;

class CompetencyFormatTest extends TestCase
{
    use BuildsFacultyAssessment;
    use RefreshDatabase;

    private int $typeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->typeId = DB::table('competency_types')->insertGetId([
            'code' => 'CC', 'full_name' => 'Core Competency', 'description' => 'สมรรถนะหลัก',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_admin_creates_a_faculty_format_competency_with_three_behaviours_at_levels_one_three_five(): void
    {
        // The faculty format has no code; the server keeps an internal one.
        $payload = [...$this->payload('', 'faculty', $this->facultyLevels()), 'code' => null, 'name' => 'การทำงานเป็นทีม'];
        $this->actingAs($this->admin())
            ->post(route('admin.competencies.store'), $payload)
            ->assertSessionHasNoErrors();

        $competencyId = DB::table('competencies')->where('name', 'การทำงานเป็นทีม')->value('id');
        $this->assertDatabaseHas('competencies', ['id' => $competencyId, 'code' => 'ENG-CC-001', 'assessment_method' => 'faculty']);
        $this->assertSame([1, 3, 5], DB::table('competency_levels')->where('competency_id', $competencyId)->orderBy('level')->pluck('level')->map(fn ($l) => (int) $l)->all());
        $this->assertSame(9, DB::table('comp_level_indicators')
            ->join('competency_levels', 'competency_levels.id', '=', 'comp_level_indicators.competency_level_id')
            ->where('competency_levels.competency_id', $competencyId)->count());
    }

    public function test_faculty_format_rejects_other_levels_and_wrong_behaviour_counts(): void
    {
        $withLevelTwo = [...$this->facultyLevels(), ['level' => 2, 'indicators' => $this->behaviours(2, 3)]];
        $twoBehaviours = $this->facultyLevels();
        $twoBehaviours[2]['indicators'] = $this->behaviours(5, 2);

        $this->actingAs($this->admin())
            ->post(route('admin.competencies.store'), $this->payload('ENG-CC-02', 'faculty', $withLevelTwo))
            ->assertSessionHasErrors(['levels' => 'รูปแบบคณะวิศวกรรมศาสตร์ต้องมีพฤติกรรมเฉพาะระดับ 1, 3 และ 5']);
        $this->actingAs($this->admin())
            ->post(route('admin.competencies.store'), $this->payload('ENG-CC-03', 'faculty', $twoBehaviours))
            ->assertSessionHasErrors(['levels' => 'รูปแบบคณะวิศวกรรมศาสตร์ต้องมีพฤติกรรมระดับละ 3 ข้อ (ระดับ 5 มี 2 ข้อ)']);

        $this->assertDatabaseCount('competencies', 0);
    }

    public function test_competency_without_a_format_stays_university_format(): void
    {
        $payload = $this->payload('CC-001', 'standard', [['level' => 1, 'indicators' => $this->behaviours(1, 2)]]);
        unset($payload['assessment_method']);

        $this->actingAs($this->admin())
            ->post(route('admin.competencies.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('competencies', ['code' => 'CC-001', 'assessment_method' => 'standard']);
    }

    public function test_hr_can_only_bind_competencies_of_the_rounds_format(): void
    {
        $roundId = $this->round('faculty');
        $positionId = $this->position();
        $university = $this->competency('CC-001', 'standard');
        $faculty = $this->competency('ENG-CC-01', 'faculty');

        $this->actingAs($this->hr())
            ->post(route('hr.position-competencies.store'), [
                'position_id' => $positionId, 'assessment_round_id' => $roundId, 'competency_ids' => [$university],
            ])
            ->assertSessionHasErrors(['competency_id' => 'รอบนี้ใช้สมรรถนะรูปแบบคณะวิศวกรรมศาสตร์เท่านั้น ผูกไม่ได้: CC-001']);
        $this->actingAs($this->hr())
            ->post(route('hr.position-competencies.store'), [
                'position_id' => $positionId, 'assessment_round_id' => $roundId, 'competency_ids' => [$faculty],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([$faculty], DB::table('position_competencies')->pluck('competency_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_round_cannot_open_while_a_competency_of_another_format_is_bound(): void
    {
        $roundId = $this->round('faculty');
        $university = $this->competency('CC-001', 'standard');
        DB::table('position_competencies')->insert([
            'assessment_round_id' => $roundId, 'position_id' => $this->position(), 'competency_id' => $university,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->hr())
            ->patch(route('hr.assessment-rounds.activate', $roundId))
            ->assertSessionHasErrors('round');

        $this->assertDatabaseHas('assessment_rounds', ['id' => $roundId, 'is_active' => false]);
    }

    public function test_round_method_cannot_switch_while_competencies_of_the_old_format_are_bound(): void
    {
        $roundId = $this->round('standard');
        DB::table('position_competencies')->insert([
            'assessment_round_id' => $roundId, 'position_id' => $this->position(), 'competency_id' => $this->competency('CC-001', 'standard'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->hr())
            ->put(route('hr.assessment-rounds.update', $roundId), [
                'name' => 'รอบวิธีคณะ',
                'is_active' => false,
                'assessment_method' => 'faculty',
            ])
            ->assertSessionHasErrors('assessment_method');

        $this->assertDatabaseHas('assessment_rounds', ['id' => $roundId, 'assessment_method' => 'standard']);
    }

    public function test_copying_into_a_faculty_round_only_carries_faculty_competencies(): void
    {
        $sourceRoundId = $this->round('faculty', 'รอบต้นทาง');
        $targetRoundId = $this->round('faculty', 'รอบปลายทาง');
        $positionId = $this->position();
        $university = $this->competency('CC-001', 'standard');
        $faculty = $this->competency('ENG-CC-01', 'faculty');
        foreach ([$university, $faculty] as $competencyId) {
            DB::table('position_competencies')->insert([
                'assessment_round_id' => $sourceRoundId, 'position_id' => $positionId, 'competency_id' => $competencyId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($this->hr())
            ->post(route('hr.position-competencies.copy-round'), ['source_round_id' => $sourceRoundId, 'target_round_id' => $targetRoundId])
            ->assertSessionHasNoErrors();

        $this->assertSame([$faculty], DB::table('position_competencies')->where('assessment_round_id', $targetRoundId)
            ->pluck('competency_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_admin_dashboard_tells_each_competency_its_format(): void
    {
        $this->competency('CC-001', 'standard');
        $this->competency('ENG-CC-01', 'faculty');

        $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('competencies.0.cd', 'CC-001')
                ->where('competencies.0.method', 'standard')
                ->where('competencies.1.cd', 'ENG-CC-01')
                ->where('competencies.1.method', 'faculty'));
    }

    public function test_employee_sees_faculty_competency_by_name_without_a_code(): void
    {
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        DB::table('competency_types')->delete(); // the faculty builder creates its own CC type
        [$competencyId] = $this->makeAssessable($employee, $this->activeFacultyRound());
        DB::table('competencies')->where('id', $competencyId)->update(['assessment_method' => 'faculty']);

        $this->actingAs($employee)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentUserCompetencies.0.id', $competencyId)
                ->where('currentUserCompetencies.0.cd', ''));
    }

    private function payload(string $code, string $method, array $levels): array
    {
        return [
            'competency_type_id' => $this->typeId,
            'code' => $code,
            'name' => "สมรรถนะ {$code}",
            'detail' => 'คำอธิบาย',
            'assessment_method' => $method,
            'levels' => $levels,
        ];
    }

    private function facultyLevels(): array
    {
        return array_map(fn (int $level): array => ['level' => $level, 'indicators' => $this->behaviours($level, 3)], [1, 3, 5]);
    }

    private function behaviours(int $level, int $count): array
    {
        return array_map(fn (int $index): array => ['description' => "พฤติกรรมระดับ {$level} ข้อ {$index}"], range(1, $count));
    }

    private function competency(string $code, string $method): int
    {
        return DB::table('competencies')->insertGetId([
            'competency_type_id' => $this->typeId, 'code' => $code, 'name' => "สมรรถนะ {$code}",
            'assessment_method' => $method, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function round(string $method, string $name = 'รอบวิธีคณะ'): int
    {
        return DB::table('assessment_rounds')->insertGetId([
            'name' => $name,
            'year' => 2569,
            'assessment_method' => $method,
            'self_assess_start' => now()->subDay()->toDateString(),
            'self_assess_end' => now()->addMonth()->toDateString(),
            'supervisor_assess_end' => now()->addMonths(2)->toDateString(),
            'is_active' => false,
            'snapshot_required' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function position(): int
    {
        $worklineId = DB::table('worklines')->insertGetId(['name' => 'สายวิชาการ', 'created_at' => now(), 'updated_at' => now()]);
        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId, 'name' => 'ภาควิชาทดสอบ', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('positions')->insertGetId([
            'job_family_id' => $jobFamilyId, 'name' => 'อาจารย์', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->roleId('admin')]);
    }

    private function hr(): User
    {
        return User::factory()->create(['role_id' => $this->roleId('hr')]);
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }
}

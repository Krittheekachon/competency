<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HrPositionCompetencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_can_attach_and_detach_competencies_to_a_position(): void
    {
        $hrUser = User::factory()->create(['role_id' => 1, 'role_key' => 'hr']);
        [$positionId, $competencyId] = $this->createPositionAndCompetency();

        $this->actingAs($hrUser)
            ->post(route('hr.position-competencies.store'), [
                'position_id' => $positionId,
                'competency_id' => $competencyId,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('position_competencies', [
            'position_id' => $positionId,
            'competency_id' => $competencyId,
        ]);

        $this->actingAs($hrUser)
            ->post(route('hr.position-competencies.store'), [
                'position_id' => $positionId,
                'competency_id' => $competencyId,
            ])
            ->assertRedirect();

        $this->assertSame(1, DB::table('position_competencies')->count());

        $this->actingAs($hrUser)
            ->delete(route('hr.position-competencies.destroy'), [
                'position_id' => $positionId,
                'competency_id' => $competencyId,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('position_competencies', [
            'position_id' => $positionId,
            'competency_id' => $competencyId,
        ]);
    }

    public function test_hr_dashboard_loads_position_competency_mapping(): void
    {
        $hrUser = User::factory()->create(['role_id' => 1, 'role_key' => 'hr']);
        [$positionId, $competencyId] = $this->createPositionAndCompetency();

        DB::table('position_competencies')->insert([
            'position_id' => $positionId,
            'competency_id' => $competencyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($hrUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('HR/Dashboard')
                ->where("positionCompetencies.{$positionId}.0", $competencyId)
            );
    }

    private function createPositionAndCompetency(): array
    {
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายทดสอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'กลุ่มงานทดสอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $positionId = DB::table('positions')->insertGetId([
            'job_family_id' => $jobFamilyId,
            'name' => 'ตำแหน่งทดสอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $typeId = DB::table('competency_types')->insertGetId([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'Core competency',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $competencyId = DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId,
            'code' => 'CC-T01',
            'name' => 'สมรรถนะทดสอบ',
            'detail' => 'รายละเอียดทดสอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$positionId, $competencyId];
    }
}

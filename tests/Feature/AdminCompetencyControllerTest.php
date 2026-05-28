<?php

namespace Tests\Feature;

use App\Models\Competency;
use App\Models\CompetencyType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCompetencyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_competency_dictionary_item(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $type = CompetencyType::create([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'สมรรถนะหลัก',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.competencies.store'), [
                'competency_type_id' => $type->id,
                'code' => 'CC-001',
                'name' => 'การบริการที่ดี',
                'detail' => 'ให้บริการด้วยความเข้าใจ',
                'levels' => [
                    [
                        'level' => 1,
                        'description' => '',
                        'indicators' => [
                            ['description' => 'รับฟังผู้รับบริการ', 'weight' => 0.25],
                            ['description' => 'ตอบกลับอย่างสุภาพ', 'weight' => 0.25],
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $competency = Competency::query()->where('code', 'CC-001')->firstOrFail();

        $this->assertDatabaseHas('competencies', [
            'id' => $competency->id,
            'competency_type_id' => $type->id,
            'name' => 'การบริการที่ดี',
        ]);
        $this->assertDatabaseHas('competency_levels', [
            'competency_id' => $competency->id,
            'level' => 1,
        ]);
        $this->assertDatabaseHas('comp_level_indicators', [
            'description' => 'รับฟังผู้รับบริการ',
            'weight' => 0.25,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.competencies.update', $competency), [
                'competency_type_id' => $type->id,
                'code' => 'CC-002',
                'name' => 'การมุ่งผลสัมฤทธิ์',
                'detail' => 'ทำงานให้เกิดผลลัพธ์',
                'levels' => [
                    [
                        'level' => 2,
                        'description' => '',
                        'indicators' => [
                            ['description' => 'ติดตามผลการทำงาน', 'weight' => 0.50],
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('competencies', [
            'id' => $competency->id,
            'code' => 'CC-002',
            'name' => 'การมุ่งผลสัมฤทธิ์',
        ]);
        $this->assertDatabaseMissing('comp_level_indicators', [
            'description' => 'รับฟังผู้รับบริการ',
        ]);
        $this->assertDatabaseHas('comp_level_indicators', [
            'description' => 'ติดตามผลการทำงาน',
            'weight' => 0.50,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.competencies.destroy', $competency))
            ->assertRedirect();

        $this->assertDatabaseMissing('competencies', ['id' => $competency->id]);
    }
}

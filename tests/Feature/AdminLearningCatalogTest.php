<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminLearningCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_learning_catalog(): void
    {
        $adminUser = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $methodId = $this->createLearningMethod('workshop', 'Workshop');
        $competencyId = $this->createCompetency('CC-001', 'Customer First');

        $this->actingAs($adminUser)
            ->post(route('admin.learning-catalogs.store'), [
                'code' => 'KKU-LC-001',
                'name' => 'หลักสูตรทดสอบ',
                'method_key' => 'workshop',
                'delivery_type' => 'e_learning',
                'source_type' => 'internal',
                'provider' => 'หน่วยงานทดสอบ',
                'cost' => '1200',
                'hours' => '6',
                'expected_levels' => [2, 3],
                'competency_ids' => [$competencyId],
                'description' => 'รายละเอียดหลักสูตร',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('learning_catalogs', [
            'code' => 'KKU-LC-001',
            'name' => 'หลักสูตรทดสอบ',
            'method_type_id' => $methodId,
            'delivery_type' => 'e_learning',
            'source_type' => 'internal',
            'provider' => 'หน่วยงานทดสอบ',
            'cost' => 1200,
            'hours' => 6,
            'description' => 'รายละเอียดหลักสูตร',
            'is_active' => true,
        ]);

        $catalogId = DB::table('learning_catalogs')->value('id');
        $this->assertDatabaseHas('learning_catalog_competency', [
            'learning_catalog_id' => $catalogId,
            'competency_id' => $competencyId,
        ]);
        $this->assertSame([2, 3], json_decode(DB::table('learning_catalogs')->where('id', $catalogId)->value('expected_levels'), true));

        $this->actingAs($adminUser)
            ->put(route('admin.learning-catalogs.update', $catalogId), [
                'code' => 'KKU-LC-002',
                'name' => 'หลักสูตรแก้ไข',
                'method_key' => 'workshop',
                'delivery_type' => 'in_class',
                'source_type' => 'external',
                'provider' => 'หน่วยงานใหม่',
                'cost' => null,
                'hours' => '3.5',
                'expected_levels' => [4],
                'competency_ids' => [],
                'description' => 'รายละเอียดใหม่',
                'is_active' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('learning_catalogs', [
            'id' => $catalogId,
            'code' => 'KKU-LC-002',
            'name' => 'หลักสูตรแก้ไข',
            'delivery_type' => 'in_class',
            'source_type' => 'external',
            'provider' => 'หน่วยงานใหม่',
            'cost' => null,
            'hours' => 3.5,
            'description' => 'รายละเอียดใหม่',
            'is_active' => false,
        ]);
        $this->assertDatabaseMissing('learning_catalog_competency', [
            'learning_catalog_id' => $catalogId,
            'competency_id' => $competencyId,
        ]);

        $this->actingAs($adminUser)
            ->delete(route('admin.learning-catalogs.destroy', $catalogId))
            ->assertRedirect();

        $this->assertDatabaseMissing('learning_catalogs', ['id' => $catalogId]);
    }

    public function test_admin_dashboard_loads_learning_catalog_and_idp_tool_items(): void
    {
        $adminUser = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $methodId = $this->createLearningMethod('online', 'Online Learning');
        $competencyId = $this->createCompetency('CC-002', 'Expertise');
        DB::table('idp_learning_methods')->insert([
            'focus_type' => 'experiential',
            'title' => 'การมอบหมายงานโครงการ',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $catalogId = DB::table('learning_catalogs')->insertGetId([
            'code' => 'ONLINE-001',
            'name' => 'หลักสูตรออนไลน์',
            'method_type_id' => $methodId,
            'delivery_type' => 'e_learning',
            'source_type' => 'external',
            'provider' => 'ผู้จัด',
            'cost' => 0,
            'hours' => 2,
            'expected_levels' => json_encode([1, 2]),
            'description' => 'รายละเอียด',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('learning_catalog_competency')->insert([
            'learning_catalog_id' => $catalogId,
            'competency_id' => $competencyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($adminUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('hrCatalogItems.0.code', 'ONLINE-001')
                ->where('hrCatalogItems.0.name', 'หลักสูตรออนไลน์')
                ->where('hrCatalogItems.0.methodKey', 'online')
                ->where('hrCatalogItems.0.methodLabel', 'Online Learning')
                ->where('hrCatalogItems.0.deliveryType', 'e_learning')
                ->where('hrCatalogItems.0.sourceType', 'external')
                ->where('hrCatalogItems.0.hours', 2)
                ->where('hrCatalogItems.0.expectedLevels', [1, 2])
                ->where('hrCatalogItems.0.competencyIds', [$competencyId])
                ->where('hrCatalogItems.0.isActive', true)
                ->where('idpLearningMethods.0.focusType', 'experiential')
                ->where('idpLearningMethods.0.title', 'การมอบหมายงานโครงการ / งานพิเศษ (Project Assignment)')
            );
    }

    public function test_admin_gets_validation_errors_when_learning_catalog_code_or_name_is_duplicated(): void
    {
        $adminUser = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $this->createLearningMethod('formal', 'Formal Learning');

        DB::table('learning_catalogs')->insert([
            'code' => 'DUP-001',
            'name' => 'หลักสูตรซ้ำ',
            'delivery_type' => 'e_learning',
            'source_type' => 'internal',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($adminUser)
            ->from('/dashboard')
            ->post(route('admin.learning-catalogs.store'), [
                'code' => 'DUP-001',
                'name' => 'หลักสูตรซ้ำ',
                'method_key' => 'formal',
                'delivery_type' => 'e_learning',
                'source_type' => 'internal',
                'provider' => null,
                'cost' => null,
                'hours' => null,
                'expected_levels' => [],
                'competency_ids' => [],
                'description' => null,
                'is_active' => true,
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHasErrors([
                'code' => 'รหัสหลักสูตรนี้ถูกใช้งานแล้ว',
                'name' => 'ชื่อหลักสูตรนี้ถูกใช้งานแล้ว',
            ]);
    }

    private function createLearningMethod(string $key, string $label): int
    {
        return DB::table('learning_method_types')->insertGetId([
            'key' => $key,
            'label' => $label,
            'description' => $label,
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createCompetency(string $code, string $name): int
    {
        $typeId = DB::table('competency_types')->insertGetId([
            'code' => $code.'-TYPE',
            'full_name' => 'Core Competency',
            'description' => 'Core Competency',
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId,
            'code' => $code,
            'name' => $name,
            'detail' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

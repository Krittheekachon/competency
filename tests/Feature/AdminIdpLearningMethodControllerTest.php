<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminIdpLearningMethodControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_experiential_or_social_method(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $nextSortOrder = DB::table('idp_learning_methods')
            ->where('focus_type', 'experiential')
            ->max('sort_order') + 1;

        $this->actingAs($admin)
            ->post(route('admin.idp-learning-methods.store'), [
                'focus_type' => 'experiential',
                'code' => 'EXP-X',
                'title' => 'การมอบหมายงานโครงการ',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $methodId = DB::table('idp_learning_methods')->where('title', 'การมอบหมายงานโครงการ')->value('id');

        $this->assertDatabaseHas('idp_learning_methods', [
            'id' => $methodId,
            'focus_type' => 'experiential',
            'code' => 'EXP-X',
            'title' => 'การมอบหมายงานโครงการ',
            'sort_order' => $nextSortOrder,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.idp-learning-methods.update', $methodId), [
                'focus_type' => 'social',
                'code' => 'SOC-X',
                'title' => 'การสอนงาน',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('idp_learning_methods', [
            'id' => $methodId,
            'focus_type' => 'social',
            'code' => 'SOC-X',
            'title' => 'การสอนงาน',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.idp-learning-methods.destroy', $methodId))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('idp_learning_methods', [
            'id' => $methodId,
        ]);
    }

    public function test_admin_cannot_create_formal_method_because_formal_is_learning_catalog(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.idp-learning-methods.store'), [
                'focus_type' => 'formal',
                'code' => 'FOR-X',
                'title' => 'Formal Learning Focus',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['focus_type']);

        $this->assertDatabaseMissing('idp_learning_methods', [
            'focus_type' => 'formal',
            'title' => 'Formal Learning Focus',
        ]);
    }

    public function test_admin_gets_validation_error_when_method_title_is_missing(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.idp-learning-methods.store'), [
                'focus_type' => 'social',
                'code' => 'SOC-M',
                'title' => '',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['title']);

        $this->assertDatabaseMissing('idp_learning_methods', [
            'focus_type' => 'social',
            'title' => '',
        ]);
    }

    public function test_new_method_sort_order_continues_within_same_focus_type(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $nextSortOrder = DB::table('idp_learning_methods')
            ->where('focus_type', 'social')
            ->max('sort_order') + 1;

        $this->actingAs($admin)
            ->post(route('admin.idp-learning-methods.store'), [
                'focus_type' => 'social',
                'code' => 'SOC-MENTOR',
                'title' => 'การเป็นพี่เลี้ยง',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('idp_learning_methods', [
            'focus_type' => 'social',
            'code' => 'SOC-MENTOR',
            'title' => 'การเป็นพี่เลี้ยง',
            'sort_order' => $nextSortOrder,
        ]);
    }
}

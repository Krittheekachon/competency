<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\IdpFillMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IdpFillModeSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_is_quick_and_admin_can_change_the_setting(): void
    {
        $this->assertSame('quick', app(IdpFillMode::class)->current());
        $admin = User::factory()->create(['role_id' => DB::table('roles')->where('key', 'admin')->value('id')]);
        $this->actingAs($admin)->put(route('admin.idp-fill-mode.update'), ['idp_fill_mode' => 'form'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('system_settings', ['key' => 'idp_fill_mode', 'value' => 'form']);
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('idpFillMode', 'form'));
        $this->put(route('admin.idp-fill-mode.update'), ['idp_fill_mode' => 'quick'])->assertSessionHasNoErrors();
        $this->assertSame('quick', app(IdpFillMode::class)->current());
    }

    public function test_setting_rejects_invalid_modes(): void
    {
        $admin = User::factory()->create(['role_id' => DB::table('roles')->where('key', 'admin')->value('id')]);
        $this->actingAs($admin)->putJson(route('admin.idp-fill-mode.update'), ['idp_fill_mode' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('idp_fill_mode');
        $this->assertSame('quick', app(IdpFillMode::class)->current());
    }

    public function test_non_admin_cannot_change_the_setting_but_receives_the_mode(): void
    {
        foreach (['employee', 'hr', 'dean'] as $role) {
            $user = User::factory()->create(['role_id' => DB::table('roles')->where('key', $role)->value('id')]);
            $this->actingAs($user)->put(route('admin.idp-fill-mode.update'), ['idp_fill_mode' => 'form'])->assertForbidden();
            $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('idpFillMode', 'quick'));
        }
    }

    public function test_saved_activity_format_is_independent_of_the_current_setting(): void
    {
        $settings = app(IdpFillMode::class);
        $settings->save('form');
        $this->assertSame('quick', $settings->fromActivities([['formDetails' => ['_behaviorPlan' => true]]]));
        $settings->save('quick');
        $this->assertSame('form', $settings->fromActivities([['formDetails' => ['_saved' => true]]]));
        $this->assertNull($settings->fromActivities([]));
    }
}

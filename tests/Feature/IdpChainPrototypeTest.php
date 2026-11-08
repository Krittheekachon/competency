<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IdpChainPrototypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_the_prototype_without_real_chain_data(): void
    {
        $admin = User::factory()->create(['role_id' => DB::table('roles')->where('key', 'admin')->value('id')]);
        $this->actingAs($admin)->get(route('admin.idp-chains', ['type' => 'idp']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')->where('prototypePage', 'admin-idp-chains')
                ->missing('users')->missing('reviewerChainTemplates'));
    }

    public function test_non_admin_cannot_open_the_prototype(): void
    {
        $employee = User::factory()->create(['role_id' => DB::table('roles')->where('key', 'employee')->value('id')]);
        $this->actingAs($employee)->get(route('admin.idp-chains'))->assertForbidden();
    }
}

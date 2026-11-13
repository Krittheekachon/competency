<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsFacultyAssessment;
use Tests\TestCase;

class ReviewerPresetScoringTest extends TestCase
{
    use BuildsFacultyAssessment;
    use RefreshDatabase;

    public function test_preset_carries_scoring_types_and_passes_them_to_its_users_on_create_edit_and_add(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        [$a, $b, $c] = User::factory()->count(3)->create()->all();
        [$first, $second] = User::factory()->count(2)->create()->all();

        $this->actingAs($admin)->post(route('admin.reviewer-chain-templates.store'), [
            'name' => 'สายทดสอบ', 'chain_type' => 'assessment', 'reviewer_ids' => [$a->id, $b->id],
            'scoring' => [$a->id => ['CC', 'FC']], 'assignment_user_ids' => [$first->id],
        ])->assertSessionHasNoErrors();

        $templateId = (int) DB::table('reviewer_chain_templates')->where('name', 'สายทดสอบ')->value('id');
        $this->assertSame(['CC,FC', null], DB::table('reviewer_chain_template_steps')->where('template_id', $templateId)->orderBy('step_order')->pluck('scoring_types')->all());
        $this->assertSame('CC,FC', $this->grant($first, $a));
        $this->assertNull($this->grant($first, $b));

        // Editing the preset re-applies the new grants to everyone already on it.
        $this->patch(route('admin.reviewer-chain-templates.update', $templateId), [
            'name' => 'สายทดสอบ', 'reviewer_ids' => [$b->id, $c->id], 'scoring' => [$b->id => ['MC'], $c->id => ['CC']],
        ])->assertSessionHasNoErrors();
        $this->assertSame('MC', $this->grant($first, $b));
        $this->assertSame('CC', $this->grant($first, $c));
        $this->assertSame(0, DB::table('user_reviewer_steps')->where('user_id', $first->id)->where('reviewer_id', $a->id)->count());

        // A user added later gets the preset's grants too.
        $this->post(route('admin.reviewer-chain-templates.users.store', $templateId), ['user_ids' => [$second->id]])->assertSessionHasNoErrors();
        $this->assertSame('MC', $this->grant($second, $b));
        $this->assertSame('CC', $this->grant($second, $c));
    }

    public function test_idp_presets_never_store_scoring_and_the_payload_exposes_grants(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleId('admin')]);
        $a = User::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.reviewer-chain-templates.store'), [
            'name' => 'IDP ทดสอบ', 'chain_type' => 'idp', 'reviewer_ids' => [$a->id], 'scoring' => [$a->id => ['CC']],
            'assignment_user_ids' => [$user->id],
        ])->assertSessionHasNoErrors();

        $this->assertNull(DB::table('reviewer_chain_template_steps')->value('scoring_types'));
        $this->assertNull(DB::table('user_reviewer_steps')->where('chain_type', 'idp')->value('scoring_types'));
        $payload = app(\App\Services\ReviewerTemplateResolver::class)->payload();
        $this->assertSame([], collect($payload)->firstWhere('name', 'IDP ทดสอบ')['steps'][0]['scoringTypes']);
    }

    private function grant(User $employee, User $reviewer): ?string
    {
        return DB::table('user_reviewer_steps')->where('user_id', $employee->id)->where('reviewer_id', $reviewer->id)
            ->where('chain_type', 'assessment')->value('scoring_types');
    }
}

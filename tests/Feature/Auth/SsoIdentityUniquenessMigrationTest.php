<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SsoIdentityUniquenessMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_refuses_duplicate_identities_without_changing_accounts(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_sso_normalized_unique');
        DB::statement('DROP INDEX IF EXISTS users_email_ci_unique');
        DB::statement('DROP INDEX IF EXISTS users_sso_user_id_normalized_unique');
        DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');
        DB::statement('DROP INDEX IF EXISTS users_sso_user_id_unique');

        User::factory()->create([
            'sso' => 'employee-duplicate',
            'email' => 'Conflict.User@kku.ac.th',
            'sso_user_id' => 'duplicate-kku-user',
        ]);
        User::factory()->create([
            'sso' => ' employee-duplicate ',
            'email' => 'conflict.user@kku.ac.th',
            'sso_user_id' => ' duplicate-kku-user ',
        ]);

        $before = User::query()->orderBy('id')->get(['id', 'email', 'sso_user_id'])->toArray();

        try {
            (require database_path('migrations/2026_10_25_000001_enforce_sso_identity_uniqueness.php'))->up();
            $this->fail('Migration should stop when duplicate identity groups exist.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('1 employee ID duplicate group(s)', $exception->getMessage());
            $this->assertStringContainsString('1 case-insensitive email duplicate group(s)', $exception->getMessage());
            $this->assertStringContainsString('1 sso_user_id duplicate group(s)', $exception->getMessage());
            $this->assertStringNotContainsString('Conflict.User@kku.ac.th', $exception->getMessage());
            $this->assertSame($before, User::query()->orderBy('id')->get(['id', 'email', 'sso_user_id'])->toArray());
        }
    }

    public function test_migration_rollback_preserves_accounts_and_can_be_reapplied(): void
    {
        $user = User::factory()->create([
            'email' => 'migration-check@kku.ac.th',
            'sso_user_id' => 'migration-check-user',
        ]);
        $migration = require database_path('migrations/2026_10_25_000001_enforce_sso_identity_uniqueness.php');

        $migration->down();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'sso_user_id' => 'migration-check-user']);

        $migration->up();
        $this->expectException(\Illuminate\Database\QueryException::class);
        User::factory()->create(['email' => 'MIGRATION-CHECK@kku.ac.th']);
    }

    public function test_normalized_employee_id_index_rejects_outer_whitespace_duplicates(): void
    {
        User::factory()->create(['email' => 'employee-id-one@kku.ac.th', 'sso' => 'employee-id-one']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        User::factory()->create(['email' => 'employee-id-two@kku.ac.th', 'sso' => ' employee-id-one ']);
    }
}

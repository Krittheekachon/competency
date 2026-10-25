<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;
use Tests\TestCase;

class EnforceSsoIdentityUniquenessMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_keeps_existing_rounds_fail_closed_without_reclassifying_or_creating_snapshots(): void
    {
        $this->prepareMigrationSchema();
        $roundId = (int) DB::table('assessment_rounds')->insertGetId([
            'name' => 'Existing round',
            'year' => 2567,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_10_24_000002_enforce_sso_identity_uniqueness.php');
        $migration->up();

        $this->assertTrue((bool) DB::table('assessment_rounds')->where('id', $roundId)->value('snapshot_required'));
        $this->assertSame(0, DB::table('user_round_profiles')->where('assessment_round_id', $roundId)->count());
        $this->assertSame(0, DB::table('user_round_reviewer_steps')->where('assessment_round_id', $roundId)->count());
    }

    public function test_migration_refuses_duplicate_identifiers_without_modifying_accounts(): void
    {
        $this->prepareMigrationSchema();
        $firstId = DB::table('users')->insertGetId($this->userValues('CaseCheck@example.test', 'employee-dup', 'sso-user-dup'));
        $secondId = DB::table('users')->insertGetId($this->userValues('other@example.test', 'employee-dup', 'sso-user-other'));
        $before = DB::table('users')->whereIn('id', [$firstId, $secondId])->orderBy('id')->get()->toArray();

        $migration = require database_path('migrations/2026_10_24_000002_enforce_sso_identity_uniqueness.php');
        try {
            $migration->up();
            $this->fail('Expected duplicate identifiers to block migration.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('duplicate employee IDs', $exception->getMessage());
        }

        $after = DB::table('users')->whereIn('id', [$firstId, $secondId])->orderBy('id')->get()->toArray();
        $this->assertEquals($before, $after);
        $this->assertFalse(Schema::hasColumn('assessment_rounds', 'snapshot_required'));
    }

    public function test_case_insensitive_email_duplicate_is_rejected_by_precheck_without_account_changes(): void
    {
        $this->prepareMigrationSchema();
        $firstId = DB::table('users')->insertGetId($this->userValues('MixedCase@example.test', 'employee-unique', 'sso-user-unique'));
        $secondId = DB::table('users')->insertGetId($this->userValues('mixedcase@EXAMPLE.test', 'employee-other', 'sso-user-other'));
        $before = DB::table('users')->whereIn('id', [$firstId, $secondId])->orderBy('id')->get()->toArray();
        $migration = require database_path('migrations/2026_10_24_000002_enforce_sso_identity_uniqueness.php');

        try {
            $migration->up();
            $this->fail('Expected case-insensitive email duplicate to block migration.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('case-insensitive email values', $exception->getMessage());
        }

        $this->assertEquals($before, DB::table('users')->whereIn('id', [$firstId, $secondId])->orderBy('id')->get()->toArray());
    }

    public function test_migration_can_be_rolled_back_without_removing_existing_accounts(): void
    {
        $this->prepareMigrationSchema();
        DB::table('users')->insert($this->userValues('rollback@example.test', 'employee-rollback', 'sso-user-rollback'));
        $before = DB::table('users')->orderBy('id')->get()->toArray();
        $migration = require database_path('migrations/2026_10_24_000002_enforce_sso_identity_uniqueness.php');
        try {
            $migration->up();
        } catch (Throwable $exception) {
            if (DB::connection()->getDriverName() !== 'sqlite') {
                throw $exception;
            }

            $this->markTestSkipped('Migration uses a PostgreSQL expression index that SQLite cannot verify.');
        }

        $migration->down();
        $this->assertFalse(Schema::hasColumn('assessment_rounds', 'snapshot_required'));
        $this->assertFalse($this->indexExists('users_email_lower_unique'));
        $this->assertEquals($before, DB::table('users')->orderBy('id')->get()->toArray());
    }

    /** @return array<string, mixed> */
    private function userValues(string $email, string $sso, string $ssoUserId): array
    {
        return [
            'name' => 'Migration test user',
            'email' => $email,
            'password' => bcrypt('temporary-test-password'),
            'sso' => $sso,
            'sso_user_id' => $ssoUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function indexExists(string $name): bool
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return false;
        }

        return DB::table('sqlite_master')->where('type', 'index')->where('name', $name)->exists();
    }

    private function prepareMigrationSchema(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        // RefreshDatabase already ran the full migration stack, including these new indexes.
        // Remove only the target objects inside this test's isolated in-memory database so
        // the migration can be exercised from its actual pre-migration schema state.
        DB::statement('DROP INDEX IF EXISTS users_sso_unique');
        DB::statement('DROP INDEX IF EXISTS users_sso_user_id_unique');
        DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');
        DB::statement('DROP INDEX IF EXISTS users_sso_normalized_unique');
        DB::statement('DROP INDEX IF EXISTS users_email_ci_unique');
        DB::statement('DROP INDEX IF EXISTS users_sso_user_id_normalized_unique');
        if (Schema::hasColumn('assessment_rounds', 'snapshot_required')) {
            Schema::table('assessment_rounds', fn ($table) => $table->dropColumn('snapshot_required'));
        }
    }
}

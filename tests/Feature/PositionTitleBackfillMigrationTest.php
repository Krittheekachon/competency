<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class PositionTitleBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_maps_only_known_worklines_and_preserves_aliased_titles_on_rollback(): void
    {
        $examples = [
            ['วิชาการ', 'academic'],
            ['สายวิชาการ', 'academic'],
            ['สายงานวิชาการ', 'academic'],
            ['สนับสนุน', 'support'],
            ['สายสนับสนุน', 'support'],
            ['สายงานสนับสนุน', 'support'],
            ['บริหาร', null],
        ];

        $positionExpectations = [];
        foreach ($examples as [$worklineName, $expectedType]) {
            $worklineId = DB::table('worklines')->insertGetId([
                'name' => $worklineName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $jobFamilyId = DB::table('job_families')->insertGetId([
                'workline_id' => $worklineId,
                'name' => 'Family '.$worklineId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $positionId = DB::table('positions')->insertGetId([
                'job_family_id' => $jobFamilyId,
                'name' => 'Position '.$worklineId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $positionExpectations[] = [
                'position_id' => $positionId,
                'expected_type' => $expectedType,
            ];
        }

        $migration = require database_path('migrations/2026_10_19_000003_backfill_existing_positions_into_position_titles.php');
        $migration->up();

        $titleIds = [];
        foreach ($positionExpectations as $example) {
            $positionId = $example['position_id'];
            $expectedType = $example['expected_type'];
            $position = DB::table('positions')->where('id', $positionId)->first();
            if ($expectedType === null) {
                $this->assertNull($position->position_title_id);
                continue;
            }

            $title = DB::table('position_titles')->find($position->position_title_id);
            $this->assertSame($expectedType, $title->workline_type);
            $titleIds[] = (int) $title->id;
        }

        $expectedTitleCount = DB::table('position_titles')->where('source', 'legacy')->count();
        $migration->up();
        $this->assertSame($expectedTitleCount, DB::table('position_titles')->where('source', 'legacy')->count());

        $titleId = $titleIds[0];
        DB::table('position_title_aliases')->insert([
            'position_title_id' => $titleId,
            'name' => 'Old title',
            'normalized_name' => 'old title',
            'source' => 'sso',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->down();

        $this->assertDatabaseHas('position_titles', ['id' => $titleId, 'source' => 'legacy']);
        $this->assertDatabaseHas('position_title_aliases', ['position_title_id' => $titleId, 'normalized_name' => 'old title']);
    }

    public function test_fingerprint_migration_refuses_rollback_when_duplicate_rows_exist(): void
    {
        $user = User::factory()->create();
        foreach ([1, 2] as $_) {
            DB::table('sso_profile_changes')->insert([
                'user_id' => $user->id,
                'fingerprint' => str_repeat('a', 64),
                'incoming_profile' => '{}',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $migration = require database_path('migrations/2026_10_19_000002_allow_repeated_sso_profile_fingerprints.php');

        try {
            $migration->down();
            $this->fail('Expected duplicate fingerprints to prevent rollback.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('duplicate rows exist', $exception->getMessage());
        }

        $this->assertSame(2, DB::table('sso_profile_changes')->where('user_id', $user->id)->count());
    }
}

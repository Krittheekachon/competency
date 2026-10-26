<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('position_title_id')->nullable()->after('position_id')
                ->constrained('position_titles')->nullOnDelete();
        });

        foreach (['position_competencies', 'position_fc_selection_rules', 'fc_topic_selections', 'hr_expectations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('position_title_id')->nullable()->after('position_id')
                    ->constrained('position_titles')->nullOnDelete();
            });
        }

        Schema::table('fc_topic_selections', function (Blueprint $table): void {
            $table->foreignId('position_id')->nullable()->change();
        });

        Schema::table('user_round_profiles', function (Blueprint $table): void {
            $table->foreignId('position_title_id')->nullable()->after('position_id')
                ->constrained('position_titles')->nullOnDelete();
        });

        $positionTitles = DB::table('positions')->pluck('position_title_id', 'id');

        DB::table('users')->whereNotNull('position_id')->orderBy('id')->eachById(
            function (object $user) use ($positionTitles): void {
                DB::table('users')->where('id', $user->id)->update([
                    'position_title_id' => $positionTitles->get($user->position_id),
                ]);
            },
            100,
            'id',
            'id',
        );

        foreach (['position_competencies', 'position_fc_selection_rules', 'fc_topic_selections', 'hr_expectations'] as $tableName) {
            DB::table($tableName)->whereNotNull('position_id')->orderBy('id')->eachById(
                function (object $row) use ($tableName, $positionTitles): void {
                    DB::table($tableName)->where('id', $row->id)->update([
                        'position_title_id' => $positionTitles->get($row->position_id),
                    ]);
                },
                100,
                'id',
                'id',
            );
        }

        DB::table('user_round_profiles')->whereNotNull('position_id')->orderBy('id')->eachById(
            function (object $profile) use ($positionTitles): void {
                DB::table('user_round_profiles')->where('id', $profile->id)->update([
                    'position_title_id' => $positionTitles->get($profile->position_id),
                ]);
            },
            100,
            'id',
            'id',
        );

        DB::table('position_competencies')
            ->whereNotNull('position_title_id')
            ->orderBy('id')
            ->get(['id', 'assessment_round_id', 'position_title_id', 'competency_id'])
            ->groupBy(fn (object $row): string => implode(':', [
                $row->assessment_round_id,
                $row->position_title_id,
                $row->competency_id,
            ]))
            ->each(function ($rows): void {
                DB::table('position_competencies')->whereIn('id', $rows->pluck('id')->slice(1))->delete();
            });

        DB::table('position_fc_selection_rules')
            ->whereNotNull('position_title_id')
            ->orderBy('id')
            ->get(['id', 'assessment_round_id', 'position_title_id', 'required_fc_count'])
            ->groupBy(fn (object $row): string => $row->assessment_round_id.':'.$row->position_title_id)
            ->each(function ($rows, string $scope): void {
                if ($rows->pluck('required_fc_count')->unique()->count() > 1) {
                    throw new RuntimeException("ตำแหน่งกลาง {$scope} มีจำนวน FC ที่ขัดแย้งกัน กรุณาแก้ข้อมูลก่อน migrate");
                }

                DB::table('position_fc_selection_rules')->whereIn('id', $rows->pluck('id')->slice(1))->delete();
            });

        DB::statement('CREATE UNIQUE INDEX position_competencies_round_title_competency_unique ON position_competencies (assessment_round_id, position_title_id, competency_id) WHERE position_title_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX position_fc_rules_round_title_unique ON position_fc_selection_rules (assessment_round_id, position_title_id) WHERE position_title_id IS NOT NULL');
        DB::statement('CREATE INDEX hr_expectations_round_title_competency_index ON hr_expectations (assessment_round_id, position_title_id, competency_id)');
        DB::statement('CREATE INDEX fc_topic_selections_round_user_title_index ON fc_topic_selections (assessment_round_id, user_id, position_title_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS fc_topic_selections_round_user_title_index');
        DB::statement('DROP INDEX IF EXISTS hr_expectations_round_title_competency_index');
        DB::statement('DROP INDEX IF EXISTS position_fc_rules_round_title_unique');
        DB::statement('DROP INDEX IF EXISTS position_competencies_round_title_competency_unique');

        DB::table('fc_topic_selections')->whereNull('position_id')->delete();
        Schema::table('fc_topic_selections', function (Blueprint $table): void {
            $table->foreignId('position_id')->nullable(false)->change();
        });

        Schema::table('user_round_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('position_title_id');
        });

        foreach (['hr_expectations', 'fc_topic_selections', 'position_fc_selection_rules', 'position_competencies'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('position_title_id');
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('position_title_id');
        });
    }
};

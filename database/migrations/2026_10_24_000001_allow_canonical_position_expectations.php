<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_expectations', function (Blueprint $table): void {
            $table->foreignId('level_id')->nullable()->change();
        });

        DB::statement('CREATE UNIQUE INDEX hr_expectations_round_title_competency_unique ON hr_expectations (assessment_round_id, position_title_id, competency_id) WHERE position_title_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS hr_expectations_round_title_competency_unique');
        DB::table('hr_expectations')->whereNull('level_id')->delete();
        Schema::table('hr_expectations', function (Blueprint $table): void {
            $table->foreignId('level_id')->nullable(false)->change();
        });
    }
};

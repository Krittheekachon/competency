<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Workline-level positions (dean, deputy dean, assistant dean) have no position row,
        // so their competencies are bound to the position title alone.
        Schema::table('position_competencies', function (Blueprint $table): void {
            $table->unsignedBigInteger('position_id')->nullable()->change();
        });

        // The existing unique index covers (round, position, competency); NULL positions need their own.
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS position_competencies_round_title_unique ON position_competencies (assessment_round_id, position_title_id, competency_id) WHERE position_id IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS position_competencies_round_title_unique');
        DB::table('position_competencies')->whereNull('position_id')->delete();

        Schema::table('position_competencies', function (Blueprint $table): void {
            $table->unsignedBigInteger('position_id')->nullable(false)->change();
        });
    }
};

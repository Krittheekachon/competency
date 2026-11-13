<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin decides, per reviewer step, which competency types (CC, FC, MC) that reviewer may score.
     * Comma separated; NULL or empty means the reviewer can only approve or return.
     */
    public function up(): void
    {
        foreach (['user_reviewer_steps', 'user_round_reviewer_steps'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('scoring_types', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['user_reviewer_steps', 'user_round_reviewer_steps'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('scoring_types');
            });
        }
    }
};

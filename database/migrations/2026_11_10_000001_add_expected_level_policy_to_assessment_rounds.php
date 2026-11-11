<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // by_level: expected level comes from the employee's level (levels.expected_level).
        // fixed: every assigned competency in the round expects expected_level_fixed.
        Schema::table('assessment_rounds', function (Blueprint $table): void {
            $table->string('expected_level_mode', 20)->default('by_level')->after('assessment_method_version');
            $table->unsignedTinyInteger('expected_level_fixed')->nullable()->after('expected_level_mode');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_rounds', function (Blueprint $table): void {
            $table->dropColumn(['expected_level_mode', 'expected_level_fixed']);
        });
    }
};

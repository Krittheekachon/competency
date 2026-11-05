<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing dictionary entries are the university five-level format.
        Schema::table('competencies', function (Blueprint $table): void {
            $table->string('assessment_method', 30)->default('standard')->index();
        });
    }

    public function down(): void
    {
        Schema::table('competencies', function (Blueprint $table): void {
            $table->dropIndex(['assessment_method']);
            $table->dropColumn('assessment_method');
        });
    }
};

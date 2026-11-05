<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_rounds', function (Blueprint $table): void {
            $table->string('assessment_method', 30)->default('standard')->after('year');
            $table->unsignedSmallInteger('assessment_method_version')->default(1)->after('assessment_method');
            $table->index('assessment_method', 'assessment_rounds_method_index');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_rounds', function (Blueprint $table): void {
            $table->dropIndex('assessment_rounds_method_index');
            $table->dropColumn(['assessment_method', 'assessment_method_version']);
        });
    }
};

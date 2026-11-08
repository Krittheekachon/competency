<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_rounds', function (Blueprint $table): void {
            $table->date('approval_start')->nullable();
            $table->date('approval_end')->nullable();
            $table->date('progress_report_start')->nullable();
            $table->date('progress_report_end')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_rounds', function (Blueprint $table): void {
            $table->dropColumn(['approval_start', 'approval_end', 'progress_report_start', 'progress_report_end']);
        });
    }
};

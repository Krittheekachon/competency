<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('hr_expectations');
    }

    public function down(): void
    {
        Schema::create('hr_expectations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_round_id')->constrained('assessment_rounds')->cascadeOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->cascadeOnDelete();
            $table->foreignId('position_title_id')->nullable()->constrained('position_titles')->nullOnDelete();
            $table->foreignId('level_id')->nullable()->constrained('levels')->cascadeOnDelete();
            $table->foreignId('job_family_id')->nullable()->constrained('job_families')->nullOnDelete();
            $table->foreignId('competency_id')->constrained('competencies')->cascadeOnDelete();
            $table->unsignedTinyInteger('expected_level')->nullable();
            $table->timestamps();

            $table->unique(
                ['assessment_round_id', 'position_id', 'level_id', 'competency_id'],
                'hr_expectations_unique_scope'
            );
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX hr_expectations_round_title_competency_unique ON hr_expectations (assessment_round_id, position_title_id, competency_id) WHERE position_title_id IS NOT NULL');
            DB::statement('CREATE INDEX hr_expectations_round_title_competency_index ON hr_expectations (assessment_round_id, position_title_id, competency_id)');
        }
    }
};

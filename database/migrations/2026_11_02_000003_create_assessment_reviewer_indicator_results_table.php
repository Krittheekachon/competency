<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_reviewer_indicator_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained('competencies')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('review_step');
            $table->string('indicator_key');
            $table->boolean('is_checked')->default(true);
            $table->timestamps();

            $table->unique(
                ['assessment_id', 'competency_id', 'reviewer_id', 'indicator_key'],
                'assessment_reviewer_indicator_unique'
            );
            $table->index(
                ['assessment_id', 'competency_id', 'review_step'],
                'assessment_reviewer_indicator_step_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_reviewer_indicator_results');
    }
};

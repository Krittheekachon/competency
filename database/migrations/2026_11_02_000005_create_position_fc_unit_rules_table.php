<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unit-specific override of position_fc_selection_rules.required_fc_count; FC choices still come from the position.
        Schema::create('position_fc_unit_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_round_id')->constrained('assessment_rounds')->cascadeOnDelete();
            $table->foreignId('position_title_id')->constrained('position_titles')->cascadeOnDelete();
            $table->foreignId('support_unit_id')->constrained('support_units')->cascadeOnDelete();
            $table->unsignedSmallInteger('required_fc_count');
            $table->timestamps();
            $table->unique(
                ['assessment_round_id', 'position_title_id', 'support_unit_id'],
                'position_fc_unit_rules_round_title_unit_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_fc_unit_rules');
    }
};

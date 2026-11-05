<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Faculty-method plan items cover every failed competency gap of the person, not one gap.
        Schema::create('idp_item_competency_gaps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('idp_item_id')->constrained('idp_items')->cascadeOnDelete();
            $table->foreignId('competency_gap_id')->constrained('competency_gaps')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['idp_item_id', 'competency_gap_id'], 'idp_item_competency_gaps_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idp_item_competency_gaps');
    }
};

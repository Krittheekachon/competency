<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A preset chain carries which competency types (CC, FC, MC) each reviewer may score. */
    public function up(): void
    {
        Schema::table('reviewer_chain_template_steps', function (Blueprint $table): void {
            $table->string('scoring_types', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reviewer_chain_template_steps', function (Blueprint $table): void {
            $table->dropColumn('scoring_types');
        });
    }
};

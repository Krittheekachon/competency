<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('support_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('support_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_department_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('name');
            $table->unique(['support_department_id', 'name']);
            $table->timestamps();
        });

        Schema::create('support_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_work_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('name');
            $table->unique(['support_work_id', 'name']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_units');
        Schema::dropIfExists('support_works');
        Schema::dropIfExists('support_departments');
    }
};

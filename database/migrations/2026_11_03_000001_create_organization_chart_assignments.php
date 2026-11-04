<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_chart_assignments', function (Blueprint $table): void {
            $table->string('node_key')->primary();
            $table->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('members_overridden')->default(false);
            $table->timestamps();
        });
        Schema::create('organization_chart_members', function (Blueprint $table): void {
            $table->string('node_key');
            $table->foreign('node_key')->references('node_key')->on('organization_chart_assignments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['node_key', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_chart_members');
        Schema::dropIfExists('organization_chart_assignments');
    }
};

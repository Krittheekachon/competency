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
        Schema::table('comp_level_indicators', function (Blueprint $table) {
            $table->decimal('weight', 5, 2)->default(0)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('comp_level_indicators', function (Blueprint $table) {
            $table->dropColumn('weight');
        });
    }
};

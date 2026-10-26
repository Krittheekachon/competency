<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('levels')->whereNull('workline_id')->delete();

        Schema::table('levels', function (Blueprint $table): void {
            $table->dropForeign(['workline_id']);
            $table->unsignedBigInteger('workline_id')->nullable(false)->change();
            $table->foreign('workline_id')->references('id')->on('worklines')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('levels', function (Blueprint $table): void {
            $table->dropForeign(['workline_id']);
            $table->unsignedBigInteger('workline_id')->nullable()->change();
            $table->foreign('workline_id')->references('id')->on('worklines')->nullOnDelete();
        });
    }
};

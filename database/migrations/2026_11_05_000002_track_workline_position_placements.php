<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('position_titles', function (Blueprint $table): void {
            $table->boolean('is_workline_position')->default(false);
        });
        // Preserve exactly the management titles previously shown at workline root.
        DB::table('position_titles')->whereNotNull('workline_id')->where('workline_type', 'management')
            ->whereNotIn('id', DB::table('positions')->whereNotNull('position_title_id')->select('position_title_id'))
            ->update(['is_workline_position' => true]);
    }

    public function down(): void
    {
        Schema::table('position_titles', fn (Blueprint $table) => $table->dropColumn('is_workline_position'));
    }
};

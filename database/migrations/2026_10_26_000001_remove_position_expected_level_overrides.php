<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('hr_expectations')
            ->whereNotNull('position_title_id')
            ->delete();
    }

    public function down(): void
    {
        // ระดับความคาดหวังเดิมซ้ำกับค่าจาก levels และไม่สามารถกู้คืนอย่างถูกต้องได้
    }
};

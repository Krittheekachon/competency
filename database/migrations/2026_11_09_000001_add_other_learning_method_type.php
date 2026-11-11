<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('learning_method_types')) {
            return;
        }

        // A development method the employee types in themselves; it has no tool or catalog row.
        DB::table('learning_method_types')->updateOrInsert(
            ['key' => 'other-learning'],
            [
                'label' => 'อื่นๆ',
                'description' => 'วิธีพัฒนาที่ผู้ใช้ระบุเอง',
                'is_active' => true,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('learning_method_types')) {
            return;
        }

        $id = DB::table('learning_method_types')->where('key', 'other-learning')->value('id');
        if (! $id) {
            return;
        }

        DB::transaction(function () use ($id): void {
            if (Schema::hasTable('idp_activities') && Schema::hasColumn('idp_activities', 'method_type_id')) {
                DB::table('idp_activities')->where('method_type_id', $id)->update(['method_type_id' => null]);
            }
            DB::table('learning_method_types')->where('id', $id)->delete();
        });
    }
};

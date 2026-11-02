<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $roles = [
            ['key' => 'deputy_dean', 'name_th' => 'รองคณบดี', 'name_en' => 'Deputy Dean'],
            ['key' => 'assistant_dean', 'name_th' => 'ผู้ช่วยคณบดี', 'name_en' => 'Assistant Dean'],
        ];

        foreach ($roles as $role) {
            $values = [
                'name_th' => $role['name_th'],
                'name_en' => $role['name_en'],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('roles', 'role_key')) {
                $values['role_key'] = $role['key'];
            }

            if (Schema::hasColumn('roles', 'role_id')) {
                $existingRoleId = DB::table('roles')->where('key', $role['key'])->value('role_id');
                $values['role_id'] = $existingRoleId ?? (((int) DB::table('roles')->max('role_id')) + 1);
            }

            DB::table('roles')->updateOrInsert(['key' => $role['key']], $values);
        }
    }

    public function down(): void
    {
        DB::table('roles')->whereIn('key', ['deputy_dean', 'assistant_dean'])->delete();
    }
};

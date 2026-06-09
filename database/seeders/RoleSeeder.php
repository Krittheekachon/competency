<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    // database/seeders/RoleSeeder.php
    public function run(): void
    {
        $roles = [
            ['role_id' => 0, 'role_key' => 'admin',        'name_th' => 'ผู้ดูแลระบบ',         'name_en' => 'Admin'],
            ['role_id' => 1, 'role_key' => 'supervisor',   'name_th' => 'ผู้บังคับบัญชา',      'name_en' => 'Supervisor'],
            ['role_id' => 2, 'role_key' => 'dept_head',    'name_th' => 'ผู้บริหารหน่วยงาน',   'name_en' => 'Department Head'],
            ['role_id' => 3, 'role_key' => 'employee',     'name_th' => 'บุคลากร',             'name_en' => 'Employee'],
            ['role_id' => 4, 'role_key' => 'hr',           'name_th' => 'HR',                  'name_en' => 'HR'],
            ['role_id' => 5, 'role_key' => 'dean',         'name_th' => 'ผู้บริหารคณะ',        'name_en' => 'Dean'],
        ];

        foreach ($roles as $role) {
            \DB::table('roles')->updateOrInsert(
                ['role_id' => $role['role_id']],
                $role
            );
        }
    }
}

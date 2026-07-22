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
            ['key' => 'admin',      'name_th' => 'ผู้ดูแลระบบ',      'name_en' => 'Admin'],
            ['key' => 'supervisor', 'name_th' => 'หัวหน้าหน่วย',        'name_en' => 'Supervisor'],
            ['key' => 'dept_head',  'name_th' => 'หัวหน้างาน',    'name_en' => 'Department Head'],
            ['key' => 'employee',   'name_th' => 'บุคลากร',           'name_en' => 'Employee'],
            ['key' => 'hr',         'name_th' => 'งานทรัพยากรบุคคล', 'name_en' => 'HR'],
            ['key' => 'dean',       'name_th' => 'ผู้บริหารคณะ',      'name_en' => 'Dean'],
        ];

        foreach ($roles as $role) {
            \DB::table('roles')->updateOrInsert(
                ['key' => $role['key']],
                $role
            );
        }
    }
}

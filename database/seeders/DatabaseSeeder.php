<?php

namespace Database\Seeders;

use App\Models\CompetencyType;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $employeeLevelId = DB::table('levels')
            ->where('name', 'รองศาสตราจารย์')
            ->value('id');

        $users = [
            ['name' => 'Admin User',      'email' => 'admin@test.com',   'role' => 'admin'],
            ['name' => 'HR User',         'email' => 'hr@test.com',      'role' => 'hr'],
            ['name' => 'Supervisor User', 'email' => 'supervisor@test.com', 'role' => 'supervisor'],
            ['name' => 'Department Head User', 'email' => 'dept_head@test.com', 'role' => 'dept_head'],
            ['name' => 'Employee User',   'email' => 'employee@test.com', 'role' => 'employee', 'workline' => 'วิชาการ', 'level' => 'รองศาสตราจารย์', 'level_id' => $employeeLevelId],
            ['name' => 'Dean User',       'email' => 'dean@test.com',    'role' => 'dean'],
        ];

        foreach ($users as $user) {
            $roleId = DB::table('roles')->where('key', $user['role'])->value('id');

            $attributes = [
                'name' => $user['name'],
                'password' => Hash::make('password'),
                'role_id' => $roleId,
            ];

            if (array_key_exists('workline', $user)) {
                $attributes['workline'] = $user['workline'];
            }

            if (array_key_exists('level', $user)) {
                $attributes['level'] = $user['level'];
            }

            if (array_key_exists('level_id', $user)) {
                $attributes['level_id'] = $user['level_id'];
            }

            User::updateOrCreate(
                ['email' => $user['email']],
                $attributes,
            );
        }

        $competencyTypes = [
            ['code' => 'CC', 'full_name' => 'Core Competency', 'description' => 'สมรรถนะหลักที่บุคลากรทุกตำแหน่งควรมีร่วมกัน'],
            ['code' => 'MC', 'full_name' => 'Managerial Competency', 'description' => 'สมรรถนะด้านการบริหารและภาวะผู้นำสำหรับตำแหน่งบริหารหรือหัวหน้างาน'],
            ['code' => 'FC1', 'full_name' => 'Functional Competency 1', 'description' => 'สมรรถนะเฉพาะตามสายงานหรือกลุ่มงานระดับที่ 1'],
            ['code' => 'FC2', 'full_name' => 'Functional Competency 2', 'description' => 'สมรรถนะเฉพาะตามสายงานหรือกลุ่มงานระดับที่ 2'],
        ];

        foreach ($competencyTypes as $type) {
            CompetencyType::updateOrCreate(
                ['code' => $type['code']],
                $type,
            );
        }
    }
}

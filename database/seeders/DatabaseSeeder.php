<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = [
                ['name' => 'Admin User', 'email' => 'admin@test.com', 'role_id' => 0, 'password' => 'adminadmin'],
                ['name' => 'HR User', 'email' => 'hr@test.com', 'role_id' => 1, 'password' => 'hrhrhrhr'],
                ['name' => 'Executive User', 'email' => 'exec@test.com', 'role_id' => 2, 'password' => 'execexec'],
                ['name' => 'Supervisor User', 'email' => 'super@test.com', 'role_id' => 3, 'password' => 'supersuper'],
                ['name' => 'Staff User', 'email' => 'staff@test.com', 'role_id' => 4, 'password' => 'staffstaff'],
        ];

        foreach ($users as $u) {
            User::create([
                'name' => $u['name'],
                'email' => $u['email'],
                'password' => Hash::make($u['password']), // รหัสผ่านง่อยๆ สำหรับ Test
                'role_id' => $u['role_id'],
            ]);
        }
    }
}

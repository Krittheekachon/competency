<?php

namespace Database\Seeders;

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
        $this->call(RoleSeeder::class);

        DB::table('positions')->delete();
        DB::table('levels')->delete();
        DB::table('job_families')->delete();

        $defaultWorklines = ['สายบริหาร', 'สายวิชาการ', 'สายสนับสนุน'];

        DB::table('worklines')->delete();

        foreach ($defaultWorklines as $workline) {
            $exists = DB::table('worklines')->where('name', $workline)->exists();

            if ($exists) {
                DB::table('worklines')->where('name', $workline)->update(['updated_at' => now()]);
                continue;
            }

            DB::table('worklines')->insert([
                'name' => $workline,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $users = [
            ['name' => 'Admin',      'email' => 'admin@test.com',      'role_id' => 0, 'role_key' => 'admin'],
            ['name' => 'HR',         'email' => 'hr@test.com',         'role_id' => 4, 'role_key' => 'hr'],
            ['name' => 'Dean',       'email' => 'dean@test.com',       'role_id' => 5, 'role_key' => 'dean'],
            ['name' => 'User',       'email' => 'user@test.com',       'role_id' => 3, 'role_key' => 'employee'],
            ['name' => 'Supervisor', 'email' => 'supervisor@test.com', 'role_id' => 1, 'role_key' => 'supervisor'],
            ['name' => 'Head',       'email' => 'head@test.com',       'role_id' => 2, 'role_key' => 'dept_head'],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'first_name_th' => $user['name'],
                    'last_name_th' => '',
                    'password' => Hash::make('password'),
                    'role_id' => $user['role_id'],
                    'role_key' => $user['role_key'],
                    'is_active' => true,
                ],
            );
        }
    }
}

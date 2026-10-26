<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateEmployeeIdGroups = DB::table('users')
            ->selectRaw('TRIM(sso) AS normalized_employee_id')
            ->whereNotNull('sso')
            ->whereRaw("TRIM(sso) <> ''")
            ->groupByRaw('TRIM(sso)')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $duplicateEmailGroups = DB::table('users')
            ->selectRaw('LOWER(TRIM(email)) AS normalized_email')
            ->whereNotNull('email')
            ->whereRaw("TRIM(email) <> ''")
            ->groupByRaw('LOWER(TRIM(email))')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $duplicateSsoUserIdGroups = DB::table('users')
            ->selectRaw('TRIM(sso_user_id) AS normalized_sso_user_id')
            ->whereNotNull('sso_user_id')
            ->whereRaw("TRIM(sso_user_id) <> ''")
            ->groupByRaw('TRIM(sso_user_id)')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicateEmployeeIdGroups > 0 || $duplicateEmailGroups > 0 || $duplicateSsoUserIdGroups > 0) {
            throw new RuntimeException(sprintf(
                'Cannot enforce SSO identity uniqueness: found %d employee ID duplicate group(s), %d case-insensitive email duplicate group(s), and %d sso_user_id duplicate group(s). Resolve duplicates manually; no user records were changed.',
                $duplicateEmployeeIdGroups,
                $duplicateEmailGroups,
                $duplicateSsoUserIdGroups,
            ));
        }

        DB::statement(
            "CREATE UNIQUE INDEX users_sso_normalized_unique ON users (TRIM(sso)) WHERE sso IS NOT NULL AND TRIM(sso) <> ''"
        );
        DB::statement(
            "CREATE UNIQUE INDEX users_email_ci_unique ON users (LOWER(TRIM(email))) WHERE email IS NOT NULL AND TRIM(email) <> ''"
        );
        DB::statement(
            "CREATE UNIQUE INDEX users_sso_user_id_normalized_unique ON users (TRIM(sso_user_id)) WHERE sso_user_id IS NOT NULL AND TRIM(sso_user_id) <> ''"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_sso_normalized_unique');
        DB::statement('DROP INDEX IF EXISTS users_sso_user_id_normalized_unique');
        DB::statement('DROP INDEX IF EXISTS users_email_ci_unique');
    }
};

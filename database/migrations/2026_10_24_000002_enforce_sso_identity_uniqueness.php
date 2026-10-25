<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateEmployeeIds = DB::table('users')->select('sso')
            ->whereNotNull('sso')->groupBy('sso')->havingRaw('COUNT(*) > 1')->limit(5)->pluck('sso')->all();
        $duplicateSsoUserIds = DB::table('users')->select('sso_user_id')
            ->whereNotNull('sso_user_id')->groupBy('sso_user_id')->havingRaw('COUNT(*) > 1')->limit(5)->pluck('sso_user_id')->all();
        $duplicateEmails = DB::table('users')->selectRaw('LOWER(email) AS normalized_email')
            ->whereNotNull('email')->groupByRaw('LOWER(email)')->havingRaw('COUNT(*) > 1')->limit(5)->pluck('normalized_email')->all();

        if ($duplicateEmployeeIds !== [] || $duplicateSsoUserIds !== [] || $duplicateEmails !== []) {
            throw new RuntimeException(sprintf(
                'Cannot enforce SSO identity uniqueness: duplicate employee IDs (%d sample groups), sso_user_id values (%d sample groups), or case-insensitive email values (%d sample groups) exist. Review and resolve duplicate accounts explicitly before retrying; no rows were changed.',
                count($duplicateEmployeeIds), count($duplicateSsoUserIds), count($duplicateEmails),
            ));
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('sso_user_id', 'users_sso_user_id_unique');
        });
        DB::statement('CREATE UNIQUE INDEX users_email_lower_unique ON users (LOWER(email)) WHERE email IS NOT NULL');
        Schema::table('assessment_rounds', function (Blueprint $table): void {
            $table->boolean('snapshot_required')->default(true);
        });
        // Existing rounds cannot be classified safely from the available schema.
        // Keep them fail-closed until an explicit, evidence-based legacy policy exists.
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_sso_user_id_unique');
        });
        Schema::table('assessment_rounds', function (Blueprint $table): void {
            $table->dropColumn('snapshot_required');
        });
    }
};

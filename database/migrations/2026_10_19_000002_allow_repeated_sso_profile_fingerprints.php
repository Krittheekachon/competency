<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sso_profile_changes', function (Blueprint $table): void {
            $table->dropUnique('sso_profile_changes_user_fingerprint_unique');
            $table->index(['user_id', 'fingerprint'], 'sso_profile_changes_user_fingerprint_index');
        });
    }

    public function down(): void
    {
        $duplicatesExist = DB::table('sso_profile_changes')
            ->select('user_id', 'fingerprint')
            ->groupBy('user_id', 'fingerprint')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicatesExist) {
            throw new \RuntimeException('Cannot restore the unique SSO profile fingerprint index while duplicate rows exist.');
        }

        Schema::table('sso_profile_changes', function (Blueprint $table): void {
            $table->dropIndex('sso_profile_changes_user_fingerprint_index');
            $table->unique(['user_id', 'fingerprint'], 'sso_profile_changes_user_fingerprint_unique');
        });
    }
};

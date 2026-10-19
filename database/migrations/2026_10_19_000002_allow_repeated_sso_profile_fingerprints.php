<?php

use Illuminate\Database\Migrations\Migration;
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
        Schema::table('sso_profile_changes', function (Blueprint $table): void {
            $table->dropIndex('sso_profile_changes_user_fingerprint_index');
            $table->unique(['user_id', 'fingerprint'], 'sso_profile_changes_user_fingerprint_unique');
        });
    }
};

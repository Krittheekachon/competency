<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('idps')->whereNotNull('assessment_round_id')
            ->select('user_id', 'assessment_round_id')->groupBy('user_id', 'assessment_round_id')->havingRaw('COUNT(*) > 1')->get();
        $duplicateItems = DB::table('idp_items')->where('behavior_key', 'faculty-plan')
            ->select('idp_id')->groupBy('idp_id')->havingRaw('COUNT(*) > 1')->get();
        if ($duplicates->isNotEmpty() || $duplicateItems->isNotEmpty()) {
            throw new RuntimeException('Cannot add IDP uniqueness guards: duplicate user/round plans or faculty-plan items exist. Review existing data; no plans were merged or deleted.');
        }
        // Both methods have one parent per person/round. Standard retains many competency items.
        // Legacy NULL-round parents are left untouched rather than guessing their round.
        DB::statement('CREATE UNIQUE INDEX idps_user_round_unique ON idps (user_id, assessment_round_id) WHERE assessment_round_id IS NOT NULL');
        DB::statement("CREATE UNIQUE INDEX idp_items_faculty_plan_unique ON idp_items (idp_id) WHERE behavior_key = 'faculty-plan'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idp_items_faculty_plan_unique');
        DB::statement('DROP INDEX IF EXISTS idps_user_round_unique');
    }
};

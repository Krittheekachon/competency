<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_reviewer_steps', function (Blueprint $table): void {
            $table->index(
                ['reviewer_id', 'chain_type', 'user_id'],
                'reviewer_steps_reviewer_chain_user_idx'
            );
        });

        Schema::table('fc_topic_selections', function (Blueprint $table): void {
            $table->index(
                ['assessment_round_id', 'status', 'user_id', 'submitted_at'],
                'fc_selections_round_status_user_idx'
            );
        });

        Schema::table('idps', function (Blueprint $table): void {
            $table->index(['user_id', 'assessment_round_id', 'id'], 'idps_user_round_id_idx');
        });

        Schema::table('idp_items', function (Blueprint $table): void {
            $table->index(['idp_id', 'status', 'id'], 'idp_items_plan_status_id_idx');
            $table->index(['competency_gap_id', 'id'], 'idp_items_gap_id_idx');
        });

        Schema::table('idp_activities', function (Blueprint $table): void {
            $table->index(['idp_item_id', 'id'], 'idp_activities_item_id_idx');
        });

        Schema::table('idp_activity_updates', function (Blueprint $table): void {
            $table->index(
                ['activity_id', 'status', 'period_start', 'id'],
                'idp_updates_activity_status_period_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('idp_activity_updates', function (Blueprint $table): void {
            $table->dropIndex('idp_updates_activity_status_period_idx');
        });

        Schema::table('idp_activities', function (Blueprint $table): void {
            $table->dropIndex('idp_activities_item_id_idx');
        });

        Schema::table('idp_items', function (Blueprint $table): void {
            $table->dropIndex('idp_items_plan_status_id_idx');
            $table->dropIndex('idp_items_gap_id_idx');
        });

        Schema::table('idps', function (Blueprint $table): void {
            $table->dropIndex('idps_user_round_id_idx');
        });

        Schema::table('fc_topic_selections', function (Blueprint $table): void {
            $table->dropIndex('fc_selections_round_status_user_idx');
        });

        Schema::table('user_reviewer_steps', function (Blueprint $table): void {
            $table->dropIndex('reviewer_steps_reviewer_chain_user_idx');
        });
    }
};

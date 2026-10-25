<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('sso_user_id')->nullable()->index()->after('sso');
            $table->string('sso_workline')->nullable()->after('profile_saved');
            $table->string('sso_workline_type', 30)->nullable()->index()->after('sso_workline');
            $table->string('sso_faculty_name')->nullable()->after('sso_workline_type');
            $table->string('sso_position_name')->nullable()->after('sso_faculty_name');
            $table->string('sso_position_type_name')->nullable()->after('sso_position_name');
            $table->string('sso_level_id')->nullable()->after('sso_position_type_name');
            $table->string('sso_level_name')->nullable()->after('sso_level_id');
            $table->string('sso_person_status')->nullable()->after('sso_level_name');
            $table->string('sso_profile_fingerprint', 64)->nullable()->index()->after('sso_person_status');
            $table->timestamp('sso_profile_synced_at')->nullable()->after('sso_profile_fingerprint');
        });

        Schema::table('assessment_rounds', function (Blueprint $table): void {
            $table->timestamp('closed_at')->nullable()->after('is_active');
            $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('position_titles', function (Blueprint $table): void {
            $table->id();
            $table->string('workline_type', 30)->nullable()->index();
            $table->string('name');
            $table->string('normalized_name');
            $table->string('position_type_name')->nullable();
            $table->string('normalized_position_type_name')->default('');
            $table->string('source', 30)->default('manual');
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['workline_type', 'normalized_position_type_name', 'normalized_name'],
                'position_titles_identity_unique'
            );
        });

        Schema::create('position_title_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('position_title_id')->constrained('position_titles')->cascadeOnDelete();
            $table->string('name');
            $table->string('normalized_name');
            $table->string('source', 30)->default('sso');
            $table->timestamps();

            $table->unique(['position_title_id', 'normalized_name'], 'position_title_alias_unique');
        });

        Schema::table('positions', function (Blueprint $table): void {
            $table->foreignId('position_title_id')
                ->nullable()
                ->after('support_unit_id')
                ->constrained('position_titles')
                ->nullOnDelete();
        });

        Schema::table('levels', function (Blueprint $table): void {
            $table->string('source', 30)->default('manual')->after('expected_level');
            $table->string('sso_level_id')->nullable()->after('source');
        });

        Schema::create('sso_profile_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->json('previous_profile')->nullable();
            $table->json('incoming_profile');
            $table->string('status', 30)->default('pending')->index();
            $table->string('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint'], 'sso_profile_changes_user_fingerprint_unique');
        });

        Schema::create('user_round_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_round_id')->constrained('assessment_rounds')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('level_id')->nullable()->constrained('levels')->nullOnDelete();
            $table->string('workline')->nullable();
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->string('level')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->unique(['assessment_round_id', 'user_id'], 'user_round_profiles_round_user_unique');
        });

        Schema::create('user_round_reviewer_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_round_id')->constrained('assessment_rounds')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('chain_type', 30);
            $table->unsignedSmallInteger('step_order');
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['assessment_round_id', 'user_id', 'chain_type', 'step_order'],
                'user_round_reviewer_steps_order_unique'
            );
            $table->unique(
                ['assessment_round_id', 'user_id', 'chain_type', 'reviewer_id'],
                'user_round_reviewer_steps_reviewer_unique'
            );
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('user_round_reviewer_steps');
        Schema::dropIfExists('user_round_profiles');
        Schema::dropIfExists('sso_profile_changes');

        Schema::table('assessment_rounds', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn('closed_at');
        });

        Schema::table('levels', function (Blueprint $table): void {
            $table->dropColumn(['source', 'sso_level_id']);
        });

        Schema::table('positions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('position_title_id');
        });

        Schema::dropIfExists('position_title_aliases');
        Schema::dropIfExists('position_titles');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'sso_user_id',
                'sso_workline',
                'sso_workline_type',
                'sso_faculty_name',
                'sso_position_name',
                'sso_position_type_name',
                'sso_level_id',
                'sso_level_name',
                'sso_person_status',
                'sso_profile_fingerprint',
                'sso_profile_synced_at',
            ]);
        });
    }
};

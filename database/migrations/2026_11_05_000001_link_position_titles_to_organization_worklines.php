<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('position_titles', function (Blueprint $table): void {
            $table->foreignId('workline_id')->nullable()->constrained('worklines')->restrictOnDelete();
            $table->dropUnique('position_titles_identity_unique');
        });

        // Prefer actual placement IDs. Unused legacy titles use the previous UI's
        // exact canonical name only if that row exists; ambiguous matches stay unbound.
        $aliases = [
            'academic' => ['สายวิชาการ', 'วิชาการ', 'สายงานวิชาการ'],
            'support' => ['สายสนับสนุน', 'สนับสนุน', 'สายงานสนับสนุน'],
            'management' => ['สายงานบริหาร', 'บริหาร', 'สายบริหาร'],
        ];
        foreach (DB::table('position_titles')->orderBy('id')->get() as $title) {
            $ids = DB::table('positions as p')->join('job_families as f', 'p.job_family_id', '=', 'f.id')
                ->where('p.position_title_id', $title->id)->whereNotNull('f.workline_id')
                ->distinct()->pluck('f.workline_id');
            if ($ids->isEmpty() && isset($aliases[$title->workline_type])) {
                $names = $aliases[$title->workline_type];
                $canonical = DB::table('worklines')->where('name', $names[0])->value('id');
                $ids = $canonical ? collect([$canonical]) : DB::table('worklines')->whereIn('name', $names)->pluck('id');
            }
            if ($ids->count() === 1) {
                DB::table('position_titles')->where('id', $title->id)->update(['workline_id' => $ids->first()]);
            }
        }
        DB::statement('CREATE UNIQUE INDEX position_titles_workline_identity_unique ON position_titles (workline_id, normalized_position_type_name, normalized_name) WHERE workline_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX position_titles_unbound_identity_unique ON position_titles (workline_type, normalized_position_type_name, normalized_name) WHERE workline_id IS NULL');
    }

    public function down(): void
    {
        if (DB::table('position_titles')->whereNotNull('workline_type')
            ->groupBy('workline_type', 'normalized_position_type_name', 'normalized_name')
            ->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot remove workline links while catalog identities repeat across worklines; no titles were deleted.');
        }
        DB::statement('DROP INDEX position_titles_workline_identity_unique');
        DB::statement('DROP INDEX position_titles_unbound_identity_unique');
        Schema::table('position_titles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('workline_id');
            $table->unique(['workline_type', 'normalized_position_type_name', 'normalized_name'], 'position_titles_identity_unique');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $positions = DB::table('positions')
                ->join('job_families', 'positions.job_family_id', '=', 'job_families.id')
                ->leftJoin('worklines', 'job_families.workline_id', '=', 'worklines.id')
                ->whereNull('positions.position_title_id')
                ->select('positions.id', 'positions.name', 'worklines.name as workline_name')
                ->orderBy('positions.id')
                ->get();

            foreach ($positions as $position) {
                $normalizedWorkline = Str::lower((string) preg_replace('/\s+/u', '', trim((string) $position->workline_name)));
                $worklineType = match ($normalizedWorkline) {
                    'วิชาการ', 'สายวิชาการ', 'สายงานวิชาการ' => 'academic',
                    'สนับสนุน', 'สายสนับสนุน', 'สายงานสนับสนุน' => 'support',
                    default => null,
                };

                if ($worklineType === null) {
                    continue;
                }
                $normalizedName = Str::lower(Str::squish((string) $position->name));

                $titleId = DB::table('position_titles')
                    ->where('workline_type', $worklineType)
                    ->where('normalized_position_type_name', '')
                    ->where('normalized_name', $normalizedName)
                    ->value('id');

                if (! $titleId) {
                    $titleId = DB::table('position_titles')->insertGetId([
                        'workline_type' => $worklineType,
                        'name' => $position->name,
                        'normalized_name' => $normalizedName,
                        'position_type_name' => null,
                        'normalized_position_type_name' => '',
                        'source' => 'legacy',
                        'first_seen_at' => now(),
                        'last_seen_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('positions')
                    ->where('id', $position->id)
                    ->update([
                        'position_title_id' => $titleId,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $legacyTitleIds = DB::table('position_titles')
                ->where('source', 'legacy')
                ->pluck('id');

            DB::table('positions')
                ->whereIn('position_title_id', $legacyTitleIds)
                ->update(['position_title_id' => null, 'updated_at' => now()]);

            DB::table('position_titles')
                ->whereIn('id', $legacyTitleIds)
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('position_title_aliases')
                        ->whereColumn('position_title_aliases.position_title_id', 'position_titles.id');
                })
                ->delete();
        });
    }
};

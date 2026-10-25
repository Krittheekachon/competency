<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INDEX = 'positions_job_family_name_without_support_unit_unique';

    public function up(): void
    {
        $this->restorePartialUniqueIndex();
    }

    public function down(): void
    {
        // This is a corrective migration; rolling it back must not restore the malformed full index.
        $this->restorePartialUniqueIndex();
    }

    private function restorePartialUniqueIndex(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::INDEX);
        DB::statement(sprintf(
            'CREATE UNIQUE INDEX %s ON positions (job_family_id, name) WHERE support_unit_id IS NULL',
            self::INDEX,
        ));
    }
};

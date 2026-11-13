<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recovery references are now made by the server (REC-2026-00001). A unique index on the generated ones is the backstop
 * behind the sequence lock. It covers only references starting REC-, so references typed by hand before, which may
 * repeat, cannot stop the migration. Safe to run more than once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sys_pm_recoveries') && DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS sys_pm_recoveries_generated_ref_unique ON sys_pm_recoveries (ref_number) WHERE ref_number LIKE 'REC-%'");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS sys_pm_recoveries_generated_ref_unique');
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change properties_ids from INTEGER to TEXT to store comma-separated values
        DB::statement('ALTER TABLE sys_pm_zones_assignments ALTER COLUMN properties_ids TYPE text USING properties_ids::text');

        // Also change fo_ids column for consistency
        DB::statement('ALTER TABLE sys_pm_zones_assignments ALTER COLUMN fo_ids TYPE text USING fo_ids::text');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert if needed
        DB::statement('ALTER TABLE sys_pm_zones_assignments ALTER COLUMN properties_ids TYPE integer USING properties_ids::integer');
        DB::statement('ALTER TABLE sys_pm_zones_assignments ALTER COLUMN fo_ids TYPE integer USING fo_ids::integer');
    }
};

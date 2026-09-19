<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the composite (reference_number, lr_number) unique constraint with a
     * plain unique index on reference_number alone. lr_number is blank on most
     * properties, and Postgres treats NULL as never equal to NULL, so the composite
     * constraint silently allowed duplicate reference_number rows whenever lr_number
     * was empty — which is the common case for newly created properties.
     */
    public function up(): void
    {
        if (!Schema::hasTable('sys_pm_properties')) {
            return;
        }

        DB::statement('ALTER TABLE sys_pm_properties DROP CONSTRAINT IF EXISTS uq_sys_pm_properties_reference_number');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_sys_pm_properties_reference_number_only ON sys_pm_properties (reference_number)');
    }

    /**
     * Restore the original composite unique constraint.
     */
    public function down(): void
    {
        if (!Schema::hasTable('sys_pm_properties')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS uq_sys_pm_properties_reference_number_only');
        DB::statement('ALTER TABLE sys_pm_properties ADD CONSTRAINT uq_sys_pm_properties_reference_number UNIQUE (reference_number, lr_number)');
    }
};

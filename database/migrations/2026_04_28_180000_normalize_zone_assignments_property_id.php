<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing CSV-based property_ids data is intentionally discarded — see
        // refactor brief: rows are recreated one-per-property after this runs.
        DB::table('sys_pm_zones_assignments')->truncate();

        Schema::table('sys_pm_zones_assignments', function (Blueprint $table) {
            $table->dropColumn('properties_ids');
        });

        Schema::table('sys_pm_zones_assignments', function (Blueprint $table) {
            $table->integer('property_id')->after('zone_id');
            $table->index('property_id', 'sys_pm_zones_assignments_property_id_idx');
            $table->index(['staff_id', 'assigned_as', 'zone_id'], 'sys_pm_zones_assignments_group_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sys_pm_zones_assignments', function (Blueprint $table) {
            $table->dropIndex('sys_pm_zones_assignments_group_idx');
            $table->dropIndex('sys_pm_zones_assignments_property_id_idx');
            $table->dropColumn('property_id');
            $table->text('properties_ids')->nullable();
        });
    }
};

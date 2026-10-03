<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sys_set_zones', function (Blueprint $table) {
            // References sys_staff.id directly (zone_manager_id/zone_supervisor_id were
            // originally josya_users ids and got converted to sys_staff ids later — this
            // column is new, so it starts out already in the current, correct shape).
            $table->unsignedBigInteger('zone_auditor_id')->nullable()->after('zone_supervisor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_set_zones', function (Blueprint $table) {
            $table->dropColumn('zone_auditor_id');
        });
    }
};

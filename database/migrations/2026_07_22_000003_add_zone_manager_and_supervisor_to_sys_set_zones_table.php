<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_set_zones', function (Blueprint $table) {
            // References josya_users.id — zone managers/supervisors are picked from system users, not sys_staff.
            $table->unsignedBigInteger('zone_manager_id')->nullable()->after('zone_name');
            $table->unsignedBigInteger('zone_supervisor_id')->nullable()->after('zone_manager_id');
        });
    }

    public function down(): void
    {
        Schema::table('sys_set_zones', function (Blueprint $table) {
            $table->dropColumn(['zone_manager_id', 'zone_supervisor_id']);
        });
    }
};

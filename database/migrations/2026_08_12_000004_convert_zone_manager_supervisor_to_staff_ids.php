<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * sys_set_zones.zone_manager_id/zone_supervisor_id previously stored josya_users
     * ids (System User accounts). They now store sys_staff ids directly, matching
     * Property.zone_manager_id/zone_supervisor_id and the property creation form's
     * own staff-based dropdowns — no more translation hop through a link table that
     * may or may not exist for a given user.
     */
    public function up(): void
    {
        DB::statement("
            UPDATE sys_set_zones
            SET zone_manager_id = (
                SELECT staff_id FROM sys_user_staff_links WHERE user_id = sys_set_zones.zone_manager_id
            )
            WHERE zone_manager_id IS NOT NULL
        ");

        DB::statement("
            UPDATE sys_set_zones
            SET zone_supervisor_id = (
                SELECT staff_id FROM sys_user_staff_links WHERE user_id = sys_set_zones.zone_supervisor_id
            )
            WHERE zone_supervisor_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE sys_set_zones
            SET zone_manager_id = (
                SELECT user_id FROM sys_user_staff_links WHERE staff_id = sys_set_zones.zone_manager_id
            )
            WHERE zone_manager_id IS NOT NULL
        ");

        DB::statement("
            UPDATE sys_set_zones
            SET zone_supervisor_id = (
                SELECT user_id FROM sys_user_staff_links WHERE staff_id = sys_set_zones.zone_supervisor_id
            )
            WHERE zone_supervisor_id IS NOT NULL
        ");
    }
};

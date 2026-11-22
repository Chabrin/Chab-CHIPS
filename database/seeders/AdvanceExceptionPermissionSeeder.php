<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdvanceExceptionPermissionSeeder extends Seeder
{
    /**
     * Create the override_advance_limit permission (who may give an advance as an exception to the limit or to the
     * two-active-advances rule) and grant it to the directors role. More roles can be given it in the role settings.
     * Safe to re-run: the permission is matched by name and the grant by pair.
     */
    public function run(): void
    {
        $now = now();

        $moduleId = DB::table('cb_permissions')->where('name', 'update_client_advances')->value('module_id');

        DB::table('cb_permissions')->upsert([[
            'name' => 'override_advance_limit',
            'display_name' => 'override_advance_limit',
            'module_id' => $moduleId,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['name'], ['display_name', 'module_id', 'updated_at']);

        $permissionId = DB::table('cb_permissions')->where('name', 'override_advance_limit')->value('id');
        $directorRoleId = DB::table('cb_roles')->where('name', 'directors')->value('id');

        if ($permissionId && $directorRoleId) {
            DB::table('cb_permission_role')->upsert([[
                'permission_id' => $permissionId,
                'role_id' => $directorRoleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['permission_id', 'role_id'], ['updated_at']);
        }
    }
}

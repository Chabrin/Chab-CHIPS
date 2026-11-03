<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $moduleId = DB::table('cb_modules')->where('module_name', 'Payroll Mgt')->value('id');
        if (!$moduleId) {
            return;
        }

        $names = [
            'read_payroll_calendar', 'read_payroll_elements', 'read_payroll_processing', 'approve_payroll_processing',
            'read_helb_loans', 'read_prorated_deductions', 'read_taxbands',
        ];
        $now = now();
        foreach ($names as $name) {
            DB::table('cb_permissions')->updateOrInsert(
                ['name' => $name],
                ['display_name' => $name, 'module_id' => $moduleId, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        // Match the project's MissingPermissionsSeeder policy for new permissions.
        $directorRoleId = DB::table('cb_roles')->where('name', 'directors')->value('id');
        if ($directorRoleId) {
            $permissionIds = DB::table('cb_permissions')->whereIn('name', $names)->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('cb_permission_role')->updateOrInsert(
                    ['permission_id' => $permissionId, 'role_id' => $directorRoleId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }

    public function down(): void
    {
        // Keep permission rows and role assignments to avoid revoking grants
        // that administrators may have assigned after this migration ran.
    }
};

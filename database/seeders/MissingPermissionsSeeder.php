<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MissingPermissionsSeeder extends Seeder
{
    /**
     * Create or update the permissions referenced by hasPermission()/@permission() checks in the
     * codebase but missing from cb_permissions, then grant them to the directors role so the
     * checks actually pass. Safe to re-run: matches existing rows by name and never touches id or
     * created_at on an update.
     */
    public function run(): void
    {
        $names = [
            'create_approvals', 'create_cb_system_configs', 'create_companies',
            'create_employee_attendances', 'create_lease_agreements', 'create_lease_escalations',
            'create_properties', 'create_property_inspections', 'create_property_merge',
            'create_repair_requests', 'create_taxbands', 'create_tenant_blacklist',
            'create_tenant_movements', 'create_tenants', 'delete_approvals', 'delete_taxbands',
            'edit_tenant_movements', 'read_activity_logs', 'read_companies', 'read_inspections',
            'read_lease_agreements', 'read_properties', 'read_recoveries', 'read_repair_requests',
            'read_taxbands', 'read_tenants', 'reverse_property_merge', 'update_approvals',
            'update_cb_system_configs', 'update_companies', 'update_properties',
            'update_recoveries', 'update_repair_requests', 'update_taxbands',
            'update_tenant_blacklist', 'update_transactions', 'update_withdrawals',
        ];

        $now = now();

        $permissionRows = array_map(fn (string $name) => [
            'name' => $name,
            'display_name' => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ], $names);

        DB::table('cb_permissions')->upsert($permissionRows, ['name'], ['display_name', 'updated_at']);

        $directorRoleId = DB::table('cb_roles')->where('name', 'directors')->value('id');

        $permissionIds = DB::table('cb_permissions')->whereIn('name', $names)->pluck('id');

        $pivotRows = $permissionIds->map(fn (int $permissionId) => [
            'permission_id' => $permissionId,
            'role_id' => $directorRoleId,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('cb_permission_role')->upsert($pivotRows, ['permission_id', 'role_id'], ['updated_at']);
    }
}

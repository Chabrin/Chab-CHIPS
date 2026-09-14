<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MissingPermissionsSeeder extends Seeder
{
    /**
     * Create or update the permissions referenced by hasPermission()/@permission() checks in the
     * codebase but missing from cb_permissions, then grant them to the directors role so the
     * checks actually pass. Also assigns every one of them a module_id (looked up by module_name,
     * never a raw id) so they render under the right accordion on the role-permissions screen
     * instead of being invisible/orphaned. Safe to re-run: matches existing rows by name and never
     * touches id or created_at on an update.
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
            'update_cb_system_configs', 'update_companies', 'update_inspections',
            'update_lease_agreements', 'update_properties', 'update_recoveries',
            'update_repair_requests', 'update_taxbands', 'update_tenant_blacklist',
            'update_tenant_exit_settlements', 'update_tenant_movements', 'update_tenants',
            'update_transactions', 'update_withdrawals',
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

        $moduleAssignments = [
            // Approval Control — actual workflow-config screens (resources/views/approvals/*)
            'create_approvals' => 'Approval Control',
            'update_approvals' => 'Approval Control',
            'delete_approvals' => 'Approval Control',

            // IT Settings — system-level config/reference data (resources/views/settings/*, companies/*)
            'create_cb_system_configs' => 'IT Settings',
            'update_cb_system_configs' => 'IT Settings',
            'create_companies' => 'IT Settings',
            'read_companies' => 'IT Settings',
            'update_companies' => 'IT Settings',

            // Human Resource — staff/attendance domain
            'create_employee_attendances' => 'Human Resource',

            // Payroll Mgt — same domain as the existing (underscored) tax_bands permissions
            'create_taxbands' => 'Payroll Mgt',
            'read_taxbands' => 'Payroll Mgt',
            'update_taxbands' => 'Payroll Mgt',
            'delete_taxbands' => 'Payroll Mgt',

            // Auditing — audit trail viewing
            'read_activity_logs' => 'Auditing',

            // Client Returns — landlord-account recovery postings, alongside client_advances
            'read_recoveries' => 'Client Returns',
            'update_recoveries' => 'Client Returns',

            // Payments — money-movement confirmation, alongside payment_vouchers/cheques_printing
            'update_transactions' => 'Payments',
            'update_withdrawals' => 'Payments',

            // Management — core property/tenant/lease/repair CRUD, alongside clients/units/
            // service_agreements and this session's tenant_exit_settlements/tenant_movements/
            // inspections/lease_agreements
            'create_lease_agreements' => 'Management',
            'read_lease_agreements' => 'Management',
            'create_lease_escalations' => 'Management',
            'create_properties' => 'Management',
            'read_properties' => 'Management',
            'update_properties' => 'Management',
            'create_property_inspections' => 'Management',
            'read_inspections' => 'Management',
            'create_property_merge' => 'Management',
            'reverse_property_merge' => 'Management',
            'create_repair_requests' => 'Management',
            'read_repair_requests' => 'Management',
            'update_repair_requests' => 'Management',
            'create_tenant_blacklist' => 'Management',
            'update_tenant_blacklist' => 'Management',
            'create_tenant_movements' => 'Management',
            'edit_tenant_movements' => 'Management',
            'create_tenants' => 'Management',
            'read_tenants' => 'Management',
            'update_tenants' => 'Management',
            'update_lease_agreements' => 'Management',
            'update_inspections' => 'Management',
            'update_tenant_movements' => 'Management',
            'update_tenant_exit_settlements' => 'Management',
        ];

        foreach ($moduleAssignments as $permissionName => $moduleName) {
            $moduleId = DB::table('cb_modules')->where('module_name', $moduleName)->value('id');
            DB::table('cb_permissions')->where('name', $permissionName)->update(['module_id' => $moduleId]);
        }
    }
}

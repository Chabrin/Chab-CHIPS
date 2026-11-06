<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ACCOUNTANT_PERMISSIONS = ['read_payroll_processing', 'approve_payroll_processing', 'read_all_payslips'];
    private const ACCOUNTANT_PAGES = ['/payroll/processing', '/payroll/payslips'];

    /**
     * Make the accountant role the only payroll approver and give it the payroll pages it needs.
     * Does nothing when there is no accountant role, so approval is never removed from every role.
     */
    public function up(): void
    {
        $now = now();
        $accountantRoleId = DB::table('cb_roles')->where('name', 'accountant')->value('id');
        $approvePermissionId = DB::table('cb_permissions')->where('name', 'approve_payroll_processing')->value('id');

        if (!$accountantRoleId) {
            return;
        }

        // The accountant is the only payroll approver; other roles process and submit.
        if ($approvePermissionId) {
            DB::table('cb_permission_role')
                ->where('permission_id', $approvePermissionId)
                ->where('role_id', '!=', $accountantRoleId)
                ->delete();
        }

        foreach (DB::table('cb_permissions')->whereIn('name', self::ACCOUNTANT_PERMISSIONS)->pluck('id') as $permissionId) {
            DB::table('cb_permission_role')->updateOrInsert(
                ['permission_id' => $permissionId, 'role_id' => $accountantRoleId],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }

        foreach (DB::table('cb_sub_modules')->whereIn('sub_module_url', self::ACCOUNTANT_PAGES)->pluck('id') as $subModuleId) {
            DB::table('cb_sub_module_permissions')->updateOrInsert(
                ['role_id' => $accountantRoleId, 'sub_module_id' => $subModuleId],
                ['added_on' => $now, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    /**
     * Take the payroll grants back from the accountant and give approval back to the directors.
     */
    public function down(): void
    {
        $accountantRoleId = DB::table('cb_roles')->where('name', 'accountant')->value('id');
        $directorRoleId = DB::table('cb_roles')->where('name', 'directors')->value('id');
        $approvePermissionId = DB::table('cb_permissions')->where('name', 'approve_payroll_processing')->value('id');

        if ($accountantRoleId) {
            DB::table('cb_permission_role')
                ->where('role_id', $accountantRoleId)
                ->whereIn('permission_id', DB::table('cb_permissions')->whereIn('name', self::ACCOUNTANT_PERMISSIONS)->select('id'))
                ->delete();
            DB::table('cb_sub_module_permissions')
                ->where('role_id', $accountantRoleId)
                ->whereIn('sub_module_id', DB::table('cb_sub_modules')->whereIn('sub_module_url', self::ACCOUNTANT_PAGES)->select('id'))
                ->delete();
        }

        if ($directorRoleId && $approvePermissionId) {
            DB::table('cb_permission_role')->updateOrInsert(
                ['permission_id' => $approvePermissionId, 'role_id' => $directorRoleId],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Payroll permissions HR needs to run payroll. Only names that already exist are granted. */
    private const HR_PERMISSIONS = [
        'read_payroll_calendar', 'create_payroll_calendar', 'update_payroll_calendar', 'delete_payroll_calendar',
        'read_payroll_elements', 'create_payroll_elements', 'update_payroll_elements',
        'read_payroll_processing', 'create_payroll_processing',
        'read_helb_loans', 'create_helb_loans', 'update_helb_loans', 'delete_helb_loans',
        'read_prorated_deductions', 'create_prorated_deductions', 'update_prorated_deductions',
        'read_taxbands', 'read_all_payslips',
    ];

    public function up(): void
    {
        // Who processed / approved each payroll run (maker-checker).
        if (Schema::hasTable('sys_hr_payroll_summary')) {
            Schema::table('sys_hr_payroll_summary', function (Blueprint $table) {
                if (!Schema::hasColumn('sys_hr_payroll_summary', 'processed_by')) {
                    $table->unsignedBigInteger('processed_by')->nullable();
                }
                if (!Schema::hasColumn('sys_hr_payroll_summary', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable();
                }
                if (!Schema::hasColumn('sys_hr_payroll_summary', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable();
                }
            });
        }

        // Link deduction repeats to the payroll period that created them so a rejected run can be reversed.
        foreach (['sys_helb_deductions_250_repeat', 'sys_hr_employee_amortized_deductions_252_repeat'] as $repeatTable) {
            if (Schema::hasTable($repeatTable) && !Schema::hasColumn($repeatTable, 'period_id')) {
                Schema::table($repeatTable, function (Blueprint $table) {
                    $table->unsignedBigInteger('period_id')->nullable();
                });
            }
        }

        $this->grantPermissions();
    }

    /**
     * Create the approve permission if missing and grant the HR and director roles their payroll permissions.
     */
    private function grantPermissions(): void
    {
        $moduleId = DB::table('cb_modules')->where('module_name', 'Payroll Mgt')->value('id');
        if (!$moduleId) {
            return;
        }

        $now = now();
        DB::table('cb_permissions')->updateOrInsert(
            ['name' => 'approve_payroll_processing'],
            ['display_name' => 'approve_payroll_processing', 'module_id' => $moduleId, 'created_at' => $now, 'updated_at' => $now]
        );

        // HR runs payroll; directors can see every payslip. The accountant approves.
        $grants = [
            'hr_manager' => self::HR_PERMISSIONS,
            'directors' => ['read_all_payslips'],
        ];

        foreach ($grants as $roleName => $permissionNames) {
            $roleId = DB::table('cb_roles')->where('name', $roleName)->value('id');
            if (!$roleId) {
                continue;
            }
            $permissionIds = DB::table('cb_permissions')->whereIn('name', $permissionNames)->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('cb_permission_role')->updateOrInsert(
                    ['permission_id' => $permissionId, 'role_id' => $roleId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }

    public function down(): void
    {
        // Keep columns and grants: payroll runs may already reference them and administrators may have adjusted roles.
    }
};

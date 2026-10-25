<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $payrollModuleId = DB::table('cb_modules')->where('module_name', 'Payroll Mgt')->value('id');

        if (!$payrollModuleId) {
            return;
        }

        $exists = DB::table('cb_permissions')
            ->where('module_id', $payrollModuleId)
            ->where('name', 'read_all_payslips')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('cb_permissions')->insert([
            'name' => 'read_all_payslips',
            'display_name' => 'read_all_payslips',
            // read_payslips only gates whether the /payroll/payslips page's search
            // and action buttons are shown at all; this is the separate, stricter
            // permission PayslipsController checks before actually viewing/emailing/
            // downloading a payslip — distinct on purpose, so page access and
            // payslip-data access can be assigned separately.
            'description' => "View, email, and download any employee's payslip — separate from read_payslips, which only grants access to the payslips page itself.",
            'module_id' => $payrollModuleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('cb_permissions')->where('name', 'read_all_payslips')->delete();
    }
};

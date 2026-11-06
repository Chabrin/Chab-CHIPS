<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rename SHIF to SHA in the payroll elements and menu, and give the NSSF element its key.
     */
    public function up(): void
    {
        DB::table('sys_hr_payroll_elements')->where('element_key', 'nhif')->update(['element_name' => 'SHA']);
        DB::table('cb_sub_modules')->where('sub_module_url', '/hrsettings/shif/rates')->update(['sub_module_name' => 'SHA Rates']);

        // Without this key NSSF on a contract is deducted on top of the calculated NSSF.
        DB::table('sys_hr_payroll_elements')
            ->where('element_name', 'NSSF')
            ->whereNull('element_key')
            ->update(['element_key' => 'nssf']);
    }

    /**
     * Put the old SHIF/NHIF names back and clear the NSSF element key.
     */
    public function down(): void
    {
        DB::table('sys_hr_payroll_elements')->where('element_key', 'nhif')->update(['element_name' => 'NHIF']);
        DB::table('cb_sub_modules')->where('sub_module_url', '/hrsettings/shif/rates')->update(['sub_module_name' => 'SHIF Rates']);
        DB::table('sys_hr_payroll_elements')->where('element_key', 'nssf')->update(['element_key' => null]);
    }
};

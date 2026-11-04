<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add the "Monthly Payroll Items" page to the Payroll menu. The menu lives in the database, so this runs on every
 * server. The new entry goes to the roles that already see Payroll Processing, so no access is widened. Safe to run again.
 */
return new class extends Migration
{
    private const URL = '/payroll/monthly-items';

    public function up(): void
    {
        $processing = DB::table('cb_sub_modules')->where('sub_module_url', '/payroll/processing')->first();
        if (!$processing) {
            return;
        }

        $now = now();

        $subModuleId = DB::table('cb_sub_modules')->where('sub_module_url', self::URL)->value('id');
        if (!$subModuleId) {
            $subModuleId = DB::table('cb_sub_modules')->insertGetId([
                'sub_module_name' => 'Monthly Payroll Items',
                'sub_module_url' => self::URL,
                'icon' => null,
                'order' => (int) DB::table('cb_sub_modules')->where('module_id', $processing->module_id)->max('order') + 1,
                'added_by' => 'migration',
                'added_on' => $now,
                'module_id' => $processing->module_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (DB::table('cb_sub_module_permissions')->where('sub_module_id', $processing->id)->pluck('role_id') as $roleId) {
            DB::table('cb_sub_module_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'sub_module_id' => $subModuleId],
                ['added_on' => $now, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        $subModuleId = DB::table('cb_sub_modules')->where('sub_module_url', self::URL)->value('id');
        if ($subModuleId) {
            DB::table('cb_sub_module_permissions')->where('sub_module_id', $subModuleId)->delete();
            DB::table('cb_sub_modules')->where('id', $subModuleId)->delete();
        }
    }
};

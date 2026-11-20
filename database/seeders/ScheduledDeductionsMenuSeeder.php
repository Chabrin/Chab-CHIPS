<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Adds "Scheduled Deductions" to the Payroll menu, between HELB Loans and Prorated Deductions, and the permissions for it.
 *
 * NOT part of DatabaseSeeder. Run it once on each database, after the migration 2026_11_20_100000, with:
 *     php artisan db:seed --class=ScheduledDeductionsMenuSeeder
 *
 * The menu entry and every permission below go to the HR Manager ("hr_manager") and Directors ("directors") roles, and nobody else. Changing
 * who can do what is done afterwards on the roles screen. Safe to run again: it only adds what is missing, and moves the later menu entries
 * down by one place just once (when it first adds the entry).
 */
class ScheduledDeductionsMenuSeeder extends Seeder
{
    private const URL = '/payroll/scheduled-deductions';

    private const ROLES = ['hr_manager', 'directors'];

    /** Permission name => what it allows. The names are what the screens check. */
    public const PERMISSIONS = [
        'read_scheduled_deductions' => 'See scheduled deductions and open the signed consent forms',
        'create_scheduled_deductions' => 'Add a scheduled deduction',
        'update_scheduled_deductions' => 'Add the consent form, pause, resume and change the monthly amount',
        'close_scheduled_deductions' => 'Close a scheduled deduction early',
        'manage_deduction_types' => 'Add and hide types of scheduled deduction',
        'override_deduction_limits' => 'Run a payroll that goes over the legal limit on deductions, with a written reason',
    ];

    public function run(): void
    {
        $now = now();
        $log = [];

        DB::transaction(function () use ($now, &$log) {
            $moduleId = (int) DB::table('cb_sub_modules')->where('sub_module_url', '/payroll/helbloans')->value('module_id')
                ?: (int) DB::table('cb_modules')->where('module_name', 'Payroll Mgt')->value('id');
            if (!$moduleId) {
                $log[] = 'The Payroll module was not found, so nothing was added.';

                return;
            }

            $subModuleId = DB::table('cb_sub_modules')->where('sub_module_url', self::URL)->value('id');
            if (!$subModuleId) {
                // Between HELB Loans and Prorated Deductions: take the place after HELB and move everything after it down one
                $helbOrder = DB::table('cb_sub_modules')->where('sub_module_url', '/payroll/helbloans')->value('order');
                $order = $helbOrder !== null ? (int) $helbOrder + 1 : ((int) DB::table('cb_sub_modules')->where('module_id', $moduleId)->max('order') + 1);
                if ($helbOrder !== null) {
                    DB::table('cb_sub_modules')->where('module_id', $moduleId)->where('order', '>=', $order)->increment('order');
                }
                $subModuleId = DB::table('cb_sub_modules')->insertGetId([
                    'sub_module_name' => 'Scheduled Deductions',
                    'sub_module_url' => self::URL,
                    'icon' => null,
                    'order' => $order,
                    'added_by' => 'system',
                    'added_on' => $now,
                    'module_id' => $moduleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $log[] = "menu entry added: Scheduled Deductions (place {$order})";
            } else {
                $log[] = 'menu entry exists: Scheduled Deductions';
            }

            $permissionIds = [];
            foreach (self::PERMISSIONS as $name => $description) {
                DB::table('cb_permissions')->upsert(
                    [['name' => $name, 'display_name' => $name, 'description' => $description, 'module_id' => $moduleId, 'created_at' => $now, 'updated_at' => $now]],
                    ['name'],
                    ['description', 'module_id', 'updated_at']
                );
                $permissionIds[$name] = (int) DB::table('cb_permissions')->where('name', $name)->value('id');
            }

            foreach (self::ROLES as $roleName) {
                $roleId = DB::table('cb_roles')->where('name', $roleName)->value('id');
                if (!$roleId) {
                    $log[] = "role not found, skipped: {$roleName}";

                    continue;
                }

                $menuPair = ['role_id' => $roleId, 'sub_module_id' => $subModuleId];
                if (!DB::table('cb_sub_module_permissions')->where($menuPair)->exists()) {
                    DB::table('cb_sub_module_permissions')->insert($menuPair + ['added_by' => 'system', 'added_on' => $now, 'created_at' => $now, 'updated_at' => $now]);
                    $log[] = "  menu entry given to {$roleName}";
                }

                foreach ($permissionIds as $name => $permissionId) {
                    $pair = ['permission_id' => $permissionId, 'role_id' => $roleId];
                    if (!DB::table('cb_permission_role')->where($pair)->exists()) {
                        DB::table('cb_permission_role')->insert($pair + ['created_at' => $now, 'updated_at' => $now]);
                        $log[] = "  {$name} given to {$roleName}";
                    }
                }
            }
        });

        foreach ($log as $line) {
            $this->command?->line($line);
        }
    }
}

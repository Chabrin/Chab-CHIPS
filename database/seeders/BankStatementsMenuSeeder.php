<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankStatementsMenuSeeder extends Seeder
{
    /**
     * Add "Bank Statements" (/tenant/bank-statements) under Tenant Trans and give it to the roles that can already
     * open Tenant Payments. Also creates the update_bank_statements permission (who may change the confirming roles on
     * that page) and grants it to the directors role. Safe to re-run: the sub-module is matched by URL, the permission
     * by name and the grants by pair.
     */
    public function run(): void
    {
        $now = now();

        $moduleId = DB::table('cb_modules')->where('module_name', 'Tenant Trans')->value('id');
        if (!$moduleId) {
            return;
        }

        $existing = DB::table('cb_sub_modules')->where('sub_module_url', '/tenant/bank-statements')->first();

        DB::table('cb_sub_modules')->updateOrInsert(
            ['sub_module_url' => '/tenant/bank-statements'],
            [
                'sub_module_name' => 'Bank Statements',
                'icon' => 'bank',
                'order' => $existing->order ?? (int) DB::table('cb_sub_modules')->where('module_id', $moduleId)->max('order') + 1,
                'added_by' => 'amos',
                'added_on' => $now,
                'module_id' => $moduleId,
                'created_at' => $existing->created_at ?? $now,
                'updated_at' => $now,
            ]
        );

        $subModuleId = DB::table('cb_sub_modules')->where('sub_module_url', '/tenant/bank-statements')->value('id');
        $paymentsId = DB::table('cb_sub_modules')->where('sub_module_url', '/tenant/payments')->value('id');

        foreach (DB::table('cb_sub_module_permissions')->where('sub_module_id', $paymentsId)->pluck('role_id') as $roleId) {
            $pair = ['role_id' => $roleId, 'sub_module_id' => $subModuleId];
            if (!DB::table('cb_sub_module_permissions')->where($pair)->exists()) {
                DB::table('cb_sub_module_permissions')->insert($pair + ['added_on' => $now, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        DB::table('cb_permissions')->upsert([[
            'name' => 'update_bank_statements',
            'display_name' => 'update_bank_statements',
            'module_id' => $moduleId,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['name'], ['display_name', 'module_id', 'updated_at']);

        $permissionId = DB::table('cb_permissions')->where('name', 'update_bank_statements')->value('id');
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

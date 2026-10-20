<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GlBalancesMenuSeeder extends Seeder
{
    /**
     * Add "Account Balances" (/ledger/balances) under the General Ledger menu and give it to the
     * roles that can already open Ledger Accounts. Safe to re-run: the sub-module is matched by
     * URL and the permissions by role/sub-module pair.
     */
    public function run(): void
    {
        $now = now();

        $moduleId = DB::table('cb_modules')->where('module_name', 'General Ledger')->value('id');
        if (!$moduleId) {
            return;
        }

        // A row that is already there keeps its place in the menu and its created date
        $existing = DB::table('cb_sub_modules')->where('sub_module_url', '/ledger/balances')->first();

        DB::table('cb_sub_modules')->updateOrInsert(
            ['sub_module_url' => '/ledger/balances'],
            [
                'sub_module_name' => 'Account Balances',
                'icon' => 'balance',
                'order' => $existing->order ?? (int) DB::table('cb_sub_modules')->where('module_id', $moduleId)->max('order') + 1,
                'added_by' => 'amos',
                'added_on' => $now,
                'module_id' => $moduleId,
                'created_at' => $existing->created_at ?? $now,
                'updated_at' => $now,
            ]
        );

        $subModuleId = DB::table('cb_sub_modules')->where('sub_module_url', '/ledger/balances')->value('id');
        $accountsId = DB::table('cb_sub_modules')->where('sub_module_url', '/ledger/accounts')->value('id');

        $roleIds = DB::table('cb_sub_module_permissions')
            ->where('sub_module_id', $accountsId)
            ->pluck('role_id');

        foreach ($roleIds as $roleId) {
            $pair = ['role_id' => $roleId, 'sub_module_id' => $subModuleId];
            if (!DB::table('cb_sub_module_permissions')->where($pair)->exists()) {
                DB::table('cb_sub_module_permissions')->insert($pair + ['added_on' => $now, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }
}

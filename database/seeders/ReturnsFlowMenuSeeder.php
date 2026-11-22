<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReturnsFlowMenuSeeder extends Seeder
{
    /**
     * Roles that watch the returns flow without acting on it, besides the roles that already open Processed Returns.
     */
    const WATCHERS = ['accountant', 'assistant_accountant', 'internal_auditor', 'assistant_internal_auditor', 'directors', 'zone_manager'];

    /**
     * Add "Returns Flow" (/returns/flow) under Client Returns, a view-only page of where every return is in its flow.
     * It is given to the roles that can already open Processed Returns plus the accounts and audit roles in WATCHERS.
     * Safe to re-run: the sub-module is matched by URL and the grants by pair.
     */
    public function run(): void
    {
        $now = now();

        $processed = DB::table('cb_sub_modules')->where('sub_module_url', '/processed/returns')->first();
        $moduleId = $processed->module_id ?? DB::table('cb_modules')->where('module_name', 'Client Returns')->value('id');
        if (!$moduleId) {
            return;
        }

        $existing = DB::table('cb_sub_modules')->where('sub_module_url', '/returns/flow')->first();

        DB::table('cb_sub_modules')->updateOrInsert(
            ['sub_module_url' => '/returns/flow'],
            [
                'sub_module_name' => 'Returns Flow',
                'icon' => $processed->icon ?? 'file',
                'order' => $existing->order ?? (int) DB::table('cb_sub_modules')->where('module_id', $moduleId)->max('order') + 1,
                'added_by' => 'amos',
                'added_on' => $now,
                'module_id' => $moduleId,
                'created_at' => $existing->created_at ?? $now,
                'updated_at' => $now,
            ]
        );

        $subModuleId = DB::table('cb_sub_modules')->where('sub_module_url', '/returns/flow')->value('id');

        $roleIds = DB::table('cb_roles')->whereIn('name', self::WATCHERS)->pluck('id');
        if ($processed) {
            $roleIds = $roleIds->merge(DB::table('cb_sub_module_permissions')->where('sub_module_id', $processed->id)->pluck('role_id'))->unique();
        }

        foreach ($roleIds as $roleId) {
            $pair = ['role_id' => $roleId, 'sub_module_id' => $subModuleId];
            if (!DB::table('cb_sub_module_permissions')->where($pair)->exists()) {
                DB::table('cb_sub_module_permissions')->insert($pair + ['added_on' => $now, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }
}

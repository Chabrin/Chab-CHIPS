<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommunicationsMenuSeeder extends Seeder
{
    /**
     * Add Communications as its own top-level menu group (a super module, beside Management and
     * Accounting) holding one "Email & SMS" module with its four pages, and give them to the
     * Directors role. Safe to re-run: the group and module are matched by name, the sub-modules by
     * URL and the permissions by role/sub-module pair. A module left under Management by an earlier
     * run of this seeder (it was then named "Communications") is moved into the new group.
     */
    public function run(): void {
        $now = now();

        $superModuleId = DB::table('cb_super_modules')->where('name', 'Communications')->value('id');
        if (!$superModuleId) {
            $superModuleId = DB::table('cb_super_modules')->insertGetId([
                'name' => 'Communications',
                'icon' => 'campaign',
                'order' => (int) DB::table('cb_super_modules')->max('order') + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $moduleId = DB::table('cb_modules')->whereIn('module_name', ['Email & SMS', 'Communications'])->orderBy('id')->value('id');

        if ($moduleId) {
            DB::table('cb_modules')->where('id', $moduleId)->update(['module_name' => 'Email & SMS', 'icon' => 'campaign', 'super_module_id' => $superModuleId, 'updated_at' => $now]);
        } else {
            $moduleId = DB::table('cb_modules')->insertGetId([
                'module_name' => 'Email & SMS',
                'icon' => 'campaign',
                'module_order' => (int) DB::table('cb_modules')->max('module_order') + 1,
                'added_by' => 'amos',
                'added_on' => $now,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted' => 0,
                'super_module_id' => $superModuleId,
            ]);
        }

        $pages = [
            ['url' => '/communications', 'name' => 'Sent Batches', 'icon' => 'outbox'],
            ['url' => '/communications/compose', 'name' => 'Compose', 'icon' => 'edit_note'],
            ['url' => '/communications/logs', 'name' => 'Logs', 'icon' => 'receipt_long'],
            ['url' => '/communications/metrics', 'name' => 'Metrics', 'icon' => 'monitoring'],
        ];

        foreach ($pages as $index => $page) {
            $existing = DB::table('cb_sub_modules')->where('sub_module_url', $page['url'])->first();

            DB::table('cb_sub_modules')->updateOrInsert(
                ['sub_module_url' => $page['url']],
                [
                    'sub_module_name' => $page['name'],
                    'icon' => $page['icon'],
                    'order' => $existing->order ?? $index + 1,
                    'added_by' => 'amos',
                    'added_on' => $now,
                    'module_id' => $moduleId,
                    'created_at' => $existing->created_at ?? $now,
                    'updated_at' => $now,
                ]
            );
        }

        // Grant the pages to the Directors role only. A bulk send reaches thousands of tenants with no
        // approval step, so other roles are given it by hand on the roles screen. The role is found by
        // name because its id differs between databases.
        $roleIds = DB::table('cb_roles')->where('name', 'directors')->pluck('id');
        $subModuleIds = DB::table('cb_sub_modules')->whereIn('sub_module_url', array_column($pages, 'url'))->pluck('id');

        foreach ($subModuleIds as $subModuleId) {
            foreach ($roleIds as $roleId) {
                $pair = ['role_id' => $roleId, 'sub_module_id' => $subModuleId];
                if (!DB::table('cb_sub_module_permissions')->where($pair)->exists()) {
                    DB::table('cb_sub_module_permissions')->insert($pair + ['added_on' => $now, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }
    }
}

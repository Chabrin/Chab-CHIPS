<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApproveLeasesMenuSeeder extends Seeder
{
    /**
     * Add "Approve Leases" (/approvals/leases) under the Approval Control menu and give it to the
     * same roles that can already open Property Confirmations. Safe to re-run: the sub-module
     * is matched by URL and the permissions by role/sub-module pair.
     */
    public function run(): void
    {
        $now = now();

        $moduleId = DB::table('cb_modules')->where('module_name', 'Approval Control')->value('id');
        if (!$moduleId) {
            return;
        }

        DB::table('cb_sub_modules')->updateOrInsert(
            ['sub_module_url' => '/approvals/leases'],
            [
                'sub_module_name' => 'Approve Leases',
                'icon' => 'real_estate_agent',
                'order' => (int) DB::table('cb_sub_modules')->where('module_id', $moduleId)->max('order') + 1,
                'added_by' => 'amos',
                'added_on' => $now,
                'module_id' => $moduleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $subModuleId = DB::table('cb_sub_modules')->where('sub_module_url', '/approvals/leases')->value('id');
        $confirmationsId = DB::table('cb_sub_modules')->where('sub_module_url', '/property/confirmations')->value('id');

        $roleIds = DB::table('cb_sub_module_permissions')
            ->where('sub_module_id', $confirmationsId)
            ->pluck('role_id');

        foreach ($roleIds as $roleId) {
            DB::table('cb_sub_module_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'sub_module_id' => $subModuleId],
                ['added_on' => $now, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }
}

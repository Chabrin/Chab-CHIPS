<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantExitMenuSeeder extends Seeder
{
    /**
     * Wire /tenant-exit into the sidebar menu and grant access to it. The route existed with
     * no cb_sub_modules row at all, so it had no menu entry and AccessChecker was silently
     * failing open for it (no defined sub-module means every authenticated user passes,
     * regardless of role). This adds it under the Management module — the same module the
     * update_tenant_exit_settlements Laratrust permission already uses for this feature — and
     * grants field_officer, senior_field_officer, property_manager and directors access.
     * Safe to re-run: matches the sub-module by URL and the permissions by role/sub-module pair.
     */
    public function run(): void
    {
        $now = now();

        $moduleId = DB::table('cb_modules')->where('module_name', 'Management')->value('id');

        DB::table('cb_sub_modules')->updateOrInsert(
            ['sub_module_url' => '/tenant-exit'],
            [
                'sub_module_name' => 'Tenant Exit',
                'order' => 10,
                'added_by' => 'amos',
                'added_on' => $now,
                'module_id' => $moduleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $subModuleId = DB::table('cb_sub_modules')->where('sub_module_url', '/tenant-exit')->value('id');

        $roleIds = DB::table('cb_roles')
            ->whereIn('name', ['field_officer', 'senior_field_officer', 'property_manager', 'directors'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('cb_sub_module_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'sub_module_id' => $subModuleId],
                ['added_on' => $now, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }
}

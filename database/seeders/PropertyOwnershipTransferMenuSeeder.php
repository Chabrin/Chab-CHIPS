<?php

namespace Database\Seeders;

use App\Services\PropertyTransferAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PropertyOwnershipTransferMenuSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $module = DB::table('cb_modules')->where('module_name', 'Property Actions')->value('id');
            if (! $module) {
                throw new \RuntimeException('Create the Property Actions module before registering ownership transfers.');
            }
            $page = DB::table('cb_sub_modules')->where('sub_module_url', PropertyTransferAccess::PAGE)->value('id');
            if (! $page) {
                $page = DB::table('cb_sub_modules')->insertGetId([
                    'sub_module_name' => 'Transfer Ownership', 'sub_module_url' => PropertyTransferAccess::PAGE,
                    'module_id' => $module, 'icon' => 'swap_horiz',
                    'order' => (int) DB::table('cb_sub_modules')->where('module_id', $module)->max('order') + 1,
                    'added_by' => 'seeder', 'added_on' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $permissions = [];
            foreach (['view', 'create', 'review', 'approve', 'execute'] as $action) {
                $name = $action.'_property_transfers';
                $id = DB::table('cb_permissions')->where('name', $name)->value('id');
                if (! $id) {
                    $id = DB::table('cb_permissions')->insertGetId(['name' => $name, 'display_name' => $name,
                        'description' => ucfirst($action).' property ownership transfers within the existing property scope.',
                        'module_id' => $module, 'created_at' => now(), 'updated_at' => now()]);
                }
                $permissions[$action] = $id;
            }
            foreach (DB::table('cb_roles')->get(['id', 'name', 'display_name']) as $role) {
                $names = array_map(fn ($name) => strtolower(trim((string) $name)), [$role->name, $role->display_name ?? '']);
                $director = count(array_intersect($names, ['directors', 'director'])) > 0;
                $finance = count(array_intersect($names, ['accounts', 'accountant', 'finance manager'])) > 0;
                if (! $director && ! $finance) {
                    continue;
                }
                if (! DB::table('cb_sub_module_permissions')->where('role_id', $role->id)->where('sub_module_id', $page)->exists()) {
                    DB::table('cb_sub_module_permissions')->insert(['role_id' => $role->id, 'sub_module_id' => $page, 'added_by' => 'seeder', 'added_on' => now()]);
                }
                foreach ($director ? ['view', 'create', 'review', 'approve', 'execute'] : ['view', 'create', 'review'] as $action) {
                    DB::table('cb_permission_role')->updateOrInsert(['role_id' => $role->id, 'permission_id' => $permissions[$action]], []);
                }
            }
        });
    }
}

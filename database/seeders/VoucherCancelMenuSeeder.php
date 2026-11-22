<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VoucherCancelMenuSeeder extends Seeder
{
    /**
     * Add "Cancelled Cheques" (/cheque/cancelled) next to "Print Cheques" under Payments, give it to the roles that can
     * already open Print Cheques, and create the cancel_payment_vouchers permission (who may cancel a payment voucher),
     * granted to the directors role. More roles can be given the permission in the role settings. Safe to re-run: the
     * sub-module is matched by URL, the permission by name and the grants by pair.
     */
    public function run(): void
    {
        $now = now();

        $printing = DB::table('cb_sub_modules')->where('sub_module_url', '/cheque/printing')->first();
        $moduleId = $printing->module_id ?? DB::table('cb_modules')->where('module_name', 'Payments')->value('id');
        if (!$moduleId) {
            return;
        }

        $existing = DB::table('cb_sub_modules')->where('sub_module_url', '/cheque/cancelled')->first();

        DB::table('cb_sub_modules')->updateOrInsert(
            ['sub_module_url' => '/cheque/cancelled'],
            [
                'sub_module_name' => 'Cancelled Cheques',
                'icon' => $printing->icon ?? 'file',
                'order' => $existing->order ?? (int) DB::table('cb_sub_modules')->where('module_id', $moduleId)->max('order') + 1,
                'added_by' => 'amos',
                'added_on' => $now,
                'module_id' => $moduleId,
                'created_at' => $existing->created_at ?? $now,
                'updated_at' => $now,
            ]
        );

        $subModuleId = DB::table('cb_sub_modules')->where('sub_module_url', '/cheque/cancelled')->value('id');

        if ($printing) {
            foreach (DB::table('cb_sub_module_permissions')->where('sub_module_id', $printing->id)->pluck('role_id') as $roleId) {
                $pair = ['role_id' => $roleId, 'sub_module_id' => $subModuleId];
                if (!DB::table('cb_sub_module_permissions')->where($pair)->exists()) {
                    DB::table('cb_sub_module_permissions')->insert($pair + ['added_on' => $now, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }

        DB::table('cb_permissions')->upsert([[
            'name' => 'cancel_payment_vouchers',
            'display_name' => 'cancel_payment_vouchers',
            'module_id' => $moduleId,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['name'], ['display_name', 'module_id', 'updated_at']);

        $permissionId = DB::table('cb_permissions')->where('name', 'cancel_payment_vouchers')->value('id');
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $superModuleId = DB::table('cb_super_modules')->where('name', 'Reports')->value('id');
        if (!$superModuleId) {
            return;
        }

        $moduleId = DB::table('cb_modules')->where('module_name', 'Reports')->where('super_module_id', $superModuleId)->value('id');
        if (!$moduleId) {
            $nextOrder = (int) DB::table('cb_modules')->max('module_order') + 1;
            $moduleId = DB::table('cb_modules')->insertGetId([
                'module_name' => 'Reports',
                'module_url' => '',
                'icon' => 'analytics',
                'module_order' => $nextOrder,
                'added_by' => 'system',
                'added_on' => now(),
                'created_at' => now(),
                'updated_at' => now(),
                'super_module_id' => $superModuleId,
                'deleted' => 0,
            ]);
        }

        $subModuleId = DB::table('cb_sub_modules')->where('sub_module_url', '/reports')->value('id');
        if (!$subModuleId) {
            $nextSubOrder = (int) DB::table('cb_sub_modules')->where('module_id', $moduleId)->max('order') + 1;
            $subModuleId = DB::table('cb_sub_modules')->insertGetId([
                'sub_module_name' => 'Reports & Analytics',
                'sub_module_url' => '/reports',
                'icon' => '',
                'order' => $nextSubOrder,
                'added_by' => 'system',
                'added_on' => now(),
                'module_id' => $moduleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $roleId = DB::table('cb_roles')->where('name', 'directors')->value('id');
        if ($roleId) {
            $exists = DB::table('cb_sub_module_permissions')
                ->where('role_id', $roleId)
                ->where('sub_module_id', $subModuleId)
                ->exists();
            if (!$exists) {
                DB::table('cb_sub_module_permissions')->insert([
                    'role_id' => $roleId,
                    'sub_module_id' => $subModuleId,
                    'added_by' => 'system',
                    'added_on' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $subModuleId = DB::table('cb_sub_modules')->where('sub_module_url', '/reports')->value('id');
        if ($subModuleId) {
            DB::table('cb_sub_module_permissions')->where('sub_module_id', $subModuleId)->delete();
            DB::table('cb_sub_modules')->where('id', $subModuleId)->delete();
        }

        $superModuleId = DB::table('cb_super_modules')->where('name', 'Reports')->value('id');
        if ($superModuleId) {
            DB::table('cb_modules')->where('module_name', 'Reports')->where('super_module_id', $superModuleId)->delete();
        }
    }
};

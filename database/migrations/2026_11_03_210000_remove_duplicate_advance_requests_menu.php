<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Client Trans had two menu entries, "Advance Requests" and "Advances", both opening /client/advances. Keep "Advances":
 * give it any role that only had "Advance Requests", then remove the duplicate and its role links. Safe to run more than once.
 */
return new class extends Migration
{
    public function up(): void
    {
        $keep = DB::table('cb_sub_modules')->where('sub_module_url', '/client/advances')->where('sub_module_name', 'Advances')->value('id');
        $duplicate = DB::table('cb_sub_modules')->where('sub_module_url', '/client/advances')->where('sub_module_name', 'Advance Requests')->value('id');

        if (!$keep || !$duplicate) {
            return;
        }

        $now = now();

        foreach (DB::table('cb_sub_module_permissions')->where('sub_module_id', $duplicate)->pluck('role_id') as $roleId) {
            DB::table('cb_sub_module_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'sub_module_id' => $keep],
                ['added_on' => $now, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        DB::table('cb_sub_module_permissions')->where('sub_module_id', $duplicate)->delete();
        DB::table('cb_sub_modules')->where('id', $duplicate)->delete();
    }

    public function down(): void
    {
        // Not reversed: the duplicate entry only pointed at the same page.
    }
};

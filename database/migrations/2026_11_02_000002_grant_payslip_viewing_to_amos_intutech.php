<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $userId = DB::table('josya_users')->where('username', 'amos.intutech')->value('id');
        $permissionId = DB::table('cb_permissions')->where('name', 'read_all_payslips')->value('id');

        // The account already receives read_payslips from its directors role;
        // this direct grant unlocks employee search and payslip view/email/download.
        if (!$userId || !$permissionId) {
            return;
        }

        DB::table('cb_permission_user')->updateOrInsert(
            ['permission_id' => $permissionId, 'user_id' => $userId, 'user_type' => 'App\\Models\\User'],
            []
        );
    }

    public function down(): void
    {
        $userId = DB::table('josya_users')->where('username', 'amos.intutech')->value('id');
        $permissionId = DB::table('cb_permissions')->where('name', 'read_all_payslips')->value('id');

        if ($userId && $permissionId) {
            DB::table('cb_permission_user')
                ->where('user_id', $userId)
                ->where('permission_id', $permissionId)
                ->where('user_type', 'App\\Models\\User')
                ->delete();
        }
    }
};

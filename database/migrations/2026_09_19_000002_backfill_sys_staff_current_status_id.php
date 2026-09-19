<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backfill sys_staff.current_status_id onto the new 1=Active/2=Inactive/
     * 3=Terminated/4=On Leave scheme. Old value 5 (Approved/Active) becomes 1,
     * old value 28 (Terminated) becomes 3, and NULL defaults to 2 (Inactive).
     * Rows already at 1 or 2 coincidentally already match and are left alone.
     * Safe to re-run: each WHERE only matches rows still on the old scheme.
     */
    public function up(): void
    {
        if (!Schema::hasTable('sys_staff') || !Schema::hasColumn('sys_staff', 'current_status_id')) {
            return;
        }

        DB::table('sys_staff')->where('current_status_id', 5)->update(['current_status_id' => 1]);
        DB::table('sys_staff')->where('current_status_id', 28)->update(['current_status_id' => 3]);
        DB::table('sys_staff')->whereNull('current_status_id')->update(['current_status_id' => 2]);
    }

    /**
     * Best-effort reversal. Cannot distinguish rows that were originally 1/2
     * from rows migrated by up(), so this only reverses the unambiguous case
     * (3 back to 28); 1 and 2 are left as-is since reversing them would also
     * corrupt rows that were already correct before this migration ran.
     */
    public function down(): void
    {
        if (!Schema::hasTable('sys_staff') || !Schema::hasColumn('sys_staff', 'current_status_id')) {
            return;
        }

        DB::table('sys_staff')->where('current_status_id', 3)->update(['current_status_id' => 28]);
    }
};

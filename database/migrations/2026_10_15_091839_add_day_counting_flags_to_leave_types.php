<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flag leave types that charge weekends / public holidays against the balance.
     */
    public function up(): void
    {
        foreach (['counts_weekends', 'counts_public_holidays'] as $column) {
            if (!Schema::hasColumn('sys_hr_leave_types', $column)) {
                Schema::table('sys_hr_leave_types', function (Blueprint $t) use ($column) {
                    $t->boolean($column)->default(false);
                });
            }
        }

        // Maternity & paternity count weekends and holidays; everything else does not.
        DB::table('sys_hr_leave_types')
            ->where(fn($q) => $q->where('type_name', 'ILIKE', '%maternity%')
                                ->orWhere('type_name', 'ILIKE', '%paternity%'))
            ->update(['counts_weekends' => true, 'counts_public_holidays' => true]);
    }

    public function down(): void
    {
        foreach (['counts_weekends', 'counts_public_holidays'] as $column) {
            if (Schema::hasColumn('sys_hr_leave_types', $column)) {
                Schema::table('sys_hr_leave_types', function (Blueprint $t) use ($column) {
                    $t->dropColumn($column);
                });
            }
        }
    }
};

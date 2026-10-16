<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   // database/migrations/xxxx_add_day_counting_flags_to_leave_types.php
public function up(): void
{
    Schema::table('sys_hr_leave_types', function (Blueprint $t) {
        $t->boolean('counts_weekends')->default(false);
        $t->boolean('counts_public_holidays')->default(false);
    });

    // Maternity & paternity count weekends and holidays; everything else does not.
    DB::table('sys_hr_leave_types')
        ->where(fn($q) => $q->where('type_name', 'ILIKE', '%maternity%')
                            ->orWhere('type_name', 'ILIKE', '%paternity%'))
        ->update(['counts_weekends' => true, 'counts_public_holidays' => true]);
}

public function down(): void
{
    Schema::table('sys_hr_leave_types', function (Blueprint $t) {
        $t->dropColumn(['counts_weekends', 'counts_public_holidays']);
    });
}
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pay tiers per leave type (e.g. sick leave: full pay, half pay, no pay), plus the tier tag on each day taken.
     */
    public function up(): void
    {
        if (!Schema::hasTable('sys_hr_leave_type_tiers')) {
            Schema::create('sys_hr_leave_type_tiers', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('leave_type_id')->index();
                $t->unsignedSmallInteger('tier_order');
                $t->string('label');
                $t->unsignedInteger('days')->nullable();   // null = no limit (last tier)
                $t->decimal('pay_rate', 5, 2);             // % of salary
                $t->unique(['leave_type_id', 'tier_order']);
            });
        }

        if (!Schema::hasColumn('sys_hr_leave_requests_234_repeat', 'pay_rate')) {
            Schema::table('sys_hr_leave_requests_234_repeat', function (Blueprint $t) {
                $t->decimal('pay_rate', 5, 2)->nullable();
            });
        }

        if (!Schema::hasColumn('sys_hr_leave_requests_234_repeat', 'tier_order')) {
            Schema::table('sys_hr_leave_requests_234_repeat', function (Blueprint $t) {
                $t->unsignedSmallInteger('tier_order')->nullable();
            });
        }

        // Seed sick leave: 7 days full pay, 7 days half pay, then no pay from day 15
        $sick = DB::table('sys_hr_leave_types')->where('type_name', 'ILIKE', '%sick%')->first();
        if ($sick && !DB::table('sys_hr_leave_type_tiers')->where('leave_type_id', $sick->id)->exists()) {
            DB::table('sys_hr_leave_type_tiers')->insert([
                ['leave_type_id' => $sick->id, 'tier_order' => 1, 'label' => 'Full pay', 'days' => 7, 'pay_rate' => 100],
                ['leave_type_id' => $sick->id, 'tier_order' => 2, 'label' => 'Half pay', 'days' => 7, 'pay_rate' => 50],
                ['leave_type_id' => $sick->id, 'tier_order' => 3, 'label' => 'No pay', 'days' => null, 'pay_rate' => 0],
            ]);
        }
    }

    public function down(): void
    {
        foreach (['pay_rate', 'tier_order'] as $column) {
            if (Schema::hasColumn('sys_hr_leave_requests_234_repeat', $column)) {
                Schema::table('sys_hr_leave_requests_234_repeat', function (Blueprint $t) use ($column) {
                    $t->dropColumn($column);
                });
            }
        }

        Schema::dropIfExists('sys_hr_leave_type_tiers');
    }
};

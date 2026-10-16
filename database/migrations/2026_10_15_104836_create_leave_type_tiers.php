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
   public function up(): void
{
    Schema::create('sys_hr_leave_type_tiers', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('leave_type_id')->index();
        $t->unsignedSmallInteger('tier_order');
        $t->string('label');
        $t->unsignedInteger('days')->nullable();   // null = no limit (last tier)
        $t->decimal('pay_rate', 5, 2);             // % of salary
        $t->unique(['leave_type_id', 'tier_order']);
    });

    Schema::table('sys_hr_leave_requests_234_repeat', function (Blueprint $t) {
        $t->decimal('pay_rate', 5, 2)->nullable();
        $t->unsignedSmallInteger('tier_order')->nullable();
    });

    // Seed sick leave: 7 days full pay, 7 days half pay, then no pay from day 15
    $sick = DB::table('sys_hr_leave_types')->where('type_name', 'ILIKE', '%sick%')->first();
    if ($sick) {
        DB::table('sys_hr_leave_type_tiers')->insert([
            ['leave_type_id' => $sick->id, 'tier_order' => 1, 'label' => 'Full pay',  'days' => 7,    'pay_rate' => 100],
            ['leave_type_id' => $sick->id, 'tier_order' => 2, 'label' => 'Half pay',  'days' => 7,    'pay_rate' => 50],
            ['leave_type_id' => $sick->id, 'tier_order' => 3, 'label' => 'No pay',    'days' => null, 'pay_rate' => 0],
        ]);
    }
}

public function down(): void
{
    Schema::table('sys_hr_leave_requests_234_repeat', fn (Blueprint $t) => $t->dropColumn(['pay_rate', 'tier_order']));
    Schema::dropIfExists('sys_hr_leave_type_tiers');
}
};

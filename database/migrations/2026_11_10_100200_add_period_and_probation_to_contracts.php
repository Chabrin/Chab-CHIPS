<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // HR enters a contract period (12 months) and the end date follows from the start date. The period is kept so the form
    // can show it again. Probation is a period of its own, for any contract, and is all a permanent contract has.
    public function up(): void
    {
        if (!Schema::hasColumn('sys_hr_employee_contracts', 'period_value')) {
            Schema::table('sys_hr_employee_contracts', function (Blueprint $table) {
                $table->unsignedSmallInteger('period_value')->nullable();
                $table->string('period_unit', 10)->nullable();
                $table->unsignedSmallInteger('probation_months')->nullable();
                $table->date('probation_end')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sys_hr_employee_contracts', 'period_value')) {
            Schema::table('sys_hr_employee_contracts', function (Blueprint $table) {
                $table->dropColumn(['period_value', 'period_unit', 'probation_months', 'probation_end']);
            });
        }
    }
};

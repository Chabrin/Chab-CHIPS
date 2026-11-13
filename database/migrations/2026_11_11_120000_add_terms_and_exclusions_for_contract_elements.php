<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Each allowance or deduction on a contract can now say how often it applies (every month, or once in one month) and
    // whether a part month reduces it by the days worked. Rows already on contracts keep behaving as they did: every month,
    // and a part month reduces allowances but not deductions, which is what "prorate" being empty means.
    //
    // A company-wide item (one the run applies to every staff member) can be left out for one person, with a reason. That is
    // kept per contract, so it only ever affects the person on it.
    public function up(): void
    {
        $table = 'sys_hr_employee_contracts_279_repeat';

        if (Schema::hasTable($table) && !Schema::hasColumn($table, 'frequency')) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('frequency', 10)->default('monthly');
                $table->date('once_month')->nullable();
                $table->boolean('prorate')->nullable();
            });
        }

        if (!Schema::hasTable('contract_element_exclusions')) {
            Schema::create('contract_element_exclusions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('contract_id')->index();
                $table->unsignedBigInteger('element_id');
                $table->string('reason', 255);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->unique(['contract_id', 'element_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_element_exclusions');

        $table = 'sys_hr_employee_contracts_279_repeat';
        if (Schema::hasColumn($table, 'frequency')) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['frequency', 'once_month', 'prorate']);
            });
        }
    }
};

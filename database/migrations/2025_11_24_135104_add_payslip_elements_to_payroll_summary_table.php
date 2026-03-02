<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sys_hr_payroll_summary', function (Blueprint $table) {
            $table->text('payslip_elements')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_hr_payroll_summary', function (Blueprint $table) {
            $table->dropColumn('payslip_elements');
        });
    }
};

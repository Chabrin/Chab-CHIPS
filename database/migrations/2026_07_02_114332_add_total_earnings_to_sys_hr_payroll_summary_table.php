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
            if (!Schema::hasColumn('sys_hr_payroll_summary', 'total_earnings')) {
                $table->decimal('total_earnings', 18, 2)->default(0)->after('total_deductions');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_hr_payroll_summary', function (Blueprint $table) {
            if (Schema::hasColumn('sys_hr_payroll_summary', 'total_earnings')) {
                $table->dropColumn('total_earnings');
            }
        });
    }
};

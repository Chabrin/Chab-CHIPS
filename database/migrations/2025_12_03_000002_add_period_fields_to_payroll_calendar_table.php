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
        Schema::table('sys_hr_payroll_calendar', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('payroll_month_date');
            $table->date('end_date')->nullable()->after('start_date');
            $table->string('period_month', 20)->nullable()->after('end_date')->comment('Month name like January, February, etc.');

            // Add indexes for better performance
            $table->index(['start_date', 'end_date']);
            $table->index('period_month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_hr_payroll_calendar', function (Blueprint $table) {
            $table->dropIndex(['start_date', 'end_date']);
            $table->dropIndex(['period_month']);
            $table->dropColumn(['start_date', 'end_date', 'period_month']);
        });
    }
};

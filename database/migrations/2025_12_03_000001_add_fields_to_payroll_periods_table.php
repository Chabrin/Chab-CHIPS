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
        Schema::table('sys_hr_payroll_periods', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('period');
            $table->date('end_date')->nullable()->after('start_date');
            $table->integer('calendar_id')->nullable()->after('end_date');
            $table->string('period_month', 20)->nullable()->after('calendar_id')->comment('Month name like January, February, etc.');

            // Add index for better performance
            $table->index('calendar_id');
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_hr_payroll_periods', function (Blueprint $table) {
            $table->dropIndex(['calendar_id']);
            $table->dropIndex(['start_date', 'end_date']);
            $table->dropColumn(['start_date', 'end_date', 'calendar_id', 'period_month']);
        });
    }
};

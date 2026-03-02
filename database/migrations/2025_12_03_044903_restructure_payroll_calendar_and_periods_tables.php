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
        // Remove detailed fields from calendar (keep only summary)
        Schema::table('sys_hr_payroll_calendar', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date', 'period_month']);
        });

        // Ensure periods table has calendar_id foreign key
        if (!Schema::hasColumn('sys_hr_payroll_periods', 'calendar_id')) {
            Schema::table('sys_hr_payroll_periods', function (Blueprint $table) {
                $table->unsignedBigInteger('calendar_id')->nullable()->after('id');
                $table->foreign('calendar_id')->references('id')->on('sys_hr_payroll_calendar')->onDelete('cascade');
            });
        }

        // Add period_number to track sequence within a calendar month
        if (!Schema::hasColumn('sys_hr_payroll_periods', 'period_number')) {
            Schema::table('sys_hr_payroll_periods', function (Blueprint $table) {
                $table->integer('period_number')->nullable()->after('calendar_id');
            });
        }

        // Add created_by if not exists
        if (!Schema::hasColumn('sys_hr_payroll_periods', 'created_by')) {
            Schema::table('sys_hr_payroll_periods', function (Blueprint $table) {
                $table->unsignedBigInteger('created_by')->nullable()->after('calendar_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore fields to calendar
        Schema::table('sys_hr_payroll_calendar', function (Blueprint $table) {
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('period_month')->nullable();
        });

        // Remove fields from periods
        Schema::table('sys_hr_payroll_periods', function (Blueprint $table) {
            if (Schema::hasColumn('sys_hr_payroll_periods', 'period_number')) {
                $table->dropColumn('period_number');
            }
            if (Schema::hasColumn('sys_hr_payroll_periods', 'created_by')) {
                $table->dropColumn('created_by');
            }
        });
    }
};

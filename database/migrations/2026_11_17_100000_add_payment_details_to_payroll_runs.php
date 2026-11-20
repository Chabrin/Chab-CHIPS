<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A run can now be marked as paid after it is approved. These columns keep who confirmed the payment, when, the date the
     * money went out and the bank reference. Safe to run again.
     */
    public function up(): void
    {
        if (!Schema::hasTable('payroll_runs')) {
            return;
        }

        Schema::table('payroll_runs', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_runs', 'paid_by')) {
                $table->unsignedBigInteger('paid_by')->nullable();
            }
            if (!Schema::hasColumn('payroll_runs', 'paid_at')) {
                $table->timestamp('paid_at')->nullable();
            }
            if (!Schema::hasColumn('payroll_runs', 'payment_date')) {
                $table->date('payment_date')->nullable();
            }
            if (!Schema::hasColumn('payroll_runs', 'payment_reference')) {
                $table->string('payment_reference', 100)->nullable();
            }
            if (!Schema::hasColumn('payroll_runs', 'payment_note')) {
                $table->text('payment_note')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payroll_runs')) {
            return;
        }

        foreach (['paid_by', 'paid_at', 'payment_date', 'payment_reference', 'payment_note'] as $column) {
            if (Schema::hasColumn('payroll_runs', $column)) {
                Schema::table('payroll_runs', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};

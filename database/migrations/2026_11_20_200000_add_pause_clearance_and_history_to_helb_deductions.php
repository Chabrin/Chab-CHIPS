<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HELB Loans (UAT A6): HELB is deducted by law, so it has its own screen and needs no consent form. What it does need, like every
 * deduction, is a reason when it is paused, changed or stopped, and the clearance HELB sends when the loan is paid in full.
 *
 * - sys_helb_deductions gets the reason and time of a pause, and the clearance file and note when the loan is closed.
 * - sys_helb_deduction_events is the history of what was done to each loan, with who and why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_helb_deductions', function (Blueprint $table) {
            if (!Schema::hasColumn('sys_helb_deductions', 'suspended_reason')) {
                $table->text('suspended_reason')->nullable();
            }
            if (!Schema::hasColumn('sys_helb_deductions', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable();
            }
            if (!Schema::hasColumn('sys_helb_deductions', 'cleared_reason')) {
                $table->text('cleared_reason')->nullable();
            }
            if (!Schema::hasColumn('sys_helb_deductions', 'cleared_at')) {
                $table->timestamp('cleared_at')->nullable();
            }
            if (!Schema::hasColumn('sys_helb_deductions', 'cleared_by')) {
                $table->unsignedBigInteger('cleared_by')->nullable();
            }
            if (!Schema::hasColumn('sys_helb_deductions', 'clearance_path')) {
                $table->string('clearance_path')->nullable();
            }
            if (!Schema::hasColumn('sys_helb_deductions', 'clearance_name')) {
                $table->string('clearance_name')->nullable();
            }
        });

        if (!Schema::hasTable('sys_helb_deduction_events')) {
            Schema::create('sys_helb_deduction_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('deduction_id')->index();
                $table->string('event', 30);
                $table->text('reason')->nullable();
                $table->text('details')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_helb_deduction_events');
        Schema::table('sys_helb_deductions', function (Blueprint $table) {
            foreach (['suspended_reason', 'suspended_at', 'cleared_reason', 'cleared_at', 'cleared_by', 'clearance_path', 'clearance_name'] as $column) {
                if (Schema::hasColumn('sys_helb_deductions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled deductions (UAT Round 2, A6): money owed by a staff member that is taken from pay a set amount a month until it is paid off.
 * One table of deductions (with the signed consent form and the pause / close reasons), the types HR can choose from (and add to),
 * what payroll took each month, and a history of what was done to each one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sys_hr_deduction_types')) {
            Schema::create('sys_hr_deduction_types', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                // A loan from the company itself: the law limits it to half of what is left after the other deductions
                $table->boolean('is_company_loan')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });

            // HELB is not here: it is deducted by law, needs no consent form and has its own HELB Loans screen
            $now = now();
            foreach ([['Sacco / Akiba loan', false], ['Salary advance', true], ['Loss / damage recovery', false], ['Other', false]] as [$name, $loan]) {
                DB::table('sys_hr_deduction_types')->insert(['name' => $name, 'is_company_loan' => $loan, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        if (!Schema::hasTable('sys_hr_scheduled_deductions')) {
            Schema::create('sys_hr_scheduled_deductions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('staff_id')->index();
                $table->unsignedBigInteger('type_id')->index();
                $table->string('description')->nullable();
                $table->decimal('total_amount', 12, 2);
                $table->decimal('monthly_amount', 12, 2);
                $table->unsignedInteger('months');
                $table->date('start_month');
                $table->date('end_month')->nullable();
                // What is still owed. Payroll lowers it each month a deduction is taken.
                $table->decimal('balance', 12, 2);
                // on_hold (no signed consent yet, nothing is taken), active, paused, closed (stopped early), completed (paid off)
                $table->string('status', 20)->default('on_hold')->index();
                $table->string('consent_path')->nullable();
                $table->string('consent_name')->nullable();
                $table->timestamp('consent_at')->nullable();
                $table->unsignedBigInteger('consent_by')->nullable();
                $table->timestamp('paused_at')->nullable();
                $table->text('pause_reason')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->text('close_reason')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('sys_hr_scheduled_deduction_repeats')) {
            Schema::create('sys_hr_scheduled_deduction_repeats', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('deduction_id')->index();
                $table->unsignedBigInteger('period_id')->nullable()->index();
                $table->date('deduction_date');
                $table->decimal('amount', 12, 2);
                $table->decimal('balance', 12, 2);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('sys_hr_scheduled_deduction_events')) {
            Schema::create('sys_hr_scheduled_deduction_events', function (Blueprint $table) {
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
        Schema::dropIfExists('sys_hr_scheduled_deduction_events');
        Schema::dropIfExists('sys_hr_scheduled_deduction_repeats');
        Schema::dropIfExists('sys_hr_scheduled_deductions');
        Schema::dropIfExists('sys_hr_deduction_types');
    }
};

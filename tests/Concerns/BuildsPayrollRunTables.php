<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tables the payroll review, approval and payment screens read and write, with only the columns they use.
 * Tables that already exist (for example the menu tables a plain layout adds) are left as they are.
 */
trait BuildsPayrollRunTables
{
    protected function buildPayrollRunTables(): void
    {
        $this->makeTable('sys_hr_payroll_summary', function (Blueprint $t) {
            $t->id();
            $t->integer('staff_id')->nullable();
            $t->string('staff_number_id')->nullable();
            $t->date('period')->nullable();
            $t->integer('period_id')->nullable();
            foreach (['basic_salary', 'total_earnings', 'tax_payable', 'nhif', 'nssf', 'housing_levy', 'total_deductions', 'net_pay'] as $column) {
                $t->decimal($column, 15, 2)->default(0);
            }
            $t->integer('current_status_id')->default(1);
            $t->integer('processed_by')->nullable();
            $t->integer('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable();
        });
        if (!Schema::hasTable('payroll_runs')) {
            (require base_path('database/migrations/2026_11_04_000001_create_payroll_runs_tables.php'))->up();
            (require base_path('database/migrations/2026_11_17_100000_add_payment_details_to_payroll_runs.php'))->up();
            (require base_path('database/migrations/2026_11_17_120000_add_changes_snapshot_to_payroll_runs.php'))->up();
        }
        if (!Schema::hasTable('notifications')) {
            (require base_path('database/migrations/2026_01_23_115917_create_notifications_table.php'))->up();
        }

        $this->makeTable('sys_hr_payroll_calendar', function (Blueprint $t) {
            $t->id();
            $t->integer('payroll_month_date')->nullable(); // a number on the live database, for example 202610
            $t->integer('current_status_id')->default(5);
            $t->integer('created_by')->nullable();
            $t->timestamp('date_time')->nullable();
        });
        $this->makeTable('sys_hr_payroll_periods', function (Blueprint $t) {
            $t->id();
            $t->integer('calendar_id')->nullable();
            $t->integer('period_number')->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->string('period_month')->nullable();
            $t->integer('current_status_id')->default(5);
            $t->integer('created_by')->nullable();
            $t->timestamp('date_time')->nullable();
        });
        $this->makeTable('sys_staff', function (Blueprint $t) {
            $t->id();
            $t->string('staff_number')->nullable();
            $t->string('full_names')->nullable();
            $t->integer('bank_name')->nullable();
            $t->string('account_number')->nullable();
            $t->integer('department_id')->nullable();
            $t->integer('current_status_id')->default(1);
            $t->string('disability')->nullable();
            $t->integer('position_id')->nullable();
        });
        $this->makeTable('sys_set_banks', function (Blueprint $t) { $t->id(); $t->string('bank_name')->nullable(); });
        $this->makeTable('sys_set_departments', function (Blueprint $t) { $t->id(); $t->string('department_name')->nullable(); });
        $this->makeTable('sys_hr_employee_contracts', function (Blueprint $t) {
            $t->id();
            $t->integer('staff_id')->nullable();
            $t->integer('current_status_id')->default(5);
            $t->dateTime('date_start')->nullable();
            $t->dateTime('date_end')->nullable();
            $t->decimal('basic_salary', 15, 2)->default(0);
            $t->integer('position_id')->nullable();
        });
        $this->makeTable('um_tax_bands', function (Blueprint $t) {
            $t->id();
            $t->decimal('range_from', 15, 2);
            $t->decimal('range_to', 15, 2)->nullable();
            $t->decimal('tax_rate', 5, 2);
            $t->boolean('is_last')->default(false);
        });
        (require database_path('migrations/2026_11_20_100000_create_scheduled_deduction_tables.php'))->up(); // payroll now asks for the scheduled deductions due
        $this->makeTable('sys_helb_deductions', function (Blueprint $t) {
            $t->id();
            $t->integer('staff_id')->nullable();
            $t->decimal('loan_balance', 15, 2)->default(0);
            $t->decimal('monthly_deduction', 15, 2)->default(0);
            $t->integer('cleared')->default(0);
            $t->integer('suspended')->default(0);
            $t->date('start_deduction_date')->nullable();
        });
        $this->makeTable('sys_helb_deductions_250_repeat', function (Blueprint $t) {
            $t->id();
            $t->integer('parent_id');
            $t->date('deduction_date')->nullable();
            $t->decimal('amount', 15, 2)->default(0);
            $t->decimal('loan_balance', 15, 2)->default(0);
            $t->integer('current_status_id')->nullable();
            $t->integer('period_id')->nullable();
        });
        $this->makeTable('sys_hr_employee_amortized_deductions', function (Blueprint $t) {
            $t->id();
            $t->integer('staff_id')->nullable();
            $t->decimal('total_amount', 15, 2)->default(0);
            $t->decimal('initial_deduction', 15, 2)->default(0);
            $t->decimal('principal_amount', 15, 2)->default(0);
            $t->decimal('monthly_deduction', 15, 2)->default(0);
            $t->integer('cleared')->default(0);
            $t->integer('suspended')->default(0);
        });
        $this->makeTable('sys_hr_employee_amortized_deductions_252_repeat', function (Blueprint $t) {
            $t->id();
            $t->integer('parent_id');
            $t->date('deduction_date')->nullable();
            $t->decimal('amount', 15, 2)->default(0);
            $t->decimal('interest_amount', 15, 2)->default(0);
            $t->decimal('total_amount', 15, 2)->default(0);
            $t->decimal('amount_balance', 15, 2)->default(0);
            $t->integer('current_status_id')->nullable();
            $t->integer('period_id')->nullable();
        });
        $this->makeTable('josya_users', function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); });
        $this->makeTable('cb_roles', function (Blueprint $t) { $t->id(); $t->string('name'); });
        $this->makeTable('cb_role_user', function (Blueprint $t) { $t->integer('role_id'); $t->integer('user_id'); });
        $this->makeTable('cb_permissions', function (Blueprint $t) { $t->id(); $t->string('name'); });
        $this->makeTable('cb_permission_role', function (Blueprint $t) { $t->integer('permission_id'); $t->integer('role_id'); });
    }

    private function makeTable(string $name, callable $columns): void
    {
        if (!Schema::hasTable($name)) {
            Schema::create($name, $columns);
        }
    }
}

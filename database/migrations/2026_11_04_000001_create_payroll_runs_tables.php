<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SUMMARY = 'sys_hr_payroll_summary';
    private const HELB_LOANS = 'sys_helb_deductions';
    private const HELB_REPEATS = 'sys_helb_deductions_250_repeat';
    private const PRORATED_LOANS = 'sys_hr_employee_amortized_deductions';
    private const PRORATED_REPEATS = 'sys_hr_employee_amortized_deductions_252_repeat';

    /**
     * Create the payroll run tables, add the loan_deductions column and backfill a run for every period already processed. Safe to run again.
     */
    public function up(): void
    {
        if (!Schema::hasTable('payroll_runs')) {
            Schema::create('payroll_runs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('period_id')->unique();
                $table->string('status', 32)->default('draft');
                $table->unsignedBigInteger('processed_by')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('return_comment')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('payroll_run_logs')) {
            Schema::create('payroll_run_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payroll_run_id')->index();
                $table->string('action', 32);
                $table->text('comment')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (Schema::hasTable(self::SUMMARY) && !Schema::hasColumn(self::SUMMARY, 'loan_deductions')) {
            Schema::table(self::SUMMARY, function (Blueprint $table) {
                // Loan repayments in this run, applied to balances only on approval.
                $table->json('loan_deductions')->nullable();
            });
        }

        $this->backfillRuns();
    }

    /**
     * Give every period that was processed before this migration a run, keeping who processed and approved it.
     * A period that already has a run is left alone, so running this again changes nothing.
     */
    private function backfillRuns(): void
    {
        if (!Schema::hasTable(self::SUMMARY)) {
            return;
        }

        // processed_by / approved_by / approved_at come from a separate migration that may not have run yet.
        $hasProcessedBy = Schema::hasColumn(self::SUMMARY, 'processed_by');
        $hasApproval = Schema::hasColumn(self::SUMMARY, 'approved_by') && Schema::hasColumn(self::SUMMARY, 'approved_at');

        $select = 'period_id, MIN(current_status_id) as status_id';
        $select .= $hasProcessedBy ? ', MIN(processed_by) as processed_by' : '';
        $select .= $hasApproval ? ', MIN(approved_by) as approved_by, MAX(approved_at) as approved_at' : '';

        $periods = DB::table(self::SUMMARY)
            ->whereNotNull('period_id')
            ->selectRaw($select)
            ->groupBy('period_id')
            ->get();
        $existing = DB::table('payroll_runs')->pluck('period_id')->map(fn ($id) => (int) $id)->all();

        foreach ($periods as $period) {
            if (in_array((int) $period->period_id, $existing, true)) {
                continue;
            }

            $approved = (int) $period->status_id === 5;

            DB::transaction(function () use ($period, $approved) {
                DB::table('payroll_runs')->insert([
                    'period_id' => $period->period_id,
                    'status' => $approved ? 'approved' : 'draft',
                    'processed_by' => $period->processed_by ?? null,
                    'approved_by' => $approved ? ($period->approved_by ?? null) : null,
                    'approved_at' => $approved ? ($period->approved_at ?? null) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if (!$approved) {
                    $this->deferLoanRepayments((int) $period->period_id);
                }
            });
        }
    }

    /**
     * A run that was still pending when this migration ran had already reduced the HELB and prorated balances.
     * Hand those repayments back and park them on the summary rows, so approving applies them once and
     * discarding the run leaves the balances correct.
     */
    private function deferLoanRepayments(int $periodId): void
    {
        $tablesReady = Schema::hasColumn(self::SUMMARY, 'loan_deductions')
            && Schema::hasTable(self::HELB_REPEATS) && Schema::hasColumn(self::HELB_REPEATS, 'period_id')
            && Schema::hasTable(self::PRORATED_REPEATS) && Schema::hasColumn(self::PRORATED_REPEATS, 'period_id');
        if (!$tablesReady) {
            return;
        }

        $byStaff = [];

        foreach (DB::table(self::HELB_REPEATS)->where('period_id', $periodId)->get() as $repeat) {
            $loan = DB::table(self::HELB_LOANS)->where('id', $repeat->parent_id)->first();
            DB::table(self::HELB_REPEATS)->where('id', $repeat->id)->delete();
            if (!$loan) {
                continue;
            }

            $balance = (float) $loan->loan_balance + (float) $repeat->amount;
            DB::table(self::HELB_LOANS)->where('id', $loan->id)->update(['loan_balance' => $balance, 'cleared' => $balance > 0 ? 0 : $loan->cleared]);
            $byStaff[$loan->staff_id] ??= ['helb' => null, 'prorated' => []];
            $byStaff[$loan->staff_id]['helb'] = ['parent_id' => $loan->id, 'deduction_amount' => (float) $repeat->amount, 'deduction_date' => $repeat->deduction_date];
        }

        foreach (DB::table(self::PRORATED_REPEATS)->where('period_id', $periodId)->get() as $repeat) {
            $loan = DB::table(self::PRORATED_LOANS)->where('id', $repeat->parent_id)->first();
            DB::table(self::PRORATED_REPEATS)->where('id', $repeat->id)->delete();
            if (!$loan) {
                continue;
            }

            $byStaff[$loan->staff_id] ??= ['helb' => null, 'prorated' => []];
            $byStaff[$loan->staff_id]['prorated'][] = ['parent_id' => $loan->id, 'deduction_amount' => (float) $repeat->total_amount, 'deduction_date' => $repeat->deduction_date];

            $repaid = (float) DB::table(self::PRORATED_REPEATS)->where('parent_id', $loan->id)->sum('total_amount');
            $remaining = (float) $loan->total_amount - (float) ($loan->initial_deduction ?? 0) - $repaid;
            if ($remaining > 0 && $loan->cleared) {
                DB::table(self::PRORATED_LOANS)->where('id', $loan->id)->update(['cleared' => 0]);
            }
        }

        foreach ($byStaff as $staffId => $deductions) {
            DB::table(self::SUMMARY)->where('period_id', $periodId)->where('staff_id', $staffId)->update(['loan_deductions' => json_encode($deductions)]);
        }
    }

    /**
     * Drop the loan_deductions column and the payroll run tables.
     */
    public function down(): void
    {
        if (Schema::hasColumn(self::SUMMARY, 'loan_deductions')) {
            Schema::table(self::SUMMARY, function (Blueprint $table) {
                $table->dropColumn('loan_deductions');
            });
        }
        Schema::dropIfExists('payroll_run_logs');
        Schema::dropIfExists('payroll_runs');
    }
};

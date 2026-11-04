<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        Schema::create('payroll_run_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_run_id')->index();
            $table->string('action', 32);
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('sys_hr_payroll_summary', function (Blueprint $table) {
            // Loan repayments in this run, applied to balances only on approval.
            $table->json('loan_deductions')->nullable();
        });

        // Runs processed before this table existed keep their current state.
        $periods = DB::table('sys_hr_payroll_summary')
            ->whereNotNull('period_id')
            ->selectRaw('period_id, MIN(current_status_id) as status_id')
            ->groupBy('period_id')
            ->get();

        foreach ($periods as $period) {
            DB::table('payroll_runs')->insert([
                'period_id' => $period->period_id,
                'status' => (int) $period->status_id === 5 ? 'approved' : 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('sys_hr_payroll_summary', function (Blueprint $table) {
            $table->dropColumn('loan_deductions');
        });
        Schema::dropIfExists('payroll_run_logs');
        Schema::dropIfExists('payroll_runs');
    }
};

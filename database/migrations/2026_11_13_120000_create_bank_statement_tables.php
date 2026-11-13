<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bank statement repository. One header table for every uploaded statement and one line table per bank template
 * (Equity, Co-operative), each keeping the bank's own columns exactly as the file gave them plus the match and
 * payment result of the line, and a counter that numbers the receipts. Also adds the approval workflow that says which roles may confirm an upload; it is created empty and the
 * roles are chosen on the Bank Statements page. Safe to run more than once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cb_bank_statement_uploads')) {
            Schema::create('cb_bank_statement_uploads', function (Blueprint $table) {
                $table->id();
                $table->string('bank', 20);
                $table->string('file_name', 255);
                $table->string('file_path', 500);
                $table->string('file_hash', 64)->unique();
                $table->string('status', 20)->default('Uploaded');
                $table->integer('line_count')->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->integer('matched_count')->default(0);
                $table->integer('unmatched_count')->default(0);
                $table->integer('paid_count')->default(0);
                $table->decimal('paid_amount', 15, 2)->default(0);
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->unsignedBigInteger('confirmed_by')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('matched_at')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();
                $table->index('status');
            });
        }

        if (!Schema::hasTable('cb_equity_statement_lines')) {
            Schema::create('cb_equity_statement_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('upload_id');
                $table->integer('row_no');
                $table->date('tran_date')->nullable();
                $table->string('security_no', 100)->nullable();
                $table->decimal('tran_amt', 15, 2)->nullable();
                $table->string('tran_type', 30)->nullable();
                $table->date('value_date')->nullable();
                $table->string('ref_num', 100)->nullable();
                $table->text('tran_rmks')->nullable();
                $table->string('security_no_2', 100)->nullable();
                $table->text('tran_particular')->nullable();
                $table->string('mode_of_payment', 50)->nullable();
                $this->matchColumns($table);
                $table->index('upload_id');
                $table->index('ref_num');
            });
        }

        if (!Schema::hasTable('cb_coop_statement_lines')) {
            Schema::create('cb_coop_statement_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('upload_id');
                $table->integer('row_no');
                $table->string('reference', 100)->nullable();
                $table->date('posting_date')->nullable();
                $table->date('value_date')->nullable();
                $table->string('code', 100)->nullable();
                $table->string('st_name', 255)->nullable();
                $table->string('credit_account', 100)->nullable();
                $table->decimal('amount', 15, 2)->nullable();
                $table->string('bank_user_id', 100)->nullable();
                $table->string('payment_method', 50)->nullable();
                $table->string('cheque_number', 100)->nullable();
                $table->text('narration')->nullable();
                $this->matchColumns($table);
                $table->index('upload_id');
                $table->index('reference');
            });
        }

        if (!Schema::hasTable('cb_receipt_counters')) {
            Schema::create('cb_receipt_counters', function (Blueprint $table) {
                $table->unsignedSmallInteger('year')->primary();
                $table->unsignedInteger('last_number')->default(0);
            });
        }

        if (Schema::hasTable('approval_workflows')
            && !DB::table('approval_workflows')->where('workflow_name', 'bank_statement_confirmation')->exists()) {
            DB::table('approval_workflows')->insert([
                'workflow_name' => 'bank_statement_confirmation',
                'description' => 'Which roles may confirm an uploaded bank statement so that matching and payment start. Set on the Bank Statements page.',
                'status' => 1,
                'added_on' => now(),
            ]);
        }
    }

    /**
     * The columns every line table shares: what the parser read out of the line and what the matcher decided.
     *
     * @param Blueprint $table
     */
    private function matchColumns(Blueprint $table): void
    {
        $table->string('unit_code', 50)->nullable();
        $table->string('payer_name', 255)->nullable();
        $table->string('match_status', 20)->default('Pending');
        $table->string('match_note', 255)->nullable();
        $table->unsignedBigInteger('unit_id')->nullable();
        $table->unsignedBigInteger('lease_id')->nullable();
        $table->unsignedBigInteger('tenant_id')->nullable();
        $table->unsignedBigInteger('property_id')->nullable();
        $table->unsignedBigInteger('payment_id')->nullable();
        $table->string('receipt_status', 20)->nullable();
        $table->timestamps();
    }

    public function down(): void
    {
        if (Schema::hasTable('approval_workflows')) {
            $id = DB::table('approval_workflows')->where('workflow_name', 'bank_statement_confirmation')->value('id');

            if ($id && !DB::table('approval_requests')->where('workflow_id', $id)->exists()) {
                DB::table('workflow_approvers')->where('workflow_id', $id)->delete();
                DB::table('approval_workflows')->where('id', $id)->delete();
            }
        }

        Schema::dropIfExists('cb_receipt_counters');
        Schema::dropIfExists('cb_coop_statement_lines');
        Schema::dropIfExists('cb_equity_statement_lines');
        Schema::dropIfExists('cb_bank_statement_uploads');
    }
};

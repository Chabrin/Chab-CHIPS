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
        Schema::create('cheque_register_details', function (Blueprint $table) {
            $table->id();

            // Raw text, not an enforced FK -- cheque_register has no stable
            // transferable id from the source side (Postgres auto-generates it).
            // App-level joins on (cheque_no, bank_account) link back to
            // cheque_register (CHQNO+CHQACC -> CHCHQ match rate: 99.99%).
            $table->string('cheque_no', 50)->nullable();
            $table->string('bank_account', 50)->nullable();

            // Matches cb_payment_vouchers_headers_new.voucher_number (CHPVHH.PVNO)
            // at ~99.9% -- kept as raw text, not resolved to an FK.
            $table->string('payment_voucher_number', 50)->nullable();

            // Source ENTRYID (CHCHD.ENTRYID) is a per-voucher line sequence, NOT a
            // globally unique row id -- (payment_voucher_number, entry_sequence) is
            // ~99.8% unique, not enforced as a DB constraint given the near-miss.
            $table->integer('entry_sequence')->nullable();

            $table->decimal('entry_amount', 15, 2)->default(0);

            // Columns below are 100% NULL in the current source data but are
            // migrated anyway (not dropped), kept for future use.
            $table->string('entry_description', 50)->nullable();
            $table->string('eft_code', 50)->nullable();
            $table->string('payee_code', 50)->nullable();
            $table->string('payee_name', 50)->nullable();
            $table->string('payee_account_number', 50)->nullable();
            $table->string('payee_bank', 50)->nullable();
            $table->string('payee_bank_branch', 50)->nullable();
            $table->integer('eft_entry_number')->nullable();
            $table->string('checked_status', 50)->nullable();
            $table->string('checked_by', 50)->nullable();
            $table->date('checked_at')->nullable();
            $table->string('verified_status', 50)->nullable();
            $table->string('verified_by', 50)->nullable();
            $table->date('verified_at')->nullable();

            // ~94% populated in source
            $table->string('entered_by', 50)->nullable();

            $table->timestamps();

            $table->index('cheque_no');
            $table->index('payment_voucher_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cheque_register_details');
    }
};

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
        Schema::create('cheque_register', function (Blueprint $table) {
            $table->id();

            $table->string('cheque_no', 20)->nullable();
            $table->string('cheque_book_no', 20)->nullable(); // source CHQBKNO — 100% NULL in current data, kept for future use
            $table->string('bank_name', 50)->nullable();
            $table->string('bank_account', 50)->nullable();

            // Source CHQUSD has a 3rd state (2) beyond used/unused with no
            // documented meaning in the legacy data — kept raw rather than
            // forced into a boolean. 0 = unused, 1 = used, 2 = unknown.
            $table->unsignedTinyInteger('used_flag')->nullable();
            $table->string('used_by', 50)->nullable();
            $table->date('used_date')->nullable();

            // Raw text reference to cb_payment_vouchers_headers_new.voucher_number
            // (~65% match, only populated on used cheques) — not resolved to an FK.
            $table->string('payment_voucher_number', 25)->nullable();

            $table->string('payee_name', 100)->nullable();

            // Raw payee code (landlord/vendor code, e.g. LAN-00064) — not resolved
            // to client_id, kept as text.
            $table->string('payee_code', 50)->nullable();

            $table->decimal('amount', 15, 2)->default(0);

            $table->boolean('is_cancelled')->default(false);
            $table->string('cancelled_by', 50)->nullable();
            $table->string('cancel_reason', 100)->nullable();
            $table->date('cancelled_at')->nullable();

            // Source TYPCHQ is named "cheque type" but actually holds a leaf
            // availability state ('Open' | 'Closed'), not a type — mapped faithfully.
            $table->string('leaf_status', 10)->nullable();

            $table->date('paid_date')->nullable();
            $table->string('is_posted', 5)->nullable(); // 'Yes' | 'No', matches source
            $table->string('remarks', 100)->nullable();

            // Source CHQRECON/RECNDAT/RECNBY/RECNSTAT — 100% NULL in current data,
            // kept for future use rather than dropped.
            $table->string('is_reconciled', 4)->nullable();
            $table->date('reconciled_at')->nullable();
            $table->string('reconciled_by', 50)->nullable();
            $table->string('reconciliation_status', 50)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('josya_users')->onDelete('set null');

            $table->index('cheque_no');
            $table->index('bank_account');
            $table->index('payee_code');
            $table->index('is_cancelled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cheque_register');
    }
};

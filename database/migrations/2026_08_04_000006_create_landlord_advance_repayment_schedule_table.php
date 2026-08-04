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
        Schema::create('landlord_advance_repayment_schedule', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('landlord_code', 20)->nullable(); // raw LORDID, kept alongside client_id

            $table->integer('loan_series_no')->nullable(); // one landlord can have several loans over time (up to 12+ seen)

            // LNAMT and AMOUNT differ on ~20% of rows in source — not redundant,
            // kept as separate columns rather than collapsed into one.
            $table->decimal('loan_amount', 15, 2)->nullable();
            $table->decimal('amount', 15, 2)->default(0);

            $table->date('repayment_date')->nullable();
            $table->decimal('interest_rate', 5, 2)->nullable();
            $table->integer('repayment_month')->nullable();
            $table->integer('repayment_year')->nullable();
            $table->decimal('interest_amount', 15, 2)->default(0);

            // Running balance columns from source (TOTALAMT / BALANC) — exact
            // opening/closing semantics not fully confirmed, mapped as-is.
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->decimal('balance', 15, 2)->nullable();

            $table->integer('repayment_no')->nullable();
            $table->boolean('is_paid')->default(true); // source STPAID is 100% = 1 in current data

            $table->string('document_reference', 50)->nullable();
            $table->string('transaction_no', 20)->nullable(); // 100% unique in source — natural row key
            $table->string('transaction_type', 20)->nullable(); // 'Principal' | 'Interest'

            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('sys_pm_clients')->onDelete('set null');

            $table->index('client_id');
            $table->index('transaction_no');
            $table->index('transaction_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landlord_advance_repayment_schedule');
    }
};

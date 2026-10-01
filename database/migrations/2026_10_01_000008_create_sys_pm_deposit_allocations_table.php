<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the sys_pm_deposit_allocations table that links deposits to the invoices they were applied against.
     * The Deposit and DepositAllocation models and the deposit reports already query it, but it was never created.
     */
    public function up(): void
    {
        if (!Schema::hasTable('sys_pm_deposit_allocations')) {
            Schema::create('sys_pm_deposit_allocations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('deposit_id');
                $table->unsignedBigInteger('invoice_id');
                $table->decimal('amount_allocated', 15, 2)->default(0);
                $table->timestamp('date_allocated')->nullable();
                $table->text('narration')->nullable();
                $table->string('created_by')->nullable();
                $table->timestamp('created_date')->nullable();
                $table->index('deposit_id');
                $table->index('invoice_id');
            });
        }
    }

    /**
     * Drop the sys_pm_deposit_allocations table.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_pm_deposit_allocations');
    }
};

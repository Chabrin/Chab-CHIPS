<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cb_advance_reschedule_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_advance_id')->comment('Root advance in the chain (never changes across reschedules)');
            $table->unsignedBigInteger('from_advance_id')->comment('The advance being replaced / rescheduled');
            $table->unsignedBigInteger('to_advance_id')->comment('The newly created replacement advance');
            $table->decimal('amount_paid_at_reschedule', 15, 2)->default(0)->comment('Total cleared at time of reschedule');
            $table->decimal('outstanding_balance_at_reschedule', 15, 2)->default(0)->comment('Remaining balance carried into new advance');
            $table->text('narration')->nullable();
            $table->unsignedBigInteger('rescheduled_by');
            $table->datetime('rescheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cb_advance_reschedule_history');
    }
};

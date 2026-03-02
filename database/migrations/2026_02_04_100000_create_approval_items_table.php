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
        Schema::create('cb_approval_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_type', 50)->comment('Type: returns, payments, invoices, etc.');
            $table->unsignedBigInteger('item_id')->comment('ID of the item from its table (e.g., returns_batch_id)');
            $table->string('item_reference', 100)->nullable()->comment('Reference number for quick identification');
            $table->enum('status', ['Pending', 'InProgress', 'Approved', 'Rejected'])->default('Pending')->comment('Overall approval status');
            $table->integer('current_level')->default(1)->comment('Current approval level/step');
            $table->integer('total_levels')->default(4)->comment('Total approval levels required');
            $table->integer('initiated_by')->comment('User who initiated the approval');
            $table->datetime('initiated_at')->comment('When approval was initiated');
            $table->integer('approved_by')->nullable()->comment('Final approver user ID');
            $table->datetime('approved_at')->nullable()->comment('When fully approved');
            $table->integer('rejected_by')->nullable()->comment('User who rejected');
            $table->datetime('rejected_at')->nullable()->comment('When rejected');
            $table->text('rejection_reason')->nullable()->comment('Reason for rejection');
            $table->timestamps();

            // Indexes
            $table->index(['item_type', 'item_id']);
            $table->index('status');
            $table->index('current_level');
            $table->unique(['item_type', 'item_id'], 'unique_item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cb_approval_items');
    }
};

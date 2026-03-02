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
        Schema::create('sys_pm_clients_returns_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number', 20)->comment('Format: YYYYMM (e.g., 202601 for January 2026)');
            $table->string('batch_id', 50)->comment('Format: YYYYMM-PropertyID (e.g., 202601-101)');
            $table->integer('property_id')->comment('Property for which returns were generated');
            $table->integer('returns_count')->default(0)->comment('Number of returns generated in this batch');
            $table->decimal('total_trans_amount', 15, 2)->default(0.00)->comment('Total transaction amount for all returns');
            $table->decimal('total_comm_amount', 15, 2)->default(0.00)->comment('Total commission amount for all returns');
            $table->decimal('total_payable_amount', 15, 2)->default(0.00)->comment('Total payable amount for all returns');
            $table->integer('generated_by')->comment('User ID who generated this batch');
            $table->datetime('generated_at')->comment('Timestamp when batch was generated');
            $table->tinyInteger('approval_status')->default(0)->comment('0=Pending, 1=Approved, 2=Rejected');
            $table->integer('approved_by')->nullable()->comment('User ID who approved/rejected');
            $table->datetime('approved_at')->nullable()->comment('Timestamp of approval/rejection');
            $table->text('remarks')->nullable()->comment('Any remarks or notes about this batch');
            $table->tinyInteger('is_active')->default(1)->comment('1=Active, 0=Cancelled/Voided');

            // Unique constraint
            $table->unique(['batch_number', 'property_id'], 'unique_batch_property');

        });

        //DB::statement("ALTER TABLE `sys_pm_clients_returns_batches` COMMENT = 'Tracks batch generation of client returns by month and property'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_pm_clients_returns_batches');
    }
};

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
        Schema::create('cb_property_merges', function (Blueprint $table) {
            $table->id();
            $table->string('merge_reference')->unique()->comment('Auto-generated reference e.g. MRG-20260311-0001');
            $table->unsignedBigInteger('client_id')->comment('FK to sys_pm_clients');
            $table->enum('merge_type', ['property', 'unit'])->comment('Whether merging whole properties or individual units');
            $table->string('merged_property_name')->comment('Name for the resulting merged property');
            $table->unsignedBigInteger('target_property_id')->nullable()->comment('FK to sys_pm_properties - the property that absorbs others');
            $table->unsignedBigInteger('bank_account_id')->comment('FK to sys_pm_clients_17_repeat - client bank account selected for the merge');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('current_status_id')->default(1)->comment('1=Pending, 2=Completed, 3=Reversed');
            $table->unsignedBigInteger('created_by')->comment('FK to users');
            $table->unsignedBigInteger('approved_by')->nullable()->comment('FK to users');
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable()->comment('FK to users');
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamp('date_time')->useCurrent();
            $table->timestamps();

            $table->index('client_id', 'idx_cb_pm_client');
            $table->index('target_property_id', 'idx_cb_pm_target_property');
            $table->index('bank_account_id', 'idx_cb_pm_bank_account');
            $table->index('current_status_id', 'idx_cb_pm_status');
            $table->index('created_by', 'idx_cb_pm_created_by');
        });

        Schema::create('cb_property_merge_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_merge_id')->comment('FK to cb_property_merges');
            $table->unsignedBigInteger('source_property_id')->comment('FK to sys_pm_properties - property being merged from');
            $table->unsignedBigInteger('unit_id')->nullable()->comment('FK to sys_pm_units - specific unit if unit-level merge');
            $table->json('original_data')->nullable()->comment('Snapshot of original data before merge for reversal');
            $table->timestamp('date_time')->useCurrent();
            $table->timestamps();

            $table->foreign('property_merge_id')
                ->references('id')
                ->on('cb_property_merges')
                ->onDelete('cascade');

            $table->index('source_property_id', 'idx_cb_pmi_source_property');
            $table->index('unit_id', 'idx_cb_pmi_unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cb_property_merge_items');
        Schema::dropIfExists('cb_property_merges');
    }
};

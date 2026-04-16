<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cb_returns_merge_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_reference')->unique();
            $table->unsignedBigInteger('anchor_client_id')->comment('Client profile where this config was created');
            $table->string('landlord_name', 255);
            $table->string('landlord_name_normalized', 255);
            $table->unsignedBigInteger('landlord_id')->comment('Anchor client id used for this config');
            $table->string('landlord_ref', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('date_time')->useCurrent();
            $table->timestamps();

            $table->index('anchor_client_id', 'idx_cb_rmb_anchor_client');
            $table->index('landlord_name_normalized', 'idx_cb_rmb_landlord_name_norm');
            $table->index('landlord_id', 'idx_cb_rmb_landlord_id');
        });

        Schema::create('cb_returns_merge_batch_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id');
            $table->unsignedBigInteger('property_id');
            $table->string('property_ref', 100)->nullable();
            $table->string('property_name', 255)->nullable();
            $table->unsignedBigInteger('landlord_id');
            $table->string('landlord_ref', 100)->nullable();
            $table->string('landlord_name', 255)->nullable();
            $table->timestamp('date_time')->useCurrent();
            $table->timestamps();

            $table->foreign('batch_id')
                ->references('id')
                ->on('cb_returns_merge_batches')
                ->onDelete('cascade');

            $table->unique('property_id', 'uq_cb_rmbi_property_global');
            $table->unique(['batch_id', 'property_id'], 'uq_cb_rmbi_batch_property');
            $table->index('batch_id', 'idx_cb_rmbi_batch');
            $table->index('landlord_id', 'idx_cb_rmbi_landlord');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cb_returns_merge_batch_items');
        Schema::dropIfExists('cb_returns_merge_batches');
    }
};

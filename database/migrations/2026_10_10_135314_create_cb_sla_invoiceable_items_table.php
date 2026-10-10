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
        Schema::create('cb_sla_invoiceable_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sla_id')->constrained('sys_pm_service_agreements')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('sys_set_invoiceable_items');
            // Exactly one of these two may be set — e.g. "Rent Deposit is 300%
            // of rent amount" vs a flat amount; both are optional (nullable).
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('percentage_of_rent', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['sla_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cb_sla_invoiceable_items');
    }
};

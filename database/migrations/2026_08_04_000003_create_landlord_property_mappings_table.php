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
        Schema::create('landlord_property_mappings', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->string('property_code', 50)->nullable();

            // Raw source landlord code (CHLRH.LORDID), kept alongside client_id.
            // In ~7.7% of rows this disagrees with the landlord currently stamped
            // on sys_pm_properties.client_id — see other_views_mod.sql _029.
            // Needed for manual reconciliation, not just a redundant copy.
            $table->string('landlord_code', 50)->nullable();

            $table->string('status', 20)->nullable()->default('Active');

            // Source CHLRH.ENTRYID — 100% NULL in current data, kept for future use
            $table->integer('entry_id')->nullable();

            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('sys_pm_clients')->onDelete('set null');
            $table->foreign('property_id')->references('id')->on('sys_pm_properties')->onDelete('set null');

            $table->index('client_id');
            $table->index('property_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landlord_property_mappings');
    }
};

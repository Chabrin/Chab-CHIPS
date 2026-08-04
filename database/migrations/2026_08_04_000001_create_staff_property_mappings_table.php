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
        Schema::create('staff_property_mappings', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('officer_id')->nullable();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->string('property_code', 50)->nullable();

            // Source (CHOFALL.ALLBY) does not reliably match any officer/staff
            // table (0% join rate against CHOFFICERS) — kept as raw text rather
            // than a broken FK. See mysql_postgres_views/other_views_mod.sql _027.
            $table->string('allocated_by', 50)->nullable();

            $table->date('allocated_on')->nullable();
            $table->string('allocation_type', 50)->nullable();
            $table->string('status', 20)->nullable()->default('Active');

            $table->timestamps();

            $table->foreign('officer_id')->references('id')->on('josya_users')->onDelete('set null');
            $table->foreign('property_id')->references('id')->on('sys_pm_properties')->onDelete('set null');

            $table->index('officer_id');
            $table->index('property_id');
            $table->index('allocation_type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_property_mappings');
    }
};

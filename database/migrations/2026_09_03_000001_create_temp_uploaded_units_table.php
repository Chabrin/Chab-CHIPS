<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staging table for the Units tab's bulk upload — mirrors
 * temp_uploaded_tenants exactly (see that migration's docblock and
 * UnitBulkUploadController for the two-stage import/push flow). A row here
 * is raw, as-uploaded data; unit-number availability and required fields are
 * checked at import time (flagged via push_error, not rejected), everything
 * else at push time. pushed_to_main flips to true once a row has become a
 * real sys_pm_units row, at which point it drops out of the Units Uploads
 * listing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temp_uploaded_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_id');
            $table->string('unit_number')->nullable();
            $table->string('unit_name')->nullable();
            $table->string('usage_type')->nullable();
            $table->string('unit_condition')->nullable();
            $table->string('topology')->nullable();
            $table->string('category')->nullable();
            $table->string('rent_amount')->nullable();
            $table->text('description')->nullable();
            $table->string('vat_able')->nullable();
            $table->string('initial_water_meter_reading')->nullable();
            $table->boolean('pushed_to_main')->default(false);
            $table->text('push_error')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'pushed_to_main']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temp_uploaded_units');
    }
};

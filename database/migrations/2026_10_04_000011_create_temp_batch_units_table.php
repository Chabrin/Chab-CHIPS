<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Staged unit rows of an upload batch, stored as uploaded (text).
 */
return new class extends Migration
{
    /**
     * Create the table if it is missing.
     */
    public function up(): void
    {
        if (Schema::hasTable('temp_batch_units')) {
            return;
        }

        Schema::create('temp_batch_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id')->index();
            $table->unsignedInteger('row_no');
            $table->string('unit_number')->nullable();
            $table->string('unit_name')->nullable();
            $table->string('unit_type')->nullable();
            $table->string('usage_type')->nullable();
            $table->string('topology')->nullable();
            $table->string('rent_amount')->nullable();
            $table->string('vat_able')->nullable();
            $table->string('initial_water_reading')->nullable();
            $table->string('unit_condition')->nullable();
            $table->string('unit_category')->nullable();
            $table->text('unit_description')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Drop the table.
     */
    public function down(): void
    {
        Schema::dropIfExists('temp_batch_units');
    }
};

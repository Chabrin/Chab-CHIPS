<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * One staged upload of the units & tenants template (one property per batch).
 */
return new class extends Migration
{
    /**
     * Create the table if it is missing.
     */
    public function up(): void
    {
        if (Schema::hasTable('temp_upload_batches')) {
            return;
        }

        Schema::create('temp_upload_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_id')->index();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('file_name');
            $table->string('status', 20)->default('staged')->index();
            $table->unsignedInteger('total_units')->default(0);
            $table->unsignedInteger('total_tenants')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Drop the table.
     */
    public function down(): void
    {
        Schema::dropIfExists('temp_upload_batches');
    }
};

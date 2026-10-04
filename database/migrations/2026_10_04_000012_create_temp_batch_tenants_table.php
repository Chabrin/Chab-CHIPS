<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Staged tenant rows (individual and corporate sheets) of an upload batch, stored as uploaded (text).
 */
return new class extends Migration
{
    /**
     * Create the table if it is missing.
     */
    public function up(): void
    {
        if (Schema::hasTable('temp_batch_tenants')) {
            return;
        }

        Schema::create('temp_batch_tenants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id')->index();
            $table->unsignedInteger('row_no');
            $table->string('sheet', 20);
            $table->string('unit_number')->nullable();
            $table->string('tenant_type')->nullable();
            $table->string('lease_start_date')->nullable();
            $table->string('mobile_no')->nullable();
            $table->string('names')->nullable();
            $table->string('company_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('national_id')->nullable();
            $table->string('passport_no')->nullable();
            $table->string('gender')->nullable();
            $table->string('title')->nullable();
            $table->string('email')->nullable();
            $table->string('kra_pin')->nullable();
            $table->string('nationality')->nullable();
            $table->string('other_contact_name')->nullable();
            $table->string('other_contact_phone')->nullable();
            $table->string('other_contact_email')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('nok_name')->nullable();
            $table->string('nok_phone')->nullable();
            $table->string('nok_email')->nullable();
            $table->string('nok_id')->nullable();
            $table->string('nok_relationship')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('lease_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Drop the table.
     */
    public function down(): void
    {
        Schema::dropIfExists('temp_batch_tenants');
    }
};

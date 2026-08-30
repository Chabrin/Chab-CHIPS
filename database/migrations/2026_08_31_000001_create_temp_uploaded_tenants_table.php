<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staging table for the Tenants tab's bulk upload. A row here is raw,
 * as-uploaded data — no business validation applied yet (unit vacancy,
 * nationality lookup, duplicate national ID, etc. all happen at push time,
 * see TenantBulkUploadController::pushToMain()). pushed_to_main flips to
 * true once a row has been successfully turned into a real sys_pm_clients
 * tenant, at which point it drops out of the Tenants Uploads listing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temp_uploaded_tenants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_id');
            $table->string('unit_code')->nullable();
            $table->string('title')->nullable();
            $table->string('names')->nullable();
            $table->string('gender')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('email_address')->nullable();
            $table->string('pin_number')->nullable();
            $table->string('nationality')->nullable();
            $table->string('national_id')->nullable();
            $table->string('passport_number')->nullable();
            $table->string('other_contact_name')->nullable();
            $table->string('other_contact_email')->nullable();
            $table->string('other_contact_phone')->nullable();
            $table->string('next_of_kin_name')->nullable();
            $table->string('next_of_kin_mobile')->nullable();
            $table->string('next_of_kin_email')->nullable();
            $table->string('next_of_kin_national_id')->nullable();
            $table->string('next_of_kin_relationship')->nullable();
            $table->boolean('pushed_to_main')->default(false);
            $table->text('push_error')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'pushed_to_main']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temp_uploaded_tenants');
    }
};

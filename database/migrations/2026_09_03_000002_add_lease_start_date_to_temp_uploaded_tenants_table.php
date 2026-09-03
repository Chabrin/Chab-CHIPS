<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lease Start Date — new required template column. Stored raw (string) like
 * every other staged field; parsed/validated as a real date only at push
 * time (TenantBulkUploadController::pushOneRow()), where it's used to create
 * the tenant's LeaseAgreement via LeaseCreationTrait.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temp_uploaded_tenants', function (Blueprint $table) {
            $table->string('lease_start_date')->nullable()->after('unit_code');
        });
    }

    public function down(): void
    {
        Schema::table('temp_uploaded_tenants', function (Blueprint $table) {
            $table->dropColumn('lease_start_date');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cb_tenant_records', function (Blueprint $table) {
            $table->id();

            // Traces each row back to the sys_pm_clients row it was migrated from —
            // needed so the data-migration command can be re-run safely (skip rows
            // already copied) and so this table can later be cross-referenced with
            // leases/units/payments, which all still key off sys_pm_clients.id.
            $table->unsignedBigInteger('legacy_client_id')->nullable()->unique();
            $table->foreign('legacy_client_id')->references('id')->on('sys_pm_clients')->nullOnDelete();

            // No DB-level enum — app-validated against INDIVIDUAL, COMPANY, CARETAKER, OWNERS.
            $table->string('tenant_type', 20)->nullable();

            // Personal details
            $table->foreignId('salutation_id')->nullable()->constrained('cb_salutations')->nullOnDelete();
            $table->string('names', 255)->nullable();
            $table->foreignId('gender_id')->nullable()->constrained('cb_genders')->nullOnDelete();

            // Contact
            $table->string('phone', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('physical_address', 500)->nullable();

            // Identification
            $table->string('nationality', 100)->nullable();
            // No DB-level enum — app-validated against PASSPORT, NATIONAL_ID.
            $table->string('id_document_type', 20)->nullable();
            $table->string('national_id', 20)->nullable();
            $table->string('passport_number', 50)->nullable();
            $table->string('kra_pin', 20)->nullable();

            // Registration
            $table->date('registered_date')->nullable();

            $table->timestamps();

            $table->index('tenant_type');
            $table->index('names');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cb_tenant_records');
    }
};

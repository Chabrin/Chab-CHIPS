<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staging table for the client page's Rent Invoicing flow. Mirrors
 * temp_uploaded_tenants/temp_uploaded_units exactly — a row here is a
 * reviewable, not-yet-real invoice; pushed_to_main flips true once
 * RentInvoicingController::pushToMain() has turned it into a real
 * sys_pm_debtors_invoices row via InvoiceCreationTrait.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temp_generated_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('lease_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('unit_id');
            $table->string('tenant_name')->nullable();
            $table->string('unit_code')->nullable();
            $table->decimal('base_rent', 13, 2)->nullable();
            $table->decimal('prorated_amount', 13, 2);
            $table->integer('proration_days')->nullable();
            $table->integer('month_days')->nullable();
            $table->date('returns_period');
            $table->date('date_due');
            $table->boolean('pushed_to_main')->default(false);
            $table->text('push_error')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'pushed_to_main']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temp_generated_invoices');
    }
};

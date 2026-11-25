<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_ownership_transfers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('property_id')->index();
            $t->unsignedBigInteger('from_client_id');
            $t->unsignedBigInteger('to_client_id');
            $t->unsignedBigInteger('old_sla_id');
            $t->unsignedBigInteger('new_sla_id')->nullable();
            $t->unsignedBigInteger('bank_detail_id');
            $t->date('effective_date');
            $t->string('status', 30)->default('draft')->index();
            // A unique nullable key prevents concurrent requests for one property; released at completion/cancellation.
            $t->unsignedBigInteger('pending_property_id')->nullable()->unique();
            $t->string('arrears_policy', 20);
            $t->text('reason');
            $t->json('terms');
            $t->json('documents');
            $t->json('snapshot')->nullable();
            $t->string('snapshot_hash', 64)->nullable();
            $t->text('finance_notes')->nullable();
            $t->unsignedBigInteger('created_by');
            $t->unsignedBigInteger('reviewed_by')->nullable();
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('property_ownership_transfer_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('transfer_id')->constrained('property_ownership_transfers');
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('action', 40);
            $t->text('notes')->nullable();
            $t->json('details')->nullable();
            $t->timestamp('created_at');
        });
        // Assign collection rights without overwriting the original invoice, allocations or journal.
        Schema::create('property_transfer_invoice_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('transfer_id')->constrained('property_ownership_transfers');
            $t->unsignedBigInteger('invoice_id')->index();
            $t->unsignedBigInteger('client_id');
            $t->unsignedBigInteger('sla_id');
            $t->date('effective_date');
            $t->decimal('balance_at_transfer', 18, 2);
            $t->unique(['transfer_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_transfer_invoice_assignments');
        Schema::dropIfExists('property_ownership_transfer_events');
        Schema::dropIfExists('property_ownership_transfers');
    }
};

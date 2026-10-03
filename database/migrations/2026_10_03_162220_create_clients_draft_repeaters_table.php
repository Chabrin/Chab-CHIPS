<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Staging table for the client-create page's Banks/Other Contacts/Next of Kin/Partners
        // modals — the client doesn't have an id yet at that point, so rows are tagged by a
        // one-time draft_token instead and moved into the real tables (then cleared) once the
        // main client form is successfully submitted. One table for all four kinds since it's
        // purely transient; `kind` is a plain string (no DB enum), validated app-side.
        Schema::create('clients_draft_repeaters', function (Blueprint $table) {
            $table->id();
            $table->string('draft_token', 64);
            $table->string('kind', 20);

            // bank
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('account_name')->nullable();
            $table->string('account_number', 50)->nullable();

            // contact
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_mobile', 20)->nullable();

            // next of kin
            $table->string('kin_first_name')->nullable();
            $table->string('kin_second_name')->nullable();
            $table->string('kin_last_name')->nullable();
            $table->string('kin_mobile', 20)->nullable();
            $table->string('kin_email')->nullable();
            $table->string('kin_relationship', 100)->nullable();
            $table->string('kin_national_id', 50)->nullable();

            // partner
            $table->string('partner_name')->nullable();
            $table->string('partner_national_id', 50)->nullable();
            $table->string('partner_kra_pin', 50)->nullable();
            $table->string('partner_phone', 20)->nullable();

            $table->timestamps();

            $table->index(['draft_token', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients_draft_repeaters');
    }
};

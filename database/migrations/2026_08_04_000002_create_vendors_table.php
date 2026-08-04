<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();

            $table->string('vendor_code', 50)->unique();
            $table->string('vendor_name')->nullable();
            $table->string('vendor_type', 10)->nullable(); // 'LAN' (landlord) | 'OTH' (other supplier)

            // Only populated when vendor_type = 'LAN' and the code resolves to
            // an existing landlord (CHVENDS.VENDORID -> CHLRDS.LORDID, ~99% match).
            $table->unsignedBigInteger('client_id')->nullable();

            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('address_line_3')->nullable();
            $table->string('address_line_4')->nullable();

            // Source column CHVENDS.BANKACC actually holds the bank NAME
            // (not an account code) in the live data — mapped faithfully.
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('account_number', 50)->nullable();

            $table->decimal('current_balance', 15, 2)->default(0);
            $table->timestamp('last_maintained_at')->nullable();

            // Free-text editor initials from source (CHVENDS.AUDTUSR) — cannot
            // be reliably resolved to a staff/user FK, kept as raw text.
            $table->string('audit_user', 50)->nullable();

            $table->string('status', 20)->default('Active');

            // Source CHVENDS.TAXABLE — values in the live data are large
            // inconsistent integers, not a usable 0/1 flag. Kept raw rather than
            // dropped; meaning needs to be worked out before relying on it.
            $table->bigInteger('taxable')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('sys_pm_clients')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('josya_users')->onDelete('set null');

            $table->index('vendor_type');
            $table->index('client_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};

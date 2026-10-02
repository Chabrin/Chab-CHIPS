<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants_bank_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cb_tenant_records')->cascadeOnDelete();

            // FKs (not plain text) to match the cascading Bank -> Branch select
            // already used on the tenant create/edit forms.
            $table->foreignId('bank_id')->nullable()->constrained('sys_set_banks')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('sys_set_banks_branches')->nullOnDelete();

            $table->string('account_name', 255)->nullable();
            $table->string('account_number', 255)->nullable();
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants_bank_details');
    }
};

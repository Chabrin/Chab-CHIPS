<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants_nextof_kin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cb_tenant_records')->cascadeOnDelete();

            $table->string('kin_names', 255);
            $table->string('mobile_number', 20)->nullable();
            $table->string('email_address', 255)->nullable();
            // No DB-level enum/lookup table for relationship — plain text, app-rendered dropdown.
            $table->string('relationship', 50)->nullable();
            $table->string('national_id', 20)->nullable();
            $table->string('second_name', 255)->nullable();

            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants_nextof_kin');
    }
};

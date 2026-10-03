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
        Schema::table('cb_tenant_records', function (Blueprint $table) {
            $table->string('company_registration_number', 100)->nullable()->after('tenant_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cb_tenant_records', function (Blueprint $table) {
            $table->dropColumn('company_registration_number');
        });
    }
};

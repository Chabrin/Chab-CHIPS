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
        Schema::table('cb_tenant_history', function (Blueprint $table) {
            $table->decimal('rent_amount', 12, 2)->nullable()->after('lease_start_date');
            $table->unsignedBigInteger('sla_id')->nullable()->after('rent_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cb_tenant_history', function (Blueprint $table) {
            $table->dropColumn(['rent_amount', 'sla_id']);
        });
    }
};

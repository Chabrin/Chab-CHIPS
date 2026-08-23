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
        Schema::table('sys_pm_lease_agreements', function (Blueprint $table) {
            $table->decimal('service_charges_amount', 12, 2)->nullable()->after('escalation_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_pm_lease_agreements', function (Blueprint $table) {
            $table->dropColumn('service_charges_amount');
        });
    }
};

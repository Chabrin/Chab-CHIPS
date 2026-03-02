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
        Schema::table('sys_pm_lease_agreements_27_repeat', function (Blueprint $table) {
            $table->date('cycle_one_invoiced_date')->nullable()->after('cycle_one_invoiced')
                ->comment('Date when cycle_id=1 item was invoiced for audit purposes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_pm_lease_agreements_27_repeat', function (Blueprint $table) {
            $table->dropColumn('cycle_one_invoiced_date');
        });
    }
};

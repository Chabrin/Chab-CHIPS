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
        Schema::table('sys_pm_debtors_invoices', function (Blueprint $table) {
            $table->integer('cycle_id')->nullable()->after('item_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_pm_debtors_invoices', function (Blueprint $table) {
            $table->dropColumn('cycle_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_pm_generated_invoice_months', function (Blueprint $table) {
            // Minute-precision run identifier (YmdHi) shared by every invoice a
            // generation run creates — replaces matching invoices to their run by
            // created_by + a time window. Nullable: existing rows predate this.
            $table->string('batch_number', 20)->nullable()->after('month_year');
            $table->unique('batch_number');
        });
    }

    public function down(): void
    {
        Schema::table('sys_pm_generated_invoice_months', function (Blueprint $table) {
            $table->dropUnique(['batch_number']);
            $table->dropColumn('batch_number');
        });
    }
};
